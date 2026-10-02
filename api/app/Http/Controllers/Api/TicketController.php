<?php

namespace App\Http\Controllers\Api;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTicketRequest;
use App\Http\Requests\TicketIndexRequest;
use App\Http\Requests\UpdateTicketRequest;
use App\Http\Resources\TicketResource;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class TicketController extends Controller
{
    /** Fields a customer may never change: they belong to the support team. */
    private const STAFF_ONLY = ['priority', 'category_id', 'assigned_to'];

    public function index(TicketIndexRequest $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $filters = $request->validated();

        $tickets = Ticket::query()
            ->visibleTo($user)
            // Three joins up front instead of three queries per row.
            ->with(['category', 'requester', 'assignee'])
            ->withCount(['comments' => fn ($query) => $query->visibleTo($user)])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->where('category_id', $id))
            ->when(($filters['assigned'] ?? null) === 'me', fn ($query) => $query->where('assigned_to', $user->id))
            ->when(($filters['assigned'] ?? null) === 'none', fn ($query) => $query->whereNull('assigned_to'))
            ->when($filters['q'] ?? null, function ($query, string $term) {
                // The search term is data, never a pattern: LIKE wildcards are
                // escaped. "!" is declared as the escape character because the
                // default one differs between MySQL and SQLite.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';
                $query->where(fn ($inner) => $inner
                    ->whereRaw("subject LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$like]));
            });

        match ($filters['sort'] ?? 'newest') {
            'oldest' => $tickets->oldest(),
            'priority' => $tickets->orderByRaw(
                "CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END"
            )->latest(),
            default => $tickets->latest(),
        };

        // A second key keeps the order stable from one page to the next.
        return TicketResource::collection(
            $tickets->orderBy('id')->paginate($filters['per_page'] ?? 10)
        );
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $request->user();

        // A customer describes the problem; its priority is assessed by staff.
        if (! $user->isStaff()) {
            unset($data['priority']);
        }

        $ticket = new Ticket($data);
        $ticket->requester()->associate($user);
        $ticket->save();

        return (new TicketResource($this->load($ticket, $user)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Ticket $ticket): TicketResource
    {
        Gate::authorize('view', $ticket);

        return new TicketResource($this->load($ticket, request()->user()));
    }

    public function update(UpdateTicketRequest $request, Ticket $ticket): TicketResource
    {
        Gate::authorize('update', $ticket);

        $user = $request->user();
        $data = $request->validated();

        if (array_intersect_key($data, array_flip(self::STAFF_ONLY))) {
            Gate::authorize('manage', Ticket::class);
        }

        if (! $user->isStaff()
            && (isset($data['subject']) || isset($data['description']))
            && $ticket->status !== TicketStatus::Open) {
            throw ValidationException::withMessages([
                'subject' => ['A ticket can only be edited while it is open.'],
            ]);
        }

        if (isset($data['status'])) {
            $data += $this->changeStatus($ticket, TicketStatus::from($data['status']), $user);
        }

        $ticket->update($data);

        return new TicketResource($this->load($ticket, $user));
    }

    /**
     * Checks the move against the workflow and returns the side effects on
     * `resolved_at`.
     *
     * @return array<string, mixed>
     */
    private function changeStatus(Ticket $ticket, TicketStatus $target, User $user): array
    {
        if ($target === $ticket->status) {
            return [];
        }

        if (! $ticket->status->canMoveTo($target, $user->role)) {
            throw ValidationException::withMessages([
                'status' => ["A {$ticket->status->value} ticket cannot be moved to {$target->value}."],
            ]);
        }

        return match ($target) {
            TicketStatus::Resolved => ['resolved_at' => now()],
            // Reopened: the previous resolution no longer counts.
            TicketStatus::Open, TicketStatus::InProgress => ['resolved_at' => null],
            TicketStatus::Closed => [],
        };
    }

    private function load(Ticket $ticket, User $user): Ticket
    {
        return $ticket->load([
            'category',
            'requester',
            'assignee',
            'comments' => fn ($query) => $query->visibleTo($user)->with('author')->oldest(),
        ])->loadCount(['comments' => fn ($query) => $query->visibleTo($user)]);
    }
}
