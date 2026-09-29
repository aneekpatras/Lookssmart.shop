<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreDealRequest;
use App\Http\Requests\Admin\UpdateDealRequest;
use App\Models\Deal;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    private const SORTABLE_COLUMNS = ['title', 'starts_at', 'ends_at', 'created_at'];

    /**
     * Resolves the feature image for this save: a newly uploaded file always wins over a pasted
     * URL. `$data` is mutated in place before it's passed to Deal::create()/update().
     *
     * - New file uploaded: store it via SecureUploadService (as before), delete the old local file
     *   if one existed, and null out `stock_image_url` so a stale URL doesn't linger as an
     *   orphaned, invisible fallback.
     * - No file, but `stock_image_url` provided: drop `image_path` (and delete the old local file)
     *   so the admin's intent — switch to the pasted URL — actually takes effect, matching the same
     *   precedence rule as ServiceController::applyImage().
     * - Neither: leave both untouched (existing image, local or remote, is kept as-is).
     */
    private function applyImage(Request $request, SecureUploadService $uploads, array &$data, ?string $existingPath): void
    {
        if ($request->hasFile('image')) {
            $data['image_path'] = $uploads->storePublicImage($request->file('image'), 'deals');
            $data['stock_image_url'] = null;
            $uploads->deletePublic($existingPath);

            return;
        }

        if ($request->filled('stock_image_url') && $existingPath) {
            $data['image_path'] = null;
            $uploads->deletePublic($existingPath);
        }
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Deal::class);

        $sort = in_array($request->string('sort')->value(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->value()
            : 'created_at';

        $direction = $request->string('direction')->value() === 'asc' ? 'asc' : 'desc';

        // `services:id`/`categories:id` are eager-loaded (not queried per row) purely so the
        // quick-edit modal can prefill `service_ids`/`category_ids` without turning every
        // paginated row into two extra pivot queries.
        $deals = Deal::query()
            ->withCount('redemptions')
            ->with(['services:id', 'categories:id'])
            ->when($request->filled('search'), fn ($query) => $query->where(function ($query) use ($request) {
                $search = $request->string('search')->value();
                $query->where('title', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%");
            }))
            ->orderBy($sort, $direction)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Deals', [
            'deals' => $deals->through(fn (Deal $deal) => [
                'id' => $deal->id,
                'title' => $deal->title,
                'slug' => $deal->slug,
                'subtitle' => $deal->subtitle,
                'category_tag' => $deal->category_tag,
                'type' => $deal->type,
                'value' => $deal->value,
                'original_price' => $deal->original_price,
                'deal_price' => $deal->deal_price,
                'included_services' => $deal->included_services,
                'description' => $deal->description,
                'terms' => $deal->terms,
                'code' => $deal->code,
                'starts_at' => $deal->starts_at?->toDateTimeString(),
                'ends_at' => $deal->ends_at?->toDateTimeString(),
                'usage_limit' => $deal->usage_limit,
                'per_user_limit' => $deal->per_user_limit,
                'min_amount' => $deal->min_amount,
                'is_stackable' => $deal->is_stackable,
                'is_auto_apply' => $deal->is_auto_apply,
                'is_active' => $deal->is_active,
                'is_top_deal' => $deal->is_top_deal,
                'redemptions_count' => $deal->redemptions_count,
                'service_ids' => $deal->services->pluck('id'),
                'category_ids' => $deal->categories->pluck('id'),
                'stock_image_url' => $deal->stock_image_url,
                'image_url' => $this->displayImageUrl($deal),
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'sort' => $sort,
                'direction' => $direction,
            ],
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'base_price']),
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'categoryTags' => Deal::CATEGORY_TAGS,
        ]);
    }

    private function displayImageUrl(Deal $deal): ?string
    {
        return $deal->image_path ? asset("storage/{$deal->image_path}") : ($deal->stock_image_url ?: null);
    }

    public function create(): Response
    {
        $this->authorize('create', Deal::class);

        return Inertia::render('Admin/DealForm', [
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'base_price']),
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'categoryTags' => Deal::CATEGORY_TAGS,
        ]);
    }

    public function store(StoreDealRequest $request, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->safe()->except(['service_ids', 'category_ids', 'image']);

        $this->applyImage($request, $uploads, $data, null);

        // Drop blank rows a dynamic "add bullet" UI can leave behind rather than storing an empty
        // string as a checklist item.
        if (isset($data['included_services'])) {
            $data['included_services'] = array_values(array_filter(
                $data['included_services'],
                fn ($item) => trim((string) $item) !== '',
            ));
        }

        $deal = Deal::create($data);
        $deal->services()->sync($request->input('service_ids', []));
        $deal->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.deals')->with('success', "\"{$deal->title}\" created.");
    }

    public function edit(Deal $deal): Response
    {
        $this->authorize('update', $deal);

        return Inertia::render('Admin/DealForm', [
            'deal' => [
                ...$deal->only([
                    'id', 'title', 'slug', 'subtitle', 'category_tag', 'type', 'value',
                    'original_price', 'deal_price', 'included_services', 'description', 'terms',
                    'code', 'usage_limit', 'per_user_limit', 'min_amount', 'is_stackable',
                    'is_auto_apply', 'is_active', 'is_top_deal',
                ]),
                'starts_at' => $deal->starts_at?->toDateTimeString(),
                'ends_at' => $deal->ends_at?->toDateTimeString(),
                'stock_image_url' => $deal->stock_image_url,
                'image_url' => $this->displayImageUrl($deal),
                'service_ids' => $deal->services()->pluck('services.id'),
                'category_ids' => $deal->categories()->pluck('service_categories.id'),
            ],
            'services' => Service::query()->orderBy('name')->get(['id', 'name', 'base_price']),
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
            'categoryTags' => Deal::CATEGORY_TAGS,
        ]);
    }

    public function update(UpdateDealRequest $request, Deal $deal, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->safe()->except(['service_ids', 'category_ids', 'image']);

        $this->applyImage($request, $uploads, $data, $deal->image_path);

        if (isset($data['included_services'])) {
            $data['included_services'] = array_values(array_filter(
                $data['included_services'],
                fn ($item) => trim((string) $item) !== '',
            ));
        }

        $deal->update($data);
        $deal->services()->sync($request->input('service_ids', []));
        $deal->categories()->sync($request->input('category_ids', []));

        return redirect()->route('admin.deals')->with('success', "\"{$deal->title}\" updated.");
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $this->authorize('delete', $deal);

        // Soft-delete only (Deal uses SoftDeletes) — the image is intentionally left on disk. A
        // hard purge command already exists for genuinely permanent deletion, per the same
        // reasoning as every other soft-deletable model with an uploaded image in this app.
        $deal->delete();

        return back()->with('success', "\"{$deal->title}\" deleted.");
    }
}
