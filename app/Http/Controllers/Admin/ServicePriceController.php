<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkAdjustServicePricesRequest;
use App\Http\Requests\Admin\StoreServicePriceRequest;
use App\Http\Requests\Admin\UpdateServicePriceRequest;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServicePrice;
use App\Models\ServicePriceHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ServicePriceController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ServicePrice::class);

        $services = Service::query()
            ->with(['category:id,name', 'prices' => fn ($query) => $query->orderBy('price_list')->orderByDesc('effective_from')])
            ->when($request->filled('category_id'), fn ($query) => $query->where('service_category_id', $request->integer('category_id')))
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%' . $request->string('search')->value() . '%'))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Admin/Pricing', [
            'services' => $services->through(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category?->name,
                'base_price' => $service->base_price,
                'prices' => $service->prices->map(fn (ServicePrice $price) => [
                    'id' => $price->id,
                    'price_list' => $price->price_list,
                    'price' => $price->price,
                    'effective_from' => $price->effective_from?->toDateString(),
                    'effective_to' => $price->effective_to?->toDateString(),
                ]),
            ]),
            'filters' => [
                'search' => $request->string('search')->value() ?: null,
                'category_id' => $request->integer('category_id') ?: null,
            ],
            'categories' => ServiceCategory::query()->orderBy('sort')->get(['id', 'name']),
        ]);
    }

    public function store(StoreServicePriceRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $price = DB::transaction(function () use ($data, $request) {
            $price = ServicePrice::create($data);

            ServicePriceHistory::create([
                'service_id' => $price->service_id,
                'service_price_id' => $price->id,
                'price_list' => $price->price_list,
                'old_price' => null,
                'new_price' => $price->price,
                'changed_by' => $request->user()->id,
            ]);

            return $price;
        });

        return back()->with('success', "Price added for {$price->service->name}.");
    }

    public function update(UpdateServicePriceRequest $request, ServicePrice $servicePrice): RedirectResponse
    {
        $data = $request->validated();
        $reason = $data['reason'] ?? null;
        unset($data['reason']);

        DB::transaction(function () use ($data, $reason, $servicePrice, $request) {
            $oldPrice = $servicePrice->price;

            $servicePrice->update($data);

            ServicePriceHistory::create([
                'service_id' => $servicePrice->service_id,
                'service_price_id' => $servicePrice->id,
                'price_list' => $servicePrice->price_list,
                'old_price' => $oldPrice,
                'new_price' => $servicePrice->price,
                'changed_by' => $request->user()->id,
                'reason' => $reason,
            ]);
        });

        return back()->with('success', 'Price updated.');
    }

    public function destroy(ServicePrice $servicePrice): RedirectResponse
    {
        $this->authorize('delete', $servicePrice);

        $servicePrice->delete();

        return back()->with('success', 'Price removed.');
    }

    /**
     * Brief Phase 6 spec: "bulk price update tool (+10% to a category)" — adjusts every service's
     * base_price in the category by the given percent, each change logged to price history
     * individually so the audit trail reads the same as a single manual edit would.
     */
    public function bulkAdjust(BulkAdjustServicePricesRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $multiplier = 1 + ($data['percent'] / 100);

        $count = DB::transaction(function () use ($data, $multiplier, $request) {
            $services = Service::where('service_category_id', $data['service_category_id'])->get();

            foreach ($services as $service) {
                $oldPrice = $service->base_price;
                $newPrice = round($oldPrice * $multiplier, 2);

                $service->update(['base_price' => $newPrice]);

                ServicePriceHistory::create([
                    'service_id' => $service->id,
                    'service_price_id' => null,
                    'price_list' => 'base',
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                    'changed_by' => $request->user()->id,
                    'reason' => $data['reason'] ?? "Bulk adjustment: {$data['percent']}%",
                ]);
            }

            return $services->count();
        });

        return back()->with('success', "Adjusted base price for {$count} service(s) by {$data['percent']}%.");
    }
}
