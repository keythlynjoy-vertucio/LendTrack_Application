<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CloseLoanRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'close_reason' => [
                'required',
                Rule::in([
                    'fully_paid_not_updated',
                    'settled_outside',
                    'cancelled_terminated',
                    'written_off',
                ]),
            ],
            'close_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'close_reason.required' => 'Please select a close reason.',
            'close_reason.in' => 'The selected close reason is invalid.',
            'close_remarks.max' => 'Remarks must not exceed 1000 characters.',
        ];
    }
}
