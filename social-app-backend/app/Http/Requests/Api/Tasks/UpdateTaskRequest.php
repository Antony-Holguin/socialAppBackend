<?php

namespace App\Http\Requests\Api\Tasks;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:2', 'max:200'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
