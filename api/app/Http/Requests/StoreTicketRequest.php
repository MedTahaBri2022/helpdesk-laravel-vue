<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'min:5', 'max:150'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'priority' => ['nullable', Rule::enum(TicketPriority::class)],
        ];
    }
}
