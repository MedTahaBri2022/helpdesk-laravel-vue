<?php

namespace Tests\Feature;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private User $customer;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create();
        $this->agent = User::factory()->agent()->create();
    }

    public function test_a_customer_opens_a_ticket_but_cannot_choose_its_priority(): void
    {
        Sanctum::actingAs($this->customer);
        $category = Category::factory()->create();

        $response = $this->postJson('/api/tickets', [
            'subject' => 'Cannot sign in to my account',
            'description' => 'The password reset email never arrives.',
            'category_id' => $category->id,
            'priority' => 'urgent',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.priority', 'medium')
            ->assertJsonPath('data.requester.id', $this->customer->id)
            ->assertJsonPath('data.reference', 'HD-'.str_pad((string) $response->json('data.id'), 6, '0', STR_PAD_LEFT));
    }

    public function test_creating_a_ticket_validates_its_input(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/tickets', ['subject' => 'Hi', 'description' => 'short', 'category_id' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['subject', 'description', 'category_id']);
    }

    public function test_a_customer_only_sees_their_own_tickets(): void
    {
        $mine = Ticket::factory()->for($this->customer, 'requester')->create();
        $theirs = Ticket::factory()->create();

        Sanctum::actingAs($this->customer);

        $this->getJson('/api/tickets')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
        $this->getJson("/api/tickets/{$theirs->id}")->assertForbidden();
        $this->patchJson("/api/tickets/{$theirs->id}", ['status' => 'closed'])->assertForbidden();
    }

    public function test_staff_see_every_ticket(): void
    {
        Ticket::factory(3)->create();
        Sanctum::actingAs($this->agent);

        $this->getJson('/api/tickets')->assertOk()->assertJsonPath('meta.total', 3);
    }

    public function test_the_list_filters_searches_sorts_and_paginates(): void
    {
        $billing = Category::factory()->create();
        Ticket::factory()->priority(TicketPriority::Urgent)->create([
            'subject' => 'Invoice shows the wrong amount', 'category_id' => $billing->id,
        ]);
        Ticket::factory()->status(TicketStatus::Resolved)->create(['subject' => 'Invoice missing']);
        Ticket::factory()->assignedTo($this->agent)->create(['subject' => 'Printer is offline']);
        Ticket::factory(12)->create(['subject' => 'Something else entirely']);

        Sanctum::actingAs($this->agent);

        $this->getJson('/api/tickets?q=invoice')->assertJsonPath('meta.total', 2);
        $this->getJson('/api/tickets?q=invoice&status=resolved')->assertJsonPath('meta.total', 1);
        $this->getJson("/api/tickets?category_id={$billing->id}")->assertJsonPath('meta.total', 1);
        $this->getJson('/api/tickets?assigned=me')->assertJsonPath('data.0.subject', 'Printer is offline');
        $this->getJson('/api/tickets?assigned=none')->assertJsonPath('meta.total', 14);
        $this->getJson('/api/tickets?sort=priority')->assertJsonPath('data.0.priority', 'urgent');
        $this->getJson('/api/tickets?per_page=5&page=2')
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_a_search_term_is_not_interpreted_as_a_pattern(): void
    {
        Ticket::factory()->create(['subject' => 'Discount of 100% applied twice']);
        Ticket::factory()->create(['subject' => 'Completely unrelated']);
        Sanctum::actingAs($this->agent);

        // "%" would match everything if it were passed to LIKE unescaped.
        $this->getJson('/api/tickets?q=%25')->assertJsonPath('meta.total', 1);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        Sanctum::actingAs($this->agent);

        $this->getJson('/api/tickets?status=lost&per_page=500&sort=random')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page', 'sort']);
    }

    public function test_the_list_does_not_run_one_query_per_ticket(): void
    {
        Ticket::factory(10)->create();
        Sanctum::actingAs($this->agent);

        DB::enableQueryLog();
        $this->getJson('/api/tickets')->assertOk();
        $queries = count(DB::getQueryLog());

        // count + page + 3 eager loads (and the token lookup), not 10 x 3.
        $this->assertLessThanOrEqual(8, $queries);
    }

    public function test_an_agent_takes_resolves_and_reopens_a_ticket(): void
    {
        $ticket = Ticket::factory()->create();
        Sanctum::actingAs($this->agent);

        $this->patchJson("/api/tickets/{$ticket->id}", [
            'assigned_to' => $this->agent->id, 'status' => 'in_progress', 'priority' => 'high',
        ])->assertOk()
            ->assertJsonPath('data.assignee.id', $this->agent->id)
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.priority', 'high');

        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'resolved'])->assertOk();
        $this->assertNotNull($ticket->fresh()->resolved_at);

        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'in_progress'])->assertOk();
        $this->assertNull($ticket->fresh()->resolved_at);
    }

    public function test_a_ticket_can_only_be_assigned_to_staff(): void
    {
        $ticket = Ticket::factory()->create();
        Sanctum::actingAs($this->agent);

        $this->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => $this->customer->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assigned_to');
    }

    public function test_a_customer_cannot_change_staff_only_fields(): void
    {
        $ticket = Ticket::factory()->for($this->customer, 'requester')->create();
        Sanctum::actingAs($this->customer);

        $this->patchJson("/api/tickets/{$ticket->id}", ['priority' => 'urgent'])->assertForbidden();
        $this->patchJson("/api/tickets/{$ticket->id}", ['assigned_to' => $this->agent->id])->assertForbidden();
        $this->assertSame(TicketPriority::Medium, $ticket->fresh()->priority);
    }

    public function test_a_customer_edits_a_ticket_only_while_it_is_open(): void
    {
        $ticket = Ticket::factory()->for($this->customer, 'requester')->create();
        Sanctum::actingAs($this->customer);

        $this->patchJson("/api/tickets/{$ticket->id}", ['subject' => 'A clearer subject line'])->assertOk();

        $ticket->update(['status' => TicketStatus::InProgress]);
        $this->patchJson("/api/tickets/{$ticket->id}", ['subject' => 'Changing it again'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('subject');
    }

    public function test_the_workflow_limits_what_a_customer_can_do(): void
    {
        $ticket = Ticket::factory()->for($this->customer, 'requester')->status(TicketStatus::Resolved)->create();
        Sanctum::actingAs($this->customer);

        // Listed by the API so the client knows which buttons to show.
        $this->getJson("/api/tickets/{$ticket->id}")->assertJsonPath('data.allowed_statuses', ['open', 'closed']);

        // A customer cannot declare the work in progress...
        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'in_progress'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');

        // ...but can say the problem is not fixed, or confirm that it is.
        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'open'])->assertOk();
        $this->assertNull($ticket->fresh()->resolved_at);

        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'closed'])->assertOk();
        $this->patchJson("/api/tickets/{$ticket->id}", ['status' => 'open'])->assertUnprocessable();
    }

    public function test_internal_notes_are_hidden_from_customers_everywhere(): void
    {
        $ticket = Ticket::factory()->for($this->customer, 'requester')->create();
        Comment::factory()->for($ticket)->for($this->agent, 'author')->create(['body' => 'Public reply']);
        Comment::factory()->for($ticket)->for($this->agent, 'author')->internal()->create(['body' => 'Internal note']);

        Sanctum::actingAs($this->customer);
        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertJsonCount(1, 'data.comments')
            ->assertJsonPath('data.comments.0.body', 'Public reply')
            ->assertJsonPath('data.comments_count', 1);
        $this->getJson('/api/tickets')->assertJsonPath('data.0.comments_count', 1);

        Sanctum::actingAs($this->agent);
        $this->getJson("/api/tickets/{$ticket->id}")
            ->assertJsonCount(2, 'data.comments')
            ->assertJsonPath('data.comments_count', 2);
    }

    public function test_comments_and_who_may_write_internal_notes(): void
    {
        $ticket = Ticket::factory()->for($this->customer, 'requester')->create();

        Sanctum::actingAs($this->customer);
        $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'Any news?'])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $this->customer->id)
            ->assertJsonPath('data.is_internal', false);
        $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'Sneaky', 'is_internal' => true])
            ->assertForbidden();
        $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => ''])->assertUnprocessable();

        Sanctum::actingAs($this->agent);
        $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'Checking logs', 'is_internal' => true])
            ->assertCreated()
            ->assertJsonPath('data.is_internal', true);

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/tickets/{$ticket->id}/comments", ['body' => 'Not my ticket'])->assertForbidden();
    }

    public function test_dashboard_statistics_are_for_staff(): void
    {
        Ticket::factory(2)->create();
        Ticket::factory()->assignedTo($this->agent)->status(TicketStatus::InProgress)->create();
        Ticket::factory()->status(TicketStatus::Resolved)->create([
            'created_at' => now()->subHours(10), 'resolved_at' => now()->subHours(4),
        ]);

        Sanctum::actingAs($this->customer);
        $this->getJson('/api/stats')->assertForbidden();
        $this->getJson('/api/agents')->assertForbidden();

        Sanctum::actingAs($this->agent);
        $this->getJson('/api/stats')->assertOk()->assertExactJson([
            'by_status' => ['open' => 2, 'in_progress' => 1, 'resolved' => 1, 'closed' => 0],
            'by_priority' => ['low' => 0, 'medium' => 4, 'high' => 0, 'urgent' => 0],
            'unassigned' => 2,
            'assigned_to_me' => 1,
            'average_resolution_hours' => 6,
        ]);
        $this->getJson('/api/agents')->assertOk()->assertJsonCount(1, 'data');
    }
}
