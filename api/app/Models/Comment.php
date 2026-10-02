<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Comment extends Model
{
    use HasFactory;

    protected $fillable = ['body', 'is_internal'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Ticket, $this> */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Internal notes never reach a customer, whatever endpoint is used.
     *
     * @param  Builder<Comment>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isStaff()) {
            $query->where('is_internal', false);
        }
    }
}
