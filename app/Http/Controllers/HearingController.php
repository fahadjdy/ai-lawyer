<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Resources\HearingResource;
use App\Models\Hearing;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HearingController extends Controller
{
    public function index(Request $request): Response
    {
        // Default to the current month window for the calendar view.
        $from = $request->date('from') ?? now()->startOfMonth();
        $to = $request->date('to') ?? now()->endOfMonth()->addMonth();

        $hearings = Hearing::with('case:id,uuid,case_number,title,status')
            ->between($from->toDateTimeString(), $to->toDateTimeString())
            ->orderBy('scheduled_at')
            ->get();

        return Inertia::render('hearings/Index', [
            'hearings' => HearingResource::collection($hearings),
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'upcoming' => HearingResource::collection(
                Hearing::with('case:id,uuid,case_number,title')->upcoming()->limit(8)->get(),
            ),
        ]);
    }
}
