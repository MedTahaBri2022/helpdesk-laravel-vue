<?php

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject', 'description', 'status', 'priority', 'category_id', 'assigned_to', 'resolved_at',
    ];

    protected $attributes = [
        'status' => 'open',
        'priority' => 'medium',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'resolved_at' => 'datetime',
        ];
    }

    /** Human-friendly reference shown to customers, e.g. HD-000042. */
    protected function reference(): Attribute
    {
        return Attribute::get(fn () => 'HD-'.str_pad((string) $this->id, 6, '0', STR_PAD_LEFT));
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Restricts a query to what the user is allowed to see: every ticket for
     * staff, only their own for a customer.
     *
     * @param  Builder<Ticket>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isStaff()) {
            $query->where('user_id', $user->id);
        }
    }
}
