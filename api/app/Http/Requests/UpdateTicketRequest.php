<?php

namespace App\Http\Requests;

use App\Enums\Role;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Describes valid input only. Who may change what is decided by the
 * TicketPolicy and the status workflow, in the controller.
 */
class UpdateTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['sometimes', 'string', 'min:5', 'max:150'],
            'description' => ['sometimes', 'string', 'min:10', 'max:5000'],
            'status' => ['sometimes', Rule::enum(TicketStatus::class)],
            'priority' => ['sometimes', Rule::enum(TicketPriority::class)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            // Only a member of staff can be in charge of a ticket.
            'assigned_to' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('users', 'id')->whereIn('role', [Role::Agent->value, Role::Admin->value]),
            ],
        ];
    }
}
