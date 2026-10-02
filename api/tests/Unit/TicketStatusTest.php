<?php

namespace Tests\Unit;

use App\Enums\Role;
use App\Enums\TicketStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TicketStatusTest extends TestCase
{
    /**
     * @return iterable<string, array{TicketStatus, Role, list<TicketStatus>}>
     */
    public static function workflow(): iterable
    {
        yield 'agent, open' => [TicketStatus::Open, Role::Agent, [TicketStatus::InProgress, TicketStatus::Resolved, TicketStatus::Closed]];
        yield 'agent, resolved' => [TicketStatus::Resolved, Role::Agent, [TicketStatus::InProgress, TicketStatus::Closed]];
        yield 'admin, closed' => [TicketStatus::Closed, Role::Admin, [TicketStatus::Open]];
        yield 'customer, open' => [TicketStatus::Open, Role::Customer, [TicketStatus::Closed]];
        yield 'customer, resolved' => [TicketStatus::Resolved, Role::Customer, [TicketStatus::Open, TicketStatus::Closed]];
        yield 'customer, closed' => [TicketStatus::Closed, Role::Customer, []];
    }

    /**
     * @param  list<TicketStatus>  $expected
     */
    #[DataProvider('workflow')]
    public function test_allowed_transitions(TicketStatus $from, Role $role, array $expected): void
    {
        $this->assertSame($expected, $from->allowedFor($role));
    }

    public function test_a_status_never_transitions_to_itself(): void
    {
        foreach (TicketStatus::cases() as $status) {
            foreach (Role::cases() as $role) {
                $this->assertFalse($status->canMoveTo($status, $role));
            }
        }
    }
}
