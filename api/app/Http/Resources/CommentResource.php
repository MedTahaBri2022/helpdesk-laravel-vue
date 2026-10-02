<?php

namespace App\Http\Resources;

use App\Models\Comment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Comment */
class CommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'is_internal' => $this->is_internal,
            'author' => [
                'id' => $this->author->id,
                'name' => $this->author->name,
                'role' => $this->author->role->value,
            ],
            'created_at' => $this->created_at,
        ];
    }
}
