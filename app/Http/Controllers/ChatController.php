<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Document;
use App\Models\Evidence;
use App\Models\LegalCase;
use App\Services\LegalChatAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * AI legal assistant — a persisted, multi-turn chat for firm leadership. Each
 * session belongs to one user and may optionally be anchored to a case, whose
 * facts and tracking history are fed to {@see LegalChatAssistant} as context.
 *
 * Access is gated by the `assistant.use` permission on the route group.
 */
class ChatController extends Controller
{
    /** Most recent turns sent to the model, to bound prompt size. */
    private const HISTORY_LIMIT = 40;

    /**
     * The chat workspace: the user's sessions, the active session with its
     * messages, and the cases available to attach for context.
     */
    public function index(Request $request): Response
    {
        $sessions = $this->sessionsFor($request);

        $active = null;
        if (($uuid = (string) $request->query('session')) !== '') {
            $active = ChatSession::where('user_id', $request->user()->id)->where('uuid', $uuid)->first();
        }
        $active ??= ChatSession::where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        return Inertia::render('chat/Index', [
            'sessions' => $sessions,
            'activeSession' => $active ? $this->serializeSession($active->load('case'), withMessages: true) : null,
            'cases' => LegalCase::query()
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['id', 'uuid', 'case_number', 'title'])
                ->map(fn (LegalCase $c): array => [
                    'uuid' => $c->uuid,
                    'case_number' => $c->case_number,
                    'title' => $c->title,
                ]),
        ]);
    }

    /**
     * Start a new conversation, optionally anchored to a case.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'case' => ['nullable', 'string'],
        ]);

        $session = ChatSession::create([
            'user_id' => $request->user()->id,
            'title' => 'New chat',
            'case_id' => $this->resolveCaseId($data['case'] ?? null),
        ]);

        return redirect()->route('assistant.index', ['session' => $session->uuid]);
    }

    /**
     * Rename a session and/or change the case it is anchored to.
     */
    public function update(Request $request, ChatSession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:120'],
            // 'case' present + non-empty attaches; present + empty detaches; absent leaves as-is.
            'case' => ['nullable', 'string'],
        ]);

        if (array_key_exists('title', $data) && trim((string) $data['title']) !== '') {
            $session->title = trim((string) $data['title']);
        }

        if ($request->has('case')) {
            $session->case_id = $this->resolveCaseId($data['case'] ?? null);
        }

        $session->save();

        return back();
    }

    /**
     * Post a message and stream the assistant's reply over Server-Sent Events.
     */
    public function stream(Request $request, ChatSession $session, LegalChatAssistant $assistant): StreamedResponse
    {
        $this->authorizeOwner($request, $session);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        return $this->streamResponse($session, trim($data['content']), $assistant, $request);
    }

    /**
     * Drop the last assistant reply and stream a fresh answer to the same prompt.
     */
    public function regenerate(Request $request, ChatSession $session, LegalChatAssistant $assistant): StreamedResponse
    {
        $this->authorizeOwner($request, $session);

        $last = $session->messages()->orderByDesc('id')->first();
        if ($last && $last->role === ChatMessage::ROLE_ASSISTANT) {
            $last->delete();
        }

        abort_if(
            $session->messages()->where('role', ChatMessage::ROLE_USER)->count() === 0,
            422,
            'There is nothing to regenerate yet.',
        );

        return $this->streamResponse($session, null, $assistant, $request);
    }

    /**
     * Edit an earlier user message, discard everything after it, and stream a new
     * reply from that point — like editing and resending in a modern chat client.
     */
    public function edit(Request $request, ChatSession $session, ChatMessage $message, LegalChatAssistant $assistant): StreamedResponse
    {
        $this->authorizeOwner($request, $session);
        abort_unless($message->chat_session_id === $session->id && $message->role === ChatMessage::ROLE_USER, 404);

        $data = $request->validate([
            'content' => ['required', 'string', 'min:1', 'max:4000'],
        ]);

        $message->update(['content' => trim($data['content'])]);
        $session->messages()->where('id', '>', $message->id)->delete();

        return $this->streamResponse($session, null, $assistant, $request);
    }

    /**
     * Record thumbs up/down feedback on an assistant reply (0 clears it).
     */
    public function feedback(Request $request, ChatSession $session, ChatMessage $message): JsonResponse
    {
        $this->authorizeOwner($request, $session);
        abort_unless($message->chat_session_id === $session->id && $message->role === ChatMessage::ROLE_ASSISTANT, 404);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $message->update(['rating' => $data['rating'] === 0 ? null : $data['rating']]);

        return response()->json(['rating' => $message->rating]);
    }

    /**
     * The shared SSE generator: optionally persist a new user turn, run the
     * agentic/streaming assistant, persist the reply (even if the client stops
     * mid-stream), and forward status/delta/citation events to the browser.
     */
    private function streamResponse(ChatSession $session, ?string $newUserContent, LegalChatAssistant $assistant, Request $request): StreamedResponse
    {
        $user = $request->user();
        $isFirst = $newUserContent !== null && $session->messages()->count() === 0;

        return response()->stream(function () use ($session, $newUserContent, $assistant, $user, $isFirst): void {
            @ignore_user_abort(true);
            $this->openStream();

            $emit = function (string $event, array $data): void {
                if (connection_aborted()) {
                    return;
                }
                echo 'event: '.$event."\n";
                echo 'data: '.((string) json_encode($data))."\n\n";
                $this->flushStream();
            };

            // Persist the new user turn (if any) so it joins the history and is stored.
            $userMessage = null;
            if ($newUserContent !== null) {
                $userMessage = $session->messages()->create([
                    'team_id' => $session->team_id,
                    'role' => ChatMessage::ROLE_USER,
                    'content' => $newUserContent,
                ]);
                $emit('user', ['message' => $this->serializeMessage($userMessage)]);
            }

            $history = $session->messages()
                ->orderBy('id')
                ->get(['role', 'content'])
                ->map(fn (ChatMessage $m): array => ['role' => $m->role, 'content' => $m->content])
                ->all();
            if (count($history) > self::HISTORY_LIMIT) {
                $history = array_slice($history, -self::HISTORY_LIMIT);
            }

            try {
                $result = $assistant->streamConversation($history, $this->caseContext($session), $user, $emit);
            } catch (Throwable $e) {
                // Nothing usable was produced — don't leave a dangling prompt.
                $userMessage?->delete();
                $emit('error', ['message' => $e->getMessage()]);

                return;
            }

            $content = trim($result['content']);

            if ($content === '') {
                // The model produced no usable text — surface it instead of silently
                // dropping the bubble. (If the client already disconnected, the emit
                // is a no-op, so a user-initiated Stop still won't show an error.)
                $emit('error', ['message' => 'The assistant could not generate a reply. Please try again.']);

                return;
            }

            // A backend error cut the reply short after some text had streamed: keep
            // what we have, but mark it so it doesn't masquerade as a finished answer.
            if ($result['incomplete'] ?? false) {
                $content .= "\n\n*⚠️ This reply was interrupted before it finished — tap regenerate to retry.*";
            }

            $assistantMessage = $session->messages()->create([
                'team_id' => $session->team_id,
                'role' => ChatMessage::ROLE_ASSISTANT,
                'content' => $content,
                'citations' => $result['citations'] ?: null,
            ]);

            if ($isFirst && $newUserContent !== null) {
                $session->title = Str::limit($newUserContent, 56, '…');
            }
            $session->last_message_at = Carbon::now();
            $session->save();

            $emit('done', [
                'reply' => $this->serializeMessage($assistantMessage),
                'session' => $this->sessionMeta($session),
            ]);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionMeta(ChatSession $session): array
    {
        return [
            'uuid' => $session->uuid,
            'title' => $session->title,
            'last_message_at' => $session->last_message_at?->toIso8601String(),
        ];
    }

    /** Tear down output buffering so SSE events flush to the client immediately. */
    private function openStream(): void
    {
        while (ob_get_level() > 0) {
            @ob_end_flush();
        }
    }

    private function flushStream(): void
    {
        if (ob_get_level() > 0) {
            @ob_flush();
        }
        @flush();
    }

    /**
     * Permanently delete a conversation and its messages.
     */
    public function destroy(Request $request, ChatSession $session): RedirectResponse
    {
        $this->authorizeOwner($request, $session);

        $session->delete();

        return redirect()->route('assistant.index');
    }

    /**
     * The current user's conversations, newest activity first, for the sidebar.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function sessionsFor(Request $request)
    {
        return ChatSession::with('case')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ChatSession $s): array => $this->serializeSession($s, withMessages: false));
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSession(ChatSession $session, bool $withMessages): array
    {
        $payload = [
            'uuid' => $session->uuid,
            'title' => $session->title,
            'last_message_at' => $session->last_message_at?->toIso8601String(),
            'case' => $session->case ? [
                'uuid' => $session->case->uuid,
                'case_number' => $session->case->case_number,
                'title' => $session->case->title,
            ] : null,
        ];

        if ($withMessages) {
            $payload['messages'] = $session->messages()
                ->get()
                ->map(fn (ChatMessage $m): array => $this->serializeMessage($m))
                ->all();
        }

        return $payload;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMessage(ChatMessage $message): array
    {
        return [
            'id' => $message->id,
            'role' => $message->role,
            'content' => $message->content,
            'citations' => $message->citations ?? [],
            'rating' => $message->rating,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Build the attached-case context block for the assistant, or null when the
     * session isn't anchored to a case (or the case is gone).
     *
     * @return array<string, mixed>|null
     */
    private function caseContext(ChatSession $session): ?array
    {
        if ($session->case_id === null) {
            return null;
        }

        $case = LegalCase::with('events')->find($session->case_id);
        if ($case === null) {
            return null;
        }

        $files = $this->caseFiles($case);

        return [
            'id' => $case->id,
            'title' => $case->title,
            'case_number' => $case->case_number,
            'case_type' => $case->case_type?->value,
            'court_name' => $case->court_name,
            'opposing_party' => $case->opposing_party,
            'description' => $case->description,
            'history' => $case->trackingHistory(),
            // Multimodal: images & PDFs Claude can view directly, plus a text
            // inventory of every document & evidence item (incl. videos by name).
            'attachments' => $files['attachments'],
            'files_note' => $files['note'],
        ];
    }

    /**
     * Gather the case's documents & evidence: a list of viewable attachments
     * (images / PDFs the model can study) plus a plain-text inventory of every
     * file on record so the assistant is aware of what exists (including videos
     * and office files it cannot open).
     *
     * @return array{attachments: array<int, array{kind: string, media_type: string, disk: string, path: string}>, note: string}
     */
    private function caseFiles(LegalCase $case): array
    {
        $imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

        $attachments = [];
        $attached = [];

        $consider = function (Document $d) use (&$attachments, &$attached, $imageTypes): bool {
            if ($d->id === null || isset($attached[$d->id]) || $d->path === null) {
                return isset($attached[$d->id]);
            }
            $mime = (string) $d->mime_type;
            if (in_array($mime, $imageTypes, true)) {
                $kind = 'image';
            } elseif ($mime === 'application/pdf') {
                $kind = 'pdf';
            } else {
                return false;
            }
            $attachments[] = ['kind' => $kind, 'media_type' => $mime, 'disk' => (string) $d->disk, 'path' => (string) $d->path];
            $attached[$d->id] = true;

            return true;
        };

        // Video & audio can't be processed by the model — skip them entirely.
        $skip = fn (?string $mime): bool => str_starts_with((string) $mime, 'video/') || str_starts_with((string) $mime, 'audio/');

        $docLines = [];
        foreach ($case->documents()->latestVersions()->get(['id', 'name', 'disk', 'path', 'mime_type', 'extension']) as $d) {
            if ($skip($d->mime_type)) {
                continue;
            }
            $viewable = $consider($d);
            $docLines[] = '- '.$d->name.($d->extension ? '.'.$d->extension : '').' ('.($d->mime_type ?: 'file').')'.($viewable ? ' — attached for you to view' : '');
        }

        $evLines = [];
        foreach ($case->evidence()->with('document:id,name,disk,path,mime_type,extension')->get() as $e) {
            /** @var Evidence $e */
            $line = '- '.($e->reference_number ? '['.$e->reference_number.'] ' : '').$e->title.' ('.$e->type->label().', '.$e->status->label().')';
            if ($e->document && ! $skip($e->document->mime_type)) {
                $viewable = $consider($e->document);
                $line .= ' — file: '.$e->document->name.($viewable ? ' (attached for you to view)' : '');
            }
            $evLines[] = $line;
        }

        $note = '';
        if ($docLines !== [] || $evLines !== []) {
            $parts = ['CASE FILES ON RECORD'];
            if ($docLines !== []) {
                $parts[] = 'Documents:';
                $parts = array_merge($parts, $docLines);
            }
            if ($evLines !== []) {
                $parts[] = '';
                $parts[] = 'Evidence:';
                $parts = array_merge($parts, $evLines);
            }
            $note = implode("\n", $parts);
        }

        return ['attachments' => $attachments, 'note' => $note];
    }

    /**
     * Resolve a case UUID (from the form) to its primary key within the team,
     * or null when none/unknown. The team scope guards against cross-team access.
     */
    private function resolveCaseId(?string $uuid): ?int
    {
        $uuid = trim((string) $uuid);
        if ($uuid === '') {
            return null;
        }

        return LegalCase::where('uuid', $uuid)->value('id');
    }

    private function authorizeOwner(Request $request, ChatSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);
    }
}
