<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;
use Symfony\Component\HttpFoundation\Response as ResponseContract;

/**
 * Phase 13 sub-step 1: `spatie/laravel-sitemap` (locked stack, CLAUDE.md's tech list — not
 * previously installed) rather than hand-rolled XML, so the output is spec-compliant by
 * construction. `/deals` is now a real, controller-backed page (built for the Deals & Offers task —
 * see `PublicWebsiteController::deals()`), so it is included below like any other real page. Service
 * categories aren't included either: `/services` only ever filters by a numeric `?category=` query
 * param, which is neither a distinct crawlable URL nor slug-based — sitemapping it would just be
 * indexing duplicate content under an unstable id-based URL.
 */
class SeoController extends Controller
{
    public function sitemap(Request $request): ResponseContract
    {
        $sitemap = Sitemap::create()
            ->add(Url::create(route('home'))->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)->setPriority(1.0))
            ->add(Url::create(route('services.index'))->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.9))
            ->add(Url::create(route('book'))->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.7))
            ->add(Url::create(route('deals.index'))->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.7))
            ->add(Url::create(route('blog.index'))->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)->setPriority(0.7))
            ->add(Url::create(route('gallery.index'))->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)->setPriority(0.6))
            ->add(Url::create(route('about'))->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)->setPriority(0.5))
            ->add(Url::create(route('contact'))->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)->setPriority(0.5))
            ->add(Url::create(route('privacy-policy'))->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY)->setPriority(0.2));

        Service::active()->select(['slug', 'updated_at'])->orderBy('id')
            ->each(fn (Service $service) => $sitemap->add(
                Url::create(route('services.show', $service->slug))
                    ->setLastModificationDate($service->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8),
            ));

        Post::published()->select(['slug', 'updated_at'])->orderBy('id')
            ->each(fn (Post $post) => $sitemap->add(
                Url::create(route('blog.show', $post->slug))
                    ->setLastModificationDate($post->updated_at)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setPriority(0.6),
            ));

        return $sitemap->toResponse($request);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /admin',
            'Disallow: /my-account',
            'Disallow: /customer',
            'Disallow: /api',
            '',
            'Sitemap: ' . route('sitemap'),
        ];

        return response(implode("\n", $lines) . "\n", 200, ['Content-Type' => 'text/plain']);
    }

    /**
     * Phase 13 sub-step 2: PWA-lite manifest — `favicon.ico` (the only icon asset this project
     * actually ships) is always included; the real uploaded `business.logo_path` is added on top of
     * it only when one is configured AND still exists on disk (never a stale/broken reference).
     * Neither is a proper multi-resolution PWA icon set (192/512 PNGs) — there's no icon-generation
     * pipeline in this project — logged honestly as a known limitation rather than fabricating sizes
     * that were never actually produced.
     */
    public function manifest(): JsonResponse
    {
        $name = Setting::get('business.name') ?: config('app.name');

        $icons = [[
            'src' => asset('favicon.ico'),
            'sizes' => 'any',
            'type' => 'image/x-icon',
        ]];

        $logoPath = Setting::get('business.logo_path');

        if (is_string($logoPath) && $logoPath !== '' && Storage::disk('public')->exists($logoPath)) {
            $icons[] = [
                'src' => asset("storage/{$logoPath}"),
                'sizes' => 'any',
                'type' => Storage::disk('public')->mimeType($logoPath) ?: 'image/png',
                'purpose' => 'any',
            ];
        }

        return response()->json([
            'name' => $name,
            'short_name' => $name,
            'description' => Setting::get('seo.default_description') ?: 'Book hair, skin, and nail appointments online.',
            'start_url' => '/',
            'display' => 'standalone',
            // Real design-system tokens (resources/css/app.css --color-ivory/--color-accent-500),
            // not placeholder colors.
            'background_color' => '#faf7f2',
            'theme_color' => '#c9a66b',
            'icons' => $icons,
        ])->header('Content-Type', 'application/manifest+json');
    }
}
