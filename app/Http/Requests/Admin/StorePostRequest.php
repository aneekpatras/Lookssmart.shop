<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\BaseFormRequest;
use App\Models\Post;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePostRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Post::class);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'post_category_id' => ['nullable', 'integer', 'exists:post_categories,id'],
            'tags' => ['nullable', 'string', 'max:1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:1000'],
            'status' => ['required', Rule::in(['draft', 'scheduled', 'published'])],
            'scheduled_at' => ['nullable', 'date', 'required_if:status,scheduled'],
            'cover_image' => ['nullable', 'image', 'mimes:jpeg,png,webp,gif', 'max:5120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'slug' => $this->input('slug') ?: Str::slug((string) $this->input('title')),
        ]);
    }
}
