<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class UserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email',
            'age' => 'nullable|integer',
            'status' => 'in:active,inactive',
        ];
    }

    public function authorize(): bool
    {
        return true;
    }
}
