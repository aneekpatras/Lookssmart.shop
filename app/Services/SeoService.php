<?php

namespace App\Services;

use App\Models\BusinessHour;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;

/**
 * Phase 13 sub-step 1: the single place JSON-LD structured data gets built, so `BeautySalon`/
 * `Service`/`BlogPosting` schemas all read the same real, admin-editable source (Phase 10 Settings /
 * `BusinessHour` / the models themselves) instead of each page hand-rolling its own — and so nothing
 * in this app's structured data ever hardcodes a placeholder value (a fallback image path, a fake
 * price) that isn't backed by real data. Replaces `PublicWebsiteController::generateArticleJsonLd()`,
 * which hardcoded `/images/default-article.png` and `/images/logo.png` — neither file has ever
 * existed on disk (confirmed: `public/images/` doesn't exist), so every blog post's JSON-LD image has
 * been a broken URL since Phase 9 sub-step 6. Fixed here by falling back to the real
 * `business.logo_path` upload, or omitting `image` entirely rather than pointing at nothing.
 */
class SeoService
{
    private const DAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /** @return array<string, mixed> */
    public function localBusiness(): array
    {
        $latitude = Setting::get('business.latitude');
        $longitude = Setting::get('business.longitude');
        $hasGeo = $latitude !== null && $latitude !== '' && $longitude !== null && $longitude !== '';

        $sameAs = array_values(array_filter([
            Setting::get('business.social_facebook'),
            Setting::get('business.social_instagram'),
        ]));

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BeautySalon',
            'name' => $this->businessName(),
            'image' => $this->defaultImageUrl(),
            'url' => config('app.url'),
            'telephone' => Setting::get('business.phone'),
            'email' => Setting::get('business.email'),
            'address' => Setting::get('business.address'),
            'geo' => $hasGeo ? [
                '@type' => 'GeoCoordinates',
                'latitude' => (float) $latitude,
                'longitude' => (float) $longitude,
            ] : null,
            'openingHoursSpecification' => $this->openingHours() ?: null,
            'sameAs' => $sameAs ?: null,
        ]);
    }

    /** @return array<string, mixed> */
    public function service(Service $service): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'serviceType' => $service->category?->name,
            'name' => $service->name,
            'description' => $service->seo_description ?: $service->description,
            'provider' => $this->organizationRef(),
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => number_format((float) $service->base_price, 2, '.', ''),
                'priceCurrency' => Setting::get('business.currency') ?: 'PKR',
                'availability' => 'https://schema.org/InStock',
                'url' => route('services.show', $service->slug),
            ]),
            'additionalProperty' => [
                '@type' => 'PropertyValue',
                'name' => 'Duration',
                'value' => "{$service->duration_min} minutes",
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function blogPosting(Post $post, int $wordCount, int $readingTimeMinutes): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            'headline' => $post->seo_title ?: $post->title,
            'description' => $post->seo_description ?: $post->excerpt,
            'image' => $post->cover_image_path ? asset("storage/{$post->cover_image_path}") : $this->defaultImageUrl(),
            'author' => [
                '@type' => 'Person',
                'name' => $post->author?->name ?? 'Admin',
            ],
            'datePublished' => $post->published_at?->toIso8601String(),
            'dateModified' => $post->updated_at->toIso8601String(),
            'publisher' => $this->organizationRef(),
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => route('blog.show', $post->slug),
            ],
            'wordCount' => $wordCount,
            'timeRequired' => "PT{$readingTimeMinutes}M",
            'articleSection' => $post->category?->name ?? 'Blog',
        ], fn ($value) => $value !== null);
    }

    public function businessName(): string
    {
        return Setting::get('business.name') ?: config('app.name');
    }

    public function defaultImageUrl(): ?string
    {
        $path = Setting::get('business.logo_path');

        return is_string($path) && $path !== '' ? asset("storage/{$path}") : null;
    }

    /** @return array<int, array<string, string>> */
    private function openingHours(): array
    {
        return BusinessHour::where('is_closed', false)
            ->orderBy('weekday')
            ->get()
            ->map(fn (BusinessHour $hour) => [
                '@type' => 'OpeningHoursSpecification',
                'dayOfWeek' => 'https://schema.org/' . (self::DAY_NAMES[$hour->weekday] ?? 'Monday'),
                'opens' => substr((string) $hour->open_time, 0, 5),
                'closes' => substr((string) $hour->close_time, 0, 5),
            ])
            ->all();
    }

    /** @return array<string, mixed> */
    private function organizationRef(): array
    {
        return array_filter([
            '@type' => 'Organization',
            'name' => $this->businessName(),
            'url' => config('app.url'),
            'logo' => $this->defaultImageUrl(),
        ]);
    }
}
