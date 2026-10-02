<?php

namespace Database\Factories;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subject' => rtrim(fake()->sentence(6), '.'),
            'description' => fake()->paragraph(3),
            'status' => TicketStatus::Open,
            'priority' => TicketPriority::Medium,
            'category_id' => Category::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => [
            'status' => $status,
            'resolved_at' => $status === TicketStatus::Resolved || $status === TicketStatus::Closed ? now() : null,
        ]);
    }

    public function priority(TicketPriority $priority): static
    {
        return $this->state(['priority' => $priority]);
    }

    public function assignedTo(User $agent): static
    {
        return $this->state(['assigned_to' => $agent->id]);
    }
}
