<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class CommentController extends Controller
{
    public function store(StoreCommentRequest $request, Ticket $ticket): JsonResponse
    {
        Gate::authorize('comment', $ticket);

        $user = $request->user();
        $internal = $request->boolean('is_internal');

        // Internal notes are a staff tool; a customer asking for one is refused
        // rather than silently published as a public reply.
        if ($internal) {
            Gate::authorize('manage', Ticket::class);
        }

        $comment = new Comment(['body' => $request->validated('body'), 'is_internal' => $internal]);
        $comment->author()->associate($user);
        $comment->ticket()->associate($ticket);
        $comment->save();

        // A new message is activity on the ticket: it moves up in "recently updated".
        $ticket->touch();

        return (new CommentResource($comment->load('author')))
            ->response()
            ->setStatusCode(201);
    }
}
