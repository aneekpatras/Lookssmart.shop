<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryCategoryRequest;
use App\Http\Requests\Admin\UpdateGalleryCategoryRequest;
use App\Models\GalleryCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Category management embedded in the Gallery Manager (`Admin/CMS/Gallery/Index.tsx`) rather than a
 * standalone page — mirrors `ServiceCategoryController`'s store/update/destroy/reorder shape.
 */
class GalleryCategoryController extends Controller
{
    public function store(StoreGalleryCategoryRequest $request): RedirectResponse
    {
        $category = GalleryCategory::create($request->validated());

        return back()->with('success', "\"{$category->name}\" category created.");
    }

    public function update(UpdateGalleryCategoryRequest $request, GalleryCategory $galleryCategory): RedirectResponse
    {
        $galleryCategory->update($request->validated());

        return back()->with('success', "\"{$galleryCategory->name}\" category updated.");
    }

    public function destroy(GalleryCategory $galleryCategory): RedirectResponse
    {
        $this->authorize('delete', $galleryCategory);

        $galleryCategory->delete();

        return back()->with('success', "\"{$galleryCategory->name}\" category deleted.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', GalleryCategory::class);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:gallery_categories,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            GalleryCategory::whereKey($id)->update(['sort' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }
}
