<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** Staff see every ticket; a customer only the ones they opened. */
    public function view(User $user, Ticket $ticket): bool
    {
        return $user->isStaff() || $ticket->user_id === $user->id;
    }

    public function update(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Priority, category and assignment are decided by staff. */
    public function manage(User $user): bool
    {
        return $user->isStaff();
    }
}
