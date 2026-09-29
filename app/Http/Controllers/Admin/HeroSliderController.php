<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSlideRequest;
use App\Http\Requests\Admin\UpdateSlideRequest;
use App\Models\Slide;
use App\Models\Slider;
use App\Services\SecureUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 10 sub-step 1: admin management for the homepage hero slider. The `sliders`/`slides` schema
 * (Phase 2) supports multiple named sliders WordPress-plugin style (Brief §10 item 3), but this
 * sub-step scopes to a single auto-provisioned "Homepage Hero" slider per the approved plan — full
 * multi-slider management is future scope, not built here.
 */
class HeroSliderController extends Controller
{
    private const HOMEPAGE_SLIDER_SLUG = 'homepage-hero';

    private function homepageSlider(): Slider
    {
        return Slider::firstOrCreate(
            ['slug' => self::HOMEPAGE_SLIDER_SLUG],
            ['name' => 'Homepage Hero', 'is_active' => true],
        );
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Slide::class);

        $slides = $this->homepageSlider()->slides()->orderBy('sort')->get();

        return Inertia::render('Admin/CMS/Sliders/Index', [
            'slides' => $slides->map(fn (Slide $slide) => $this->slideData($slide)),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Slide::class);

        return Inertia::render('Admin/CMS/Sliders/Form', []);
    }

    public function store(StoreSlideRequest $request, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'mobile_image']);
        $slider = $this->homepageSlider();

        $data['slider_id'] = $slider->id;
        $data['image_path'] = $uploads->storePublicImage($request->file('image'), 'sliders');

        if ($request->hasFile('mobile_image')) {
            $data['mobile_image_path'] = $uploads->storePublicImage($request->file('mobile_image'), 'sliders');
        }

        if (! isset($data['sort'])) {
            $data['sort'] = ((int) $slider->slides()->max('sort')) + 1;
        }

        $slide = Slide::create($data);

        return redirect()->route('admin.slider')->with('success', "Slide \"{$slide->heading}\" created.");
    }

    public function edit(Slide $slide): Response
    {
        $this->authorize('update', $slide);

        return Inertia::render('Admin/CMS/Sliders/Form', [
            'slide' => $this->slideData($slide),
        ]);
    }

    public function update(UpdateSlideRequest $request, Slide $slide, SecureUploadService $uploads): RedirectResponse
    {
        $data = $request->safe()->except(['image', 'mobile_image']);

        if ($request->hasFile('image')) {
            $oldPath = $slide->image_path;
            $data['image_path'] = $uploads->storePublicImage($request->file('image'), 'sliders');
            $uploads->deletePublic($oldPath);
        }

        if ($request->hasFile('mobile_image')) {
            $oldMobilePath = $slide->mobile_image_path;
            $data['mobile_image_path'] = $uploads->storePublicImage($request->file('mobile_image'), 'sliders');
            $uploads->deletePublic($oldMobilePath);
        }

        $slide->update($data);

        return redirect()->route('admin.slider')->with('success', "Slide \"{$slide->heading}\" updated.");
    }

    public function destroy(Slide $slide, SecureUploadService $uploads): RedirectResponse
    {
        $this->authorize('delete', $slide);

        $uploads->deletePublic([$slide->image_path, $slide->mobile_image_path]);
        $slide->delete();

        return back()->with('success', 'Slide deleted.');
    }

    public function reorder(Request $request): RedirectResponse
    {
        $this->authorize('create', Slide::class);

        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer', 'exists:slides,id'],
        ]);

        foreach ($data['order'] as $index => $id) {
            Slide::whereKey($id)->update(['sort' => $index]);
        }

        return back()->with('success', 'Order updated.');
    }

    /** @return array<string, mixed> */
    private function slideData(Slide $slide): array
    {
        return [
            'id' => $slide->id,
            'heading' => $slide->heading,
            'subheading' => $slide->subheading,
            'cta_text' => $slide->cta_text,
            'cta_url' => $slide->cta_url,
            'text_position' => $slide->text_position,
            'image_url' => $slide->image_path ? asset("storage/{$slide->image_path}") : null,
            'mobile_image_url' => $slide->mobile_image_path ? asset("storage/{$slide->mobile_image_path}") : null,
            'sort' => $slide->sort,
            'is_active' => $slide->is_active,
        ];
    }
}
