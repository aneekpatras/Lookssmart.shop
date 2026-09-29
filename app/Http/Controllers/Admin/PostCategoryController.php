<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePostCategoryRequest;
use App\Models\PostCategory;
use Illuminate\Http\RedirectResponse;

/**
 * Lightweight category management for the blog admin (Phase 10 sub-step 2) — a create/delete panel
 * on the Blog index rather than a full dedicated CRUD page, per the approved scope. Tags have no
 * equivalent controller: they're free-text on the post form and upserted by `BlogController`.
 */
class PostCategoryController extends Controller
{
    public function store(StorePostCategoryRequest $request): RedirectResponse
    {
        $category = PostCategory::create($request->validated());

        return back()->with('success', "\"{$category->name}\" category created.");
    }

    public function destroy(PostCategory $postCategory): RedirectResponse
    {
        $this->authorize('delete', $postCategory);

        $postCategory->delete();

        return back()->with('success', "\"{$postCategory->name}\" category deleted.");
    }
}
