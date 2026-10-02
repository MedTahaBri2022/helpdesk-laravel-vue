<?php

namespace App\Enums;

enum TicketStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * The workflow, in one place: which statuses a ticket may move to from
     * this one, depending on who asks.
     *
     * Staff drive the ticket; the customer can cancel it, confirm that a
     * resolved ticket is fixed (close) or say it is not (reopen).
     *
     * @return list<self>
     */
    public function allowedFor(Role $role): array
    {
        if ($role->isStaff()) {
            return match ($this) {
                self::Open => [self::InProgress, self::Resolved, self::Closed],
                self::InProgress => [self::Open, self::Resolved, self::Closed],
                self::Resolved => [self::InProgress, self::Closed],
                self::Closed => [self::Open],
            };
        }

        return match ($this) {
            self::Open, self::InProgress => [self::Closed],
            self::Resolved => [self::Open, self::Closed],
            self::Closed => [],
        };
    }

    public function canMoveTo(self $target, Role $role): bool
    {
        return in_array($target, $this->allowedFor($role), true);
    }
}
