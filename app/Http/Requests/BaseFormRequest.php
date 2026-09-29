<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared normalization for every app FormRequest (Brief §5 item 4). Concrete requests should extend
 * this instead of Illuminate\Foundation\Http\FormRequest directly.
 */
abstract class BaseFormRequest extends FormRequest
{
    /**
     * Trims all string inputs and converts empty strings to null before validation runs, so
     * validation rules (e.g. `nullable`) behave consistently regardless of what a form actually sent.
     */
    protected function prepareForValidation(): void
    {
        $normalized = collect($this->all())->map(function ($value) {
            if (! is_string($value)) {
                return $value;
            }

            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        })->all();

        $this->merge($normalized);
    }
}
