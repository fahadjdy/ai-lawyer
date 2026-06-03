/** Server-Sent-Events client over fetch, so we can POST a body and stream the reply. */
import { xsrfToken } from './http';

export interface SSEOptions {
    onEvent: (event: string, data: any) => void;
    signal?: AbortSignal;
}

/**
 * POST `body` to `url` and dispatch each `event:`/`data:` SSE frame to onEvent.
 * Throws on abort (AbortError) so the caller can distinguish a user stop from an
 * error. A non-OK response is surfaced as a single synthetic 'error' event.
 */
export async function streamSSE(url: string, body: unknown, { onEvent, signal }: SSEOptions): Promise<void> {
    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': xsrfToken(),
        },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
        signal,
    });

    if (!res.ok || !res.body) {
        let message = `Request failed (${res.status}).`;
        try {
            const j = await res.json();
            message = j?.message ?? message;
        } catch {
            /* non-JSON error body */
        }
        onEvent('error', { message });
        return;
    }

    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { value, done } = await reader.read();
        if (done) break;
        buffer += decoder.decode(value, { stream: true });

        let sep: number;
        while ((sep = buffer.indexOf('\n\n')) !== -1) {
            const frame = buffer.slice(0, sep);
            buffer = buffer.slice(sep + 2);
            dispatch(frame, onEvent);
        }
    }
}

function dispatch(frame: string, onEvent: (event: string, data: any) => void): void {
    let event = 'message';
    const dataLines: string[] = [];

    for (const line of frame.split('\n')) {
        if (line.startsWith('event:')) event = line.slice(6).trim();
        else if (line.startsWith('data:')) dataLines.push(line.slice(5).trim());
    }

    if (dataLines.length === 0) return;

    const raw = dataLines.join('\n');
    let data: any = raw;
    try {
        data = JSON.parse(raw);
    } catch {
        /* leave as string */
    }
    onEvent(event, data);
}
