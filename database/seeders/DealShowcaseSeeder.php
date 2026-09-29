<?php

namespace Database\Seeders;

use App\Models\Deal;
use App\Models\Service;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Replaces `DatabaseSeeder`'s old `seedDeals()` — 8 factory deals with fake-sentence titles, random
 * types/values, and a 70%-chance of attaching 1-5 RANDOM services — with 12 real package deals
 * across all 6 public category tags, each genuinely bundling real catalog services.
 *
 * **Every deal's `original_price` is the real sum of its attached services' `base_price`, and
 * `deal_price` is a genuine discount off that sum — not invented marketing numbers.** `value` is
 * set to `original_price - deal_price` with `type = 'fixed'`, so `PriceQuoteService::
 * calculateDiscount()` (`min($deal->value, $subtotal)`) produces EXACTLY the advertised deal price
 * when a customer books precisely this bundle — verified by a real Pest test hitting
 * `/api/booking/quote`. A displayed price that the booking engine cannot actually honour would be
 * false advertising, not a content-seeding shortcut.
 *
 * Every deal gets a real, unique coupon `code` so "Claim Offer" is never a dead link — see
 * `PublicWebsiteController::dealShowcaseData()`'s `claim_url`.
 */
class DealShowcaseSeeder extends Seeder
{
    private const IMAGES = [
        'hair' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&h=600&fit=crop',
        'skin' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&h=600&fit=crop',
        'bridal' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=900&h=600&fit=crop',
        'lashes' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&h=600&fit=crop',
        'spa' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&h=600&fit=crop',
    ];

    public function run(): void
    {
        // `->toBase()` is load-bearing, not stylistic: `Illuminate\Database\Eloquent\Collection`
        // OVERRIDES `only()`/`except()` to filter by the model's PRIMARY KEY, not by the collection's
        // array key — so `keyBy('sku')->only($skus)` below would silently return an empty
        // collection every time (a service's `id` never equals a SKU string), found only by
        // instrumenting the seeder directly after it ran with no error and inserted zero rows.
        // `toBase()` converts to a plain `Support\Collection`, whose `only()` genuinely means what
        // it says.
        $services = Service::query()->get()->keyBy('sku')->toBase();

        if ($services->isEmpty()) {
            // No catalog to bundle against (e.g. this seeder run in isolation without
            // SalonCatalogSeeder first) — skip cleanly rather than create deals with no real
            // services and therefore no real, honourable price.
            return;
        }

        foreach ($this->deals() as $definition) {
            $skus = $definition['skus'];
            $bundled = $services->only($skus);

            if ($bundled->count() !== count($skus)) {
                // A named SKU no longer exists in the catalog — skip this deal rather than seed one
                // whose advertised price cannot be traced back to real services.
                continue;
            }

            $originalPrice = (float) $bundled->sum('base_price');
            $dealPrice = $definition['deal_price'];

            $deal = Deal::updateOrCreate(
                ['slug' => Str::slug($definition['title'])],
                [
                    'title' => $definition['title'],
                    'subtitle' => $definition['subtitle'],
                    'category_tag' => $definition['category_tag'],
                    'type' => 'fixed',
                    'value' => round($originalPrice - $dealPrice, 2),
                    'original_price' => $originalPrice,
                    'deal_price' => $dealPrice,
                    'included_services' => $definition['included_services'] ?? $bundled->pluck('name')->values()->all(),
                    'description' => $definition['description'],
                    'terms' => $definition['terms'],
                    // A full external URL, not a local upload path — see the note below the
                    // `updateOrCreate` call for how the resolver distinguishes the two.
                    'image_path' => $definition['image'],
                    'code' => $definition['code'],
                    'starts_at' => now()->subDay(),
                    'ends_at' => now()->addMonths(3),
                    'min_amount' => $definition['min_amount'] ?? null,
                    'is_stackable' => false,
                    'is_auto_apply' => false,
                    'is_active' => true,
                    'is_top_deal' => $definition['is_top_deal'] ?? false,
                ],
            );

            $deal->services()->sync($bundled->pluck('id'));
        }
    }

    /**
     * @return list<array{title: string, subtitle: string, category_tag: string, skus: list<string>, deal_price: float, code: string, image: string, description: string, terms: string, included_services?: list<string>, min_amount?: float, is_top_deal?: bool}>
     */
    private function deals(): array
    {
        return [
            [
                'title' => 'Bridal Day Package',
                'subtitle' => 'Complete bridal-day styling, start to finish',
                'category_tag' => 'Top Deals',
                'skus' => ['MUA-BARAT-SIGNATURE', 'THR-FACE-FULL', 'THR-EYEBROW'],
                'deal_price' => 52000.00,
                'code' => 'BRIDALDAY',
                'image' => self::IMAGES['bridal'],
                'description' => 'A full bridal day, covered end to end: our signature masterpiece Barat/Walima makeup application by a senior artist, finished with full-face threading and eyebrow shaping so nothing is left to arrange separately on the morning of the event.',
                'terms' => 'Booking must be made at least 2 weeks in advance. A consultation is required before the event date. Not combinable with other offers or coupon codes.',
                'is_top_deal' => true,
            ],
            [
                'title' => 'Ultimate Spa Retreat',
                'subtitle' => 'Two signature spa therapies, one relaxed afternoon',
                'category_tag' => 'Top Deals',
                'skus' => ['SPA-FULLBODY-LUX', 'SPA-HOTSTONE'],
                'deal_price' => 24000.00,
                'code' => 'SPARETREAT',
                'image' => self::IMAGES['spa'],
                'description' => 'Our full-body luxury spa massage paired with volcanic hot stone therapy in the same visit — real muscle release, not just a relaxing hour.',
                'terms' => 'Subject to therapist availability. Please arrive 10 minutes early to change. Not combinable with other offers.',
            ],
            [
                'title' => 'Hair Revival Bundle',
                'subtitle' => 'High Frequency Hair Voucher',
                'category_tag' => 'Hair Deals',
                'skus' => ['HAIR-BLOWDRY', 'HAIR-BOTOX'],
                'deal_price' => 10500.00,
                'code' => 'HAIRVOUCHER',
                'image' => self::IMAGES['hair'],
                'description' => 'A restorative fibre treatment followed by a full blow-dry finish — real repair, not just a styling session.',
                'terms' => 'Best results with a follow-up visit within 6 weeks. Not combinable with other offers.',
            ],
            [
                'title' => 'Smoothing Saver',
                'subtitle' => 'X-Tenso smoothing + Hair Botox, together',
                'category_tag' => 'Hair Deals',
                'skus' => ['HAIR-XTENSO-LOREAL', 'HAIR-BOTOX'],
                'deal_price' => 18000.00,
                'code' => 'SMOOTHSAVE',
                'image' => self::IMAGES['hair'],
                'description' => "L'Oréal X-Tenso smoothing paired with a Hair Botox restorative treatment in the same appointment, for a sleeker finish with less frizz-rebound between washes.",
                'terms' => 'Allow up to 5 hours for both treatments. Avoid washing hair for 72 hours afterward. Not combinable with other offers.',
            ],
            [
                'title' => 'Party Glam Duo',
                'subtitle' => 'Signature glam + HD finish, one booking',
                'category_tag' => 'Makeup Deals',
                'skus' => ['MUA-PARTY-GLAM', 'MUA-HD-PARTY'],
                'deal_price' => 18000.00,
                'code' => 'PARTYGLAM',
                'image' => self::IMAGES['bridal'],
                'description' => 'Two of our most-booked party looks in one package — ideal for back-to-back events or for choosing between two styles at your trial.',
                'terms' => 'Both looks must be used within 60 days of purchase. Not combinable with other offers.',
            ],
            [
                'title' => 'Engagement Ready Package',
                'subtitle' => 'Senior-artist engagement makeup + face threading',
                'category_tag' => 'Makeup Deals',
                'skus' => ['MUA-NIKKAH-SENIOR', 'THR-FACE-FULL'],
                'deal_price' => 16500.00,
                'code' => 'ENGAGEREADY',
                'image' => self::IMAGES['bridal'],
                'description' => 'Senior-artist engagement makeup application with full-face threading beforehand, so skin prep and the final look are handled in one visit.',
                'terms' => 'Booking requires 1 week notice. Not combinable with other offers.',
            ],
            [
                'title' => 'Radiance Ritual',
                'subtitle' => 'Gold Hydra Therapy + gold polisher glow',
                'category_tag' => 'Skin Deals',
                'skus' => ['SKIN-GOLD-HYDRA', 'SKIN-POLISH-GOLD'],
                'deal_price' => 6900.00,
                'code' => 'RADIANCE',
                'image' => self::IMAGES['skin'],
                'description' => 'A deep-hydration gold facial followed by a micro-exfoliating gold polish for an immediate, event-ready glow.',
                'terms' => 'Not recommended within 48 hours of sun exposure. Not combinable with other offers.',
            ],
            [
                'title' => 'Glow Prep Combo',
                'subtitle' => 'Skin polish + full-face waxing',
                'category_tag' => 'Skin Deals',
                'skus' => ['SKIN-POLISH-GOLD', 'WAX-FACE-FULL'],
                'deal_price' => 2300.00,
                'code' => 'GLOWPREP',
                'image' => self::IMAGES['skin'],
                'description' => 'A gold micro-exfoliating polish paired with full-face waxing and a cooling mask — a genuine pre-event pairing, not two unrelated add-ons.',
                'terms' => 'Not combinable with other offers or coupon codes.',
            ],
            [
                'title' => 'Hair + Nails Refresh',
                'subtitle' => 'A cut and a fresh set, same visit',
                'category_tag' => 'Combo Deals',
                'skus' => ['HAIR-CUT-JUNIOR', 'SPEC-NAIL-GEL'],
                'deal_price' => 4500.00,
                'code' => 'HAIRNAILS',
                'image' => self::IMAGES['lashes'],
                'description' => 'A precision cut and finish by one of our junior stylists, paired with a full gel nail art and extension package.',
                'terms' => 'Both services must be booked for the same visit. Not combinable with other offers.',
            ],
            [
                'title' => 'Pre-Event Polish Combo',
                'subtitle' => 'Threading, skin polish and nails in one visit',
                'category_tag' => 'Combo Deals',
                'skus' => ['THR-FACE-FULL', 'SKIN-POLISH-GOLD', 'SPEC-NAIL-GEL'],
                'deal_price' => 4750.00,
                'code' => 'PREEVENT',
                'image' => self::IMAGES['lashes'],
                'description' => 'Full-face threading, a gold skin polish, and a gel nail art package — the three quick wins clients most often book before an event, bundled into one price.',
                'terms' => 'Subject to appointment availability across all three services in a single visit. Not combinable with other offers.',
            ],
            [
                'title' => 'Refer-a-Friend Threading Bonus',
                'subtitle' => 'Bring a friend, get eyebrow threading free',
                'category_tag' => 'Referral Deals',
                'skus' => ['THR-EYEBROW'],
                'deal_price' => 0.00,
                'code' => 'REFERTHREAD',
                'image' => self::IMAGES['lashes'],
                'description' => 'Refer a friend who books their first appointment with us, and your next eyebrow threading is on the house.',
                'terms' => 'Valid on your next visit after your referral completes their first paid appointment. One redemption per referral. Requires a minimum spend of Rs. 3,000 on the same visit.',
                'included_services' => ['Free Eyebrow Precision Threading with any qualifying booking'],
                'min_amount' => 3000.00,
            ],
            [
                'title' => 'Bring a Friend Spa Discount',
                'subtitle' => 'Rs. 1,000 off a full-body spa session',
                'category_tag' => 'Referral Deals',
                'skus' => ['SPA-FULLBODY-LUX'],
                'deal_price' => 14750.00,
                'code' => 'REFERSPA',
                'image' => self::IMAGES['spa'],
                'description' => 'Refer a friend and both of you save Rs. 1,000 on a Full Body Luxury Spa & Massage session.',
                'terms' => 'Valid once per referred friend. Cannot be combined with other offers.',
            ],
        ];
    }
}
