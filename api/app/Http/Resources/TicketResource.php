<?php

namespace App\Http\Resources;

use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Ticket */
class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'category' => ['id' => $this->category->id, 'name' => $this->category->name],
            'requester' => ['id' => $this->requester->id, 'name' => $this->requester->name],
            'assignee' => $this->assignee
                ? ['id' => $this->assignee->id, 'name' => $this->assignee->name]
                : null,
            'comments_count' => $this->whenCounted('comments', default: 0),
            // The workflow is decided by the API: the client only displays
            // the moves this user is allowed to make.
            'allowed_statuses' => array_map(
                fn ($status) => $status->value,
                $this->status->allowedFor($request->user()->role),
            ),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'resolved_at' => $this->resolved_at,
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
        ];
    }
}
