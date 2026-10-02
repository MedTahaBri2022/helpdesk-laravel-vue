<?php

namespace Database\Seeders;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo data for local development. The three accounts below share the
 * password "password"; they must never exist in a real environment.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Alia Admin', 'email' => 'admin@helpdesk.example']);
        $agent = User::factory()->agent()->create(['name' => 'Adam Agent', 'email' => 'agent@helpdesk.example']);
        $otherAgent = User::factory()->agent()->create(['name' => 'Nora Support']);
        $customer = User::factory()->create(['name' => 'Camille Customer', 'email' => 'customer@helpdesk.example']);
        $customers = User::factory(6)->create()->push($customer);

        $categories = collect(['Account', 'Billing', 'Technical issue', 'Feature request', 'Other'])
            ->map(fn (string $name) => Category::create(['name' => $name]));

        $statuses = [
            TicketStatus::Open, TicketStatus::Open, TicketStatus::InProgress,
            TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Closed,
        ];

        foreach (range(1, 42) as $index) {
            $status = $statuses[$index % count($statuses)];
            $assignee = $status === TicketStatus::Open && $index % 2 === 0
                ? null
                : [$agent, $otherAgent, $admin][$index % 3];
            $created = now()->subDays(random_int(0, 30))->subHours(random_int(0, 23));

            $ticket = Ticket::factory()
                ->status($status)
                ->priority(TicketPriority::cases()[$index % 4])
                ->create([
                    'category_id' => $categories[$index % $categories->count()]->id,
                    'user_id' => $customers[$index % $customers->count()]->id,
                    'assigned_to' => $assignee?->id,
                    'created_at' => $created,
                    'updated_at' => $created,
                    'resolved_at' => $status === TicketStatus::Resolved || $status === TicketStatus::Closed
                        ? $created->copy()->addHours(random_int(2, 72))
                        : null,
                ]);

            if ($assignee) {
                Comment::factory()->create(['ticket_id' => $ticket->id, 'user_id' => $assignee->id]);
                Comment::factory()->internal()->create(['ticket_id' => $ticket->id, 'user_id' => $assignee->id]);
            }
        }
    }
}
