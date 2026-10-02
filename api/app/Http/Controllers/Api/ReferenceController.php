<?php

namespace App\Http\Controllers\Api;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Read-only endpoints used to fill lists and the staff dashboard. */
class ReferenceController extends Controller
{
    public function categories(): JsonResponse
    {
        return response()->json(['data' => Category::orderBy('name')->get(['id', 'name'])]);
    }

    /** Staff members a ticket can be assigned to. */
    public function agents(): JsonResponse
    {
        Gate::authorize('manage', Ticket::class);

        return response()->json([
            'data' => User::whereIn('role', [Role::Agent, Role::Admin])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function stats(Request $request): JsonResponse
    {
        Gate::authorize('manage', Ticket::class);

        // One grouped query per breakdown, whatever the number of tickets.
        $byStatus = Ticket::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $byPriority = Ticket::query()->selectRaw('priority, count(*) as total')->groupBy('priority')->pluck('total', 'priority');

        $open = [TicketStatus::Open, TicketStatus::InProgress];

        // Computed in PHP so the result is identical on MySQL and SQLite.
        $hours = Ticket::whereNotNull('resolved_at')
            ->get(['created_at', 'resolved_at'])
            ->avg(fn (Ticket $ticket) => $ticket->created_at->diffInMinutes($ticket->resolved_at) / 60);

        return response()->json([
            'by_status' => collect(TicketStatus::cases())
                ->mapWithKeys(fn ($status) => [$status->value => (int) ($byStatus[$status->value] ?? 0)]),
            'by_priority' => collect(TicketPriority::cases())
                ->mapWithKeys(fn ($priority) => [$priority->value => (int) ($byPriority[$priority->value] ?? 0)]),
            'unassigned' => Ticket::whereNull('assigned_to')->whereIn('status', $open)->count(),
            'assigned_to_me' => Ticket::where('assigned_to', $request->user()->id)->whereIn('status', $open)->count(),
            'average_resolution_hours' => $hours === null ? null : round($hours, 1),
        ]);
    }
}
