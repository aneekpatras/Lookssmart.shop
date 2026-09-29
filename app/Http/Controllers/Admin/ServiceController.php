<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ServicesExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreServiceRequest;
use App\Http\Requests\Admin\UpdateServiceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Staff;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileUnacceptableForCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServiceController extends Controller
{
    private const SORTABLE_COLUMNS = ['name', 'base_price', 'duration_min', 'sort', 'created_at'];

    /** See ServiceCategoryController::attachImage() for why this catch exists. */
    private function attachImage(Service $service, Request $request): void
    {
        try {
            $service->addMediaFromRequest('image')->toMediaCollection('image');
        } catch (FileUnacceptableForCollection) {
            throw ValidationException::withMessages(['image' => 'That file is not a valid image.']);
        }
    }

    /**
     * Resolves the image for this save: an uploaded file always wins over a pasted URL.
     *
     * - New file uploaded: attach it. Callers must also null out `stock_image_url` in the data
     *   they save (see the `hasFile('image')` checks in store()/update()) so a stale URL from a
     *   previous save doesn't linger as an orphaned, invisible fallback.
     * - No file, but `stock_image_url` provided/changed: drop any existing uploaded media so the
     *   admin's intent (switch to the pasted URL) actually takes effect — otherwise the old upload
     *   would keep winning via `getFirstMediaUrl()` precedence and the URL would silently do nothing.
     * - Neither: leave both untouched.
     */
    private function applyImage(Service $service, Request $request): void
    {
        if ($request->hasFile('image')) {
            $this->attachImage($service, $request);

            return;
        }

        if ($request->filled('stock_image_url')) {
            $service->clearMediaCollection('image');
        }
    }

    private function displayImageUrl(Service $service, string $conversion = ''): ?string
    {
        return $service->getFirstMediaUrl('image', $conversion) ?: ($service->stock_image_url ?: null);
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Service::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'sort';

        $direction = $request->string('direction')->value() === 'desc' ? 'desc' : 'asc';

        // `staff:id` is eager-loaded (not queried per row) purely so the quick-edit modal can
        // prefill `staff_ids` without turning every paginated row into its own pivot query.
        $services = Service::query()
            ->with(['category:id,name', 'staff:id'])
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->string('search')->value() . '%'))
            ->when($request->filled('category_id'), fn ($query) => $query->where('service_category_id', $request->integer('category_id')))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        // `staff_ids` and the rest of the editable-but-not-listed fields (description, buffer_min,
        // faq, seo_*) ride along on every row so the admin list's quick-edit modal has everything it
        // needs to open instantly — no second request per row. 15 rows/page keeps this cheap.
        return Inertia::render('Admin/Services', [
            'services' => $services->through(fn (Service $service) => [
                'id' => $service->id,
                'service_category_id' => $service->service_category_id,
                'sku' => $service->sku,
                'name' => $service->name,
                'slug' => $service->slug,
                'description' => $service->description,
                'category' => $service->category?->name,
                'duration_min' => $service->duration_min,
                'buffer_min' => $service->buffer_min,
                'base_price' => $service->base_price,
                'is_featured' => $service->is_featured,
                'is_active' => $service->is_active,
                'sort' => $service->sort,
                'seo_title' => $service->seo_title,
                'seo_description' => $service->seo_description,
                'faq' => $service->faq,
                'staff_ids' => $service->staff->pluck('id'),
                'stock_image_url' => $service->stock_image_url,
                'image_url' => $this->displayImageUrl($service, 'thumb'),
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'category_id' => $request->integer('category_id') ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'staff' => Staff::query()->with('user:id,name')->get()->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
            ]),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Service::class);

        return Inertia::render('Admin/ServiceForm', [
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'staff' => Staff::query()->with('user:id,name')->get()->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
            ]),
        ]);
    }

    public function store(StoreServiceRequest $request, HtmlSanitizerService $sanitizer): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'staff_ids']);

        if (isset($data['description'])) {
            $data['description'] = $sanitizer->clean($data['description']);
        }

        if ($request->hasFile('image')) {
            $data['stock_image_url'] = null;
        }

        $service = Service::create($data);

        if ($request->filled('staff_ids')) {
            $service->staff()->sync($request->input('staff_ids'));
        }

        $this->applyImage($service, $request);

        return redirect()->route('admin.services')->with('success', "\"{$service->name}\" created.");
    }

    public function edit(Service $service): Response
    {
        $this->authorize('update', $service);

        return Inertia::render('Admin/ServiceForm', [
            'service' => [
                ...$service->only([
                    'id', 'service_category_id', 'sku', 'name', 'slug', 'description',
                    'duration_min', 'buffer_min', 'base_price', 'is_featured', 'is_active',
                    'sort', 'seo_title', 'seo_description', 'faq', 'stock_image_url',
                ]),
                'staff_ids' => $service->staff()->pluck('staff.id'),
                'image_url' => $this->displayImageUrl($service),
            ],
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'staff' => Staff::query()->with('user:id,name')->get()->map(fn (Staff $staff) => [
                'id' => $staff->id,
                'name' => $staff->user?->name,
            ]),
        ]);
    }

    public function update(UpdateServiceRequest $request, Service $service, HtmlSanitizerService $sanitizer): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'staff_ids']);

        if (isset($data['description'])) {
            $data['description'] = $sanitizer->clean($data['description']);
        }

        if ($request->hasFile('image')) {
            $data['stock_image_url'] = null;
        }

        $service->update($data);
        $service->staff()->sync($request->input('staff_ids', []));

        $this->applyImage($service, $request);

        return redirect()->route('admin.services')->with('success', "\"{$service->name}\" updated.");
    }

    public function destroy(Service $service): RedirectResponse
    {
        $this->authorize('delete', $service);

        $service->delete();

        return back()->with('success', "\"{$service->name}\" deleted.");
    }

    /** Full catalog export — see ServicesExport for the column set and re-import round-trip. */
    public function export(): BinaryFileResponse
    {
        $this->authorize('viewAny', Service::class);

        return Excel::download(new ServicesExport, 'services-' . now()->format('Y-m-d') . '.xlsx');
    }
}
