<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGalleryImagesRequest;
use App\Http\Requests\Admin\StoreGalleryRequest;
use App\Http\Requests\Admin\UpdateGalleryImageRequest;
use App\Http\Requests\Admin\UpdateGalleryRequest;
use App\Models\Gallery;
use App\Models\GalleryCategory;
use App\Models\GalleryImage;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GalleryController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', Gallery::class);

        $galleries = Gallery::query()
            ->withCount('images')
            ->with(['coverImage:id,image_path', 'category:id,name'])
            ->orderBy('sort')
            ->get();

        $categories = GalleryCategory::query()->withCount('galleries')->ordered()->get();

        return Inertia::render('Admin/CMS/Gallery/Index', [
            'galleries' => $galleries->map(fn (Gallery $gallery) => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'category_name' => $gallery->category?->name,
                'images_count' => $gallery->images_count,
                'cover_image_url' => $gallery->coverImage ? asset("storage/{$gallery->coverImage->image_path}") : null,
                'is_active' => $gallery->is_active,
            ]),
            'categories' => $categories->map(fn (GalleryCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'subtitle' => $category->subtitle,
                'sort' => $category->sort,
                'galleries_count' => $category->galleries_count,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Gallery::class);

        return Inertia::render('Admin/CMS/Gallery/Form', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreGalleryRequest $request): RedirectResponse
    {
        $gallery = Gallery::create($request->validated());

        return redirect()->route('admin.gallery.edit', $gallery)->with('success', "\"{$gallery->title}\" created.");
    }

    public function edit(Gallery $gallery): Response
    {
        $this->authorize('update', $gallery);

        $gallery->load('images');

        return Inertia::render('Admin/CMS/Gallery/Form', [
            'gallery' => [
                'id' => $gallery->id,
                'title' => $gallery->title,
                'slug' => $gallery->slug,
                'gallery_category_id' => $gallery->gallery_category_id,
                'sort' => $gallery->sort,
                'is_active' => $gallery->is_active,
                'cover_image_id' => $gallery->cover_image_id,
                'images' => $gallery->images->map(fn (GalleryImage $image) => $this->imageData($image)),
            ],
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateGalleryRequest $request, Gallery $gallery): RedirectResponse
    {
        $gallery->update($request->validated());

        return back()->with('success', "\"{$gallery->title}\" updated.");
    }

    public function destroy(Gallery $gallery, SecureUploadService $uploads): RedirectResponse
    {
        $this->authorize('delete', $gallery);

        $paths = $gallery->images->flatMap(fn (GalleryImage $image) => [$image->image_path, $image->pair_image_path]);
        $uploads->deletePublic($paths->all());

        $gallery->delete();

        return redirect()->route('admin.gallery')->with('success', "\"{$gallery->title}\" deleted.");
    }

    public function storeImages(StoreGalleryImagesRequest $request, Gallery $gallery, SecureUploadService $uploads): RedirectResponse
    {
        $nextSort = ((int) $gallery->images()->max('sort')) + 1;

        foreach ($request->file('images') as $index => $file) {
            GalleryImage::create([
                'gallery_id' => $gallery->id,
                'image_path' => $uploads->storePublicImage($file, 'gallery'),
                'sort' => $nextSort + $index,
            ]);
        }

        return back()->with('success', 'Images uploaded.');
    }

    public function updateImage(UpdateGalleryImageRequest $request, Gallery $gallery, GalleryImage $image, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->safe()->except('pair_image');

        if ($request->hasFile('pair_image')) {
            $oldPairPath = $image->pair_image_path;
            $data['pair_image_path'] = $uploads->storePublicImage($request->file('pair_image'), 'gallery');
            $uploads->deletePublic($oldPairPath);
        }

        $image->update($data);

        return back()->with('success', 'Image updated.');
    }

    public function destroyImage(Gallery $gallery, GalleryImage $image, SecureUploadService $uploads): RedirectResponse
    {
        $this->authorize('delete', $image);

        $uploads->deletePublic([$image->image_path, $image->pair_image_path]);

        if ($gallery->cover_image_id === $image->id) {
            $gallery->update(['cover_image_id' => null]);
        }

        $image->delete();

        return back()->with('success', 'Image deleted.');
    }

    public function reorderImages(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('update', $gallery);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:gallery_images,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            GalleryImage::where('gallery_id', $gallery->id)->whereKey($id)->update(['sort' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }

    public function setCover(Request $request, Gallery $gallery): RedirectResponse
    {
        $this->authorize('update', $gallery);

        $data = $request->validate([
            'image_id' => ['required', 'integer', 'exists:gallery_images,id'],
        ]);

        abort_unless(
            GalleryImage::where('gallery_id', $gallery->id)->whereKey($data['image_id'])->exists(),
            422,
            'That image does not belong to this gallery.',
        );

        $gallery->update(['cover_image_id' => $data['image_id']]);

        return back()->with('success', 'Cover image set.');
    }

    /** @return array<int, array<string, mixed>> */
    private function categoryOptions(): array
    {
        return GalleryCategory::query()
            ->ordered()
            ->get(['id', 'name'])
            ->map(fn (GalleryCategory $category) => ['id' => $category->id, 'name' => $category->name])
            ->all();
    }

    /** @return array<string, mixed> */
    private function imageData(GalleryImage $image): array
    {
        return [
            'id' => $image->id,
            'image_url' => asset("storage/{$image->image_path}"),
            'pair_image_url' => $image->pair_image_path ? asset("storage/{$image->pair_image_path}") : null,
            'caption' => $image->caption,
            'is_before_after' => $image->is_before_after,
            'show_on_homepage' => $image->show_on_homepage,
            'sort' => $image->sort,
        ];
    }
}
