<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceCategoryRequest;
use App\Http\Requests\Admin\UpdateServiceCategoryRequest;
use App\Models\ServiceCategory;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;

class ServiceCategoryController extends Controller
{
    private const SORTABLE_COLUMNS = ['name', 'sort', 'created_at'];

    /**
     * The FormRequest's own `image`/`mimes` rule is the primary gate; this is defense-in-depth for
     * the case where a spoofed extension passes Laravel's validation rule but the real, server-sniffed
     * MIME still fails medialibrary's own `acceptsMimeTypes()` allowlist (Brief §5 rigor).
     */
    private function attachImage(ServiceCategory $category, Request $request): void
    {
        try {
            $category->addMediaFromRequest('image')->toMediaCollection('image');
        } catch (FileUnacceptableForCollection) {
            throw ValidationException::withMessages(['image' => 'That file is not a valid image.']);
        }
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServiceCategory::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'sort';

        $direction = $request->string('direction')->value() === 'desc' ? 'desc' : 'asc';

        $categories = ServiceCategory::query()
            ->withCount('services')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->string('search')->value() . '%'))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/ServiceCategories', [
            'categories' => $categories->through(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'sort' => $category->sort,
                'is_active' => $category->is_active,
                'services_count' => $category->services_count,
                'image_url' => $category->getFirstMediaUrl('image', 'thumb') ?: null,
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function store(StoreServiceCategoryRequest $request, HtmlSanitizerService $sanitizer): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if (isset($data['seo_description'])) {
            $data['seo_description'] = $sanitizer->clean($data['seo_description']);
        }

        $category = ServiceCategory::create($data);

        if ($request->hasFile('image')) {
            $this->attachImage($category, $request);
        }

        return back()->with('success', "\"{$category->name}\" created.");
    }

    public function update(UpdateServiceCategoryRequest $request, ServiceCategory $serviceCategory, HtmlSanitizerService $sanitizer): RedirectResponse
    {
        $data = $request->safe()->except('image');

        if (isset($data['seo_description'])) {
            $data['seo_description'] = $sanitizer->clean($data['seo_description']);
        }

        $serviceCategory->update($data);

        if ($request->hasFile('image')) {
            $this->attachImage($serviceCategory, $request);
        }

        return back()->with('success', "\"{$serviceCategory->name}\" updated.");
    }

    public function destroy(ServiceCategory $serviceCategory): RedirectResponse
    {
        $this->authorize('delete', $serviceCategory);

        $serviceCategory->delete();

        return back()->with('success', "\"{$serviceCategory->name}\" deleted.");
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', ServiceCategory::class);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:service_categories,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            ServiceCategory::whereKey($id)->update(['sort' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }
}
