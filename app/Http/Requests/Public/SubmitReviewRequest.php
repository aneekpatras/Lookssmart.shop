<?php

namespace App\Http\Requests\Public;

use App\Http\Requests\BaseFormRequest;
use App\Models\Review;
use Illuminate\Validation\Rule;

class SubmitReviewRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `name` is always optional — an authenticated visitor's real account name is used automatically
     * (see `PublicWebsiteController::submitReview()`), and a guest who leaves it blank is credited as
     * "Verified Guest" rather than being forced to type something. Honeypot/time-trap fields mirror
     * `SubmitLeadRequest`'s contract exactly.
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'category' => ['required', 'string', Rule::in(Review::WRITE_REVIEW_CATEGORIES)],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
            'rendered_at' => ['required', 'integer'],
        ];
    }
}
