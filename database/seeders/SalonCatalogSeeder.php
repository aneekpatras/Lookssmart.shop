<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * The REAL Looks Smart Beauty Salon service catalog — 55 services across 5 categories, all prices
 * in PKR.
 *
 * PRICING: every `base_price` below is the salon's CURRENT, LIVE price with its 25% discount
 * ALREADY APPLIED — these are the amounts a customer pays and the amounts the site displays. They
 * are stored as-is and must NOT have a further discount applied on top. This was verified rather
 * than assumed: 22 of 28 sampled figures divide exactly by 0.75 into round-50 amounts (e.g. 19,500
 * from 26,000; 23,250 from 31,000; 60,750 from 81,000), and the two services carried over from the
 * previous catalog match precisely — eyebrow threading went Rs. 200 -> 150 and full-face threading
 * Rs. 800 -> 600. The handful that do not divide cleanly (11,600 / 12,350 / 1,850 / 2,600 / 19,100 /
 * 3,500) are the deliberate "rounded to clean PKR amounts" cases. If the salon ever changes the
 * discount, edit these numbers here — do not add a runtime multiplier, because `PriceQuoteService`
 * computes every quote server-side from `base_price` and a second implicit discount would silently
 * disagree with the displayed price.
 *
 * DESCRIPTIONS: written originally for this project. The salon's source material was reworded rather
 * than copied, both to avoid duplicating third-party marketing copy and because several services
 * arrived with no description at all.
 *
 * IMAGES: services are seeded WITHOUT medialibrary images. Card and banner imagery falls back to the
 * category's `stock_image_url` (a remote Unsplash photo, licence permits commercial use) until real
 * salon photography is uploaded through the admin Media Library, which then takes precedence — see
 * PublicWebsiteController::serviceData(). Those URLs need `https://images.unsplash.com` in the CSP's
 * `img-src`; the default `img-src 'self' data:` would block them silently.
 *
 * VARIANTS: where the salon prices the same treatment by hair length, artist seniority, or product
 * line, each is a separate bookable service rather than a "variant" of one — this schema has no
 * variant concept (no `service_variants` table, no `variants` column; `service_prices.price_list`
 * models weekend/seasonal windows, not customer-selectable tiers), and each tier genuinely differs
 * in both price and chair time, which the booking engine must know at slot-generation time.
 *
 * Idempotent: keyed on the service/category slug via `updateOrCreate`, so re-running refreshes
 * prices and copy in place instead of duplicating the catalog.
 */
class SalonCatalogSeeder extends Seeder
{
    /**
     * The 5 customer-facing categories. `stock_image_url` photo IDs are real Unsplash images taken
     * from the salon's own prior service export (so they are known-good and already category
     * appropriate), and each was confirmed to return HTTP 200 before being committed.
     */
    private const CATEGORIES = [
        'hair-styling-treatments' => [
            'name' => 'Hair Styling & Treatments',
            'sort' => 1,
            'stock_image_url' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&h=500&fit=crop',
        ],
        'skin-facial-care' => [
            'name' => 'Skin & Facial Care',
            'sort' => 2,
            'stock_image_url' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&h=500&fit=crop',
        ],
        'bridal-party-makeup' => [
            'name' => 'Bridal & Party Makeup',
            'sort' => 3,
            'stock_image_url' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=900&h=500&fit=crop',
        ],
        'lashes-waxing-threading' => [
            'name' => 'Eyelashes, Waxing & Threading',
            'sort' => 4,
            'stock_image_url' => 'https://images.unsplash.com/photo-1522335789203-aabd1fc54bc9?w=900&h=500&fit=crop',
        ],
        'massage-spa-specials' => [
            'name' => 'Massage, Spa & Special Services',
            'sort' => 5,
            'stock_image_url' => 'https://images.unsplash.com/photo-1544161515-4ab6ce6db874?w=900&h=500&fit=crop',
        ],
    ];

    /**
     * `buffer_min` is scaled to the appointment: long chemical services need real turnaround on the
     * station, a 15-minute threading slot does not.
     *
     * @var list<array{sku: string, category: string, name: string, description: string, duration_min: int, buffer_min: int, base_price: float, is_featured?: bool}>
     */
    private const SERVICES = [
        // ─── Hair Styling & Treatments (23) ───────────────────────────────────────────────
        [
            'sku' => 'HAIR-XTENSO-LOREAL',
            'category' => 'hair-styling-treatments',
            'name' => "L'Oréal X-Tenso Smooth",
            'description' => 'An advanced protein smoothing therapy that relaxes the hair\'s internal bonds for a sleek, manageable finish without flattening its natural movement.',
            'duration_min' => 180,
            'buffer_min' => 20,
            'base_price' => 12000.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'HAIR-EXTENSO-SIG',
            'category' => 'hair-styling-treatments',
            'name' => 'Signature Extenso Straightening',
            'description' => 'Our premium straightening service, formulated for a long-lasting poker-straight result that holds its shape through months of regular washing.',
            'duration_min' => 180,
            'buffer_min' => 20,
            'base_price' => 19500.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'HAIR-REBOND-PERM',
            'category' => 'hair-styling-treatments',
            'name' => 'Permanent Rebonding',
            'description' => 'A structural transformation that permanently re-forms the hair\'s bonds for an ultra-sleek, glass-smooth result on even the most resistant textures.',
            'duration_min' => 240,
            'buffer_min' => 30,
            'base_price' => 23250.00,
        ],
        [
            'sku' => 'HAIR-EXTENSO-KERATIN',
            'category' => 'hair-styling-treatments',
            'name' => 'Extenso + Keratin Dual Therapy',
            'description' => 'Two treatments in one sitting: straightening for smoothness, followed by an intensive keratin repair layer for mirror-like shine and reinforced strands.',
            'duration_min' => 240,
            'buffer_min' => 30,
            'base_price' => 27000.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'HAIR-BOTOX',
            'category' => 'hair-styling-treatments',
            'name' => 'Hair Botox Restorative Treatment',
            'description' => 'A deep-conditioning fibre treatment that floods dehydrated strands with moisture and reseals a roughened cuticle, softening frizz without altering texture.',
            'duration_min' => 90,
            'buffer_min' => 15,
            'base_price' => 12000.00,
        ],
        [
            'sku' => 'HAIR-PERM-GLAM',
            'category' => 'hair-styling-treatments',
            'name' => 'Glamour Hair Perming',
            'description' => 'Permanent waves set to your preferred curl size, building lasting volume and definition that needs little more than a scrunch to revive each morning.',
            'duration_min' => 120,
            'buffer_min' => 15,
            'base_price' => 6750.00,
        ],
        [
            'sku' => 'HAIR-COLOR-SHOULDER',
            'category' => 'hair-styling-treatments',
            'name' => 'Full Hair Color (Shoulder Length)',
            'description' => 'Complete root-to-tip colour for shoulder-length hair, giving even, saturated coverage in the shade you choose during consultation.',
            'duration_min' => 90,
            'buffer_min' => 15,
            'base_price' => 4500.00,
        ],
        [
            'sku' => 'HAIR-COLOR-MIDBACK',
            'category' => 'hair-styling-treatments',
            'name' => 'Full Hair Color (Mid-Back)',
            'description' => 'A full multi-tonal colour transformation for mid-back lengths, built in depth so the result reads rich rather than flat.',
            'duration_min' => 120,
            'buffer_min' => 15,
            'base_price' => 12000.00,
        ],
        [
            'sku' => 'HAIR-STREAKS-SHOULDER',
            'category' => 'hair-styling-treatments',
            'name' => 'Dimensional Streaks (Shoulder)',
            'description' => 'Placed highlight accents through shoulder-length hair, positioned to catch the light around the face and add visible depth.',
            'duration_min' => 120,
            'buffer_min' => 15,
            'base_price' => 12000.00,
        ],
        [
            'sku' => 'HAIR-STREAKS-MIDBACK',
            'category' => 'hair-styling-treatments',
            'name' => 'Dimensional Streaks (Mid-Back)',
            'description' => 'Full foil highlighting across mid-back lengths for a high-impact, sunlit contrast between base and highlight.',
            'duration_min' => 150,
            'buffer_min' => 20,
            'base_price' => 27000.00,
        ],
        [
            'sku' => 'HAIR-BALAYAGE',
            'category' => 'hair-styling-treatments',
            'name' => 'Full Balayage / Ombré Glow',
            'description' => 'Colour painted on freehand so it melts from a deeper root into brighter ends, with no hard regrowth line as it grows out.',
            'duration_min' => 180,
            'buffer_min' => 20,
            'base_price' => 11600.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'HAIR-BABYLIGHTS',
            'category' => 'hair-styling-treatments',
            'name' => 'Micro Baby Lights & Weaving',
            'description' => 'Very fine woven highlights that mimic the way hair naturally lightens in the sun — the softest, least obvious way to brighten a base shade.',
            'duration_min' => 180,
            'buffer_min' => 20,
            'base_price' => 12350.00,
        ],
        [
            'sku' => 'HAIR-EXT-PERMANENT',
            'category' => 'hair-styling-treatments',
            'name' => 'Permanent Hair Extension Fitting',
            'description' => 'A full permanent extension fitting, colour-matched and cut into your own hair so added length and volume blend invisibly.',
            'duration_min' => 300,
            'buffer_min' => 30,
            'base_price' => 132000.00,
        ],
        [
            'sku' => 'HAIR-EXT-TEMP',
            'category' => 'hair-styling-treatments',
            'name' => 'Clip-In / Tape-In Extensions',
            'description' => 'Temporary length fitted and blended in a single short appointment — ideal for an event, and removable the same evening.',
            'duration_min' => 60,
            'buffer_min' => 10,
            'base_price' => 19500.00,
        ],
        [
            'sku' => 'HAIR-SCALP-HF',
            'category' => 'hair-styling-treatments',
            'name' => 'Scalp High-Frequency Therapy',
            'description' => 'A stimulating scalp treatment that encourages circulation at the follicle and leaves the scalp feeling clean, calm and less congested.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 3000.00,
        ],
        [
            'sku' => 'HAIR-CUT-KIDS',
            'category' => 'hair-styling-treatments',
            'name' => 'Gentle Kids Hair Cut',
            'description' => 'An unhurried cut for children, shaped to suit them and paced so the whole thing stays comfortable from start to finish.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1850.00,
        ],
        [
            'sku' => 'HAIR-STYLE-CRIMP',
            'category' => 'hair-styling-treatments',
            'name' => 'Textured Crimped Hair Styling',
            'description' => 'Sharp, editorial crimped texture built for maximum volume and a distinctly high-fashion silhouette.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 3000.00,
        ],
        [
            'sku' => 'HAIR-STYLE-CURLS',
            'category' => 'hair-styling-treatments',
            'name' => 'Defined Glamour Iron Curls',
            'description' => 'Thermally set waves or curls, brushed out to the finish you prefer — from soft Hollywood movement to tightly defined spirals.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 2600.00,
        ],
        [
            'sku' => 'HAIR-CUT-JUNIOR',
            'category' => 'hair-styling-treatments',
            'name' => 'Junior Stylist Haircut & Finish',
            'description' => 'A precision cut and finish by one of our junior artists, supervised to the same standard at a more accessible price.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 2250.00,
        ],
        [
            'sku' => 'HAIR-HOTOIL',
            'category' => 'hair-styling-treatments',
            'name' => 'Nourishing Hot Oil Scalp Massage',
            'description' => 'Warmed oil worked through the scalp and lengths by hand — as much a stress-relief ritual as a conditioning treatment.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1500.00,
        ],
        [
            'sku' => 'HAIR-BLOWDRY',
            'category' => 'hair-styling-treatments',
            'name' => 'Classic Blow Dry & Volume',
            'description' => 'A cleansing wash followed by a full blow-out, finished with real body and lift through the roots.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 2250.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'HAIR-IRON-STRAIGHT',
            'category' => 'hair-styling-treatments',
            'name' => 'Thermal Iron Straightening',
            'description' => 'A quick, heat-protected iron press for a smooth, high-shine finish that lasts until your next wash.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 2250.00,
        ],
        [
            'sku' => 'HAIR-STYLE-SIMPLE',
            'category' => 'hair-styling-treatments',
            'name' => 'Simple Everyday Hair Styling',
            'description' => 'An easy pinned updo or neat textured finish for work, lunch or an unplanned evening out.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 2250.00,
        ],

        // ─── Skin & Facial Care (8) ───────────────────────────────────────────────────────
        [
            'sku' => 'SKIN-GOLD-HYDRA',
            'category' => 'skin-facial-care',
            'name' => 'Gold-Infused Hydra Therapy',
            'description' => 'A deeply hydrating facial using fine 24k gold particles to leave the complexion plumped, calm and noticeably luminous.',
            'duration_min' => 75,
            'buffer_min' => 15,
            'base_price' => 7500.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'SKIN-THALGO-MARINE',
            'category' => 'skin-facial-care',
            'name' => 'THALGO Marine Brightening Facial',
            'description' => 'Marine-derived actives that even out tone and lift dullness, finishing with a fresh, balanced glow rather than a tight, stripped feeling.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 6750.00,
        ],
        [
            'sku' => 'SKIN-HYDRAGLOW-GOLD',
            'category' => 'skin-facial-care',
            'name' => 'Hydra Glow Facial GOLD',
            'description' => 'A high-moisture gold rejuvenation facial with no abrasive polishing step, making it suitable for skin that reacts badly to exfoliation.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 8250.00,
        ],
        [
            'sku' => 'SKIN-TEEN-CLEAR',
            'category' => 'skin-facial-care',
            'name' => 'Teen Clear Skin Facial',
            'description' => 'A gentle Janssen-based cleanse and decongest designed for younger skin, easing breakouts without over-drying.',
            'duration_min' => 45,
            'buffer_min' => 10,
            'base_price' => 3000.00,
        ],
        [
            'sku' => 'SKIN-JANSSEN-WHITEN',
            'category' => 'skin-facial-care',
            'name' => 'Janssen Professional Whitening Facial',
            'description' => 'A German-formulated brightening protocol that targets uneven patches and post-blemish marks over a course of treatments.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 4500.00,
        ],
        [
            'sku' => 'SKIN-POLISH-GOLD',
            'category' => 'skin-facial-care',
            'name' => 'Radiant Skin Polisher (Gold)',
            'description' => 'A luxurious micro-exfoliating polish that lifts away dull surface cells and leaves an immediate, event-ready glow.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1850.00,
        ],
        [
            'sku' => 'SKIN-POLISH-SAFFRON',
            'category' => 'skin-facial-care',
            'name' => 'Saffron Organic Skin Polisher',
            'description' => 'A herbal saffron-based polish that nourishes as it refines, favoured by anyone preferring a botanical formulation.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 2250.00,
        ],
        [
            'sku' => 'SKIN-POLISH-JANSSEN',
            'category' => 'skin-facial-care',
            'name' => 'Janssen Intensive Skin Polish',
            'description' => 'A clinical-grade refining polish for texture, congestion and roughness where a gentler polish has not been enough.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 3000.00,
        ],

        // ─── Bridal & Party Makeup (9) ────────────────────────────────────────────────────
        [
            'sku' => 'MUA-NIKKAH-JUNIOR',
            'category' => 'bridal-party-makeup',
            'name' => 'Nikkah / Engagement Makeup (Junior Artist)',
            'description' => 'A softer, camera-friendly Nikkah or engagement look created by one of our junior artists, with lashes and draping included.',
            'duration_min' => 90,
            'buffer_min' => 15,
            'base_price' => 15750.00,
        ],
        [
            'sku' => 'MUA-NIKKAH-SENIOR',
            'category' => 'bridal-party-makeup',
            'name' => 'Nikkah / Engagement Makeup (Senior Artist)',
            'description' => 'Your Nikkah or engagement look built by a senior artist, with closer attention to shade matching and lasting power under event lighting.',
            'duration_min' => 90,
            'buffer_min' => 15,
            'base_price' => 19500.00,
        ],
        [
            'sku' => 'MUA-NIKKAH-SIGNATURE',
            'category' => 'bridal-party-makeup',
            'name' => 'Nikkah / Engagement Makeup (Signature Artist)',
            'description' => 'Our signature artist designs the look with you personally, from a full consultation through to the final set — the most bespoke option we offer for the day.',
            'duration_min' => 120,
            'buffer_min' => 20,
            'base_price' => 30750.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'MUA-BARAT-JUNIOR',
            'category' => 'bridal-party-makeup',
            'name' => 'Barat / Walima Bridal (Junior Artist)',
            'description' => 'A complete Barat or Walima bridal application by a junior artist, finished to hold through a long wedding day.',
            'duration_min' => 120,
            'buffer_min' => 20,
            'base_price' => 19100.00,
        ],
        [
            'sku' => 'MUA-BARAT-SENIOR',
            'category' => 'bridal-party-makeup',
            'name' => 'Barat / Walima Bridal (Senior Artist)',
            'description' => 'Full bridal makeup by a senior artist, built in layers for durability and photographed-tested depth across every kind of venue light.',
            'duration_min' => 120,
            'buffer_min' => 20,
            'base_price' => 38250.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'MUA-BARAT-SIGNATURE',
            'category' => 'bridal-party-makeup',
            'name' => 'Barat / Walima Bridal (Signature Masterpiece)',
            'description' => 'Our most complete bridal service: an extended personal consultation, a fully bespoke design and unhurried application in the private bridal room.',
            'duration_min' => 150,
            'buffer_min' => 30,
            'base_price' => 60750.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'MUA-PARTY-GLAM',
            'category' => 'bridal-party-makeup',
            'name' => 'Signature Glam Party Makeup',
            'description' => 'A full glam party look — sculpted base, defined eyes and lashes — designed to last a full evening of dinner and dancing.',
            'duration_min' => 60,
            'buffer_min' => 10,
            'base_price' => 8250.00,
        ],
        [
            'sku' => 'MUA-EDITORIAL',
            'category' => 'bridal-party-makeup',
            'name' => 'Editorial Model Look Makeup',
            'description' => 'A directional, shoot-ready look built for the camera, whether that is a clean editorial skin finish or something more graphic.',
            'duration_min' => 60,
            'buffer_min' => 10,
            'base_price' => 8250.00,
        ],
        [
            'sku' => 'MUA-HD-PARTY',
            'category' => 'bridal-party-makeup',
            'name' => 'HD High-Definition Party Look',
            'description' => 'High-definition products chosen to stay flawless in flash photography and video, with no white cast or visible powder.',
            'duration_min' => 90,
            'buffer_min' => 15,
            'base_price' => 15750.00,
        ],

        // ─── Eyelashes, Waxing & Threading (7) ────────────────────────────────────────────
        [
            'sku' => 'LASH-EXT-SEMI',
            'category' => 'lashes-waxing-threading',
            'name' => 'Semi-Permanent Eyelash Extensions',
            'description' => 'Individual lashes applied one at a time to your own, mapped to your eye shape for length and fullness that lasts several weeks.',
            'duration_min' => 120,
            'buffer_min' => 15,
            'base_price' => 7500.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'LASH-LIFT',
            'category' => 'lashes-waxing-threading',
            'name' => 'Eyelash Lift & Curl Therapy',
            'description' => 'Your natural lashes lifted from the root and set into a lasting curl, opening up the eye with no extensions to maintain.',
            'duration_min' => 60,
            'buffer_min' => 10,
            'base_price' => 4500.00,
        ],
        [
            'sku' => 'WAX-BODY-HONEY',
            'category' => 'lashes-waxing-threading',
            'name' => 'Full Body Honey Waxing',
            'description' => 'Full body waxing with a warm honey formula, carried out in a private room to strict single-use hygiene standards.',
            'duration_min' => 120,
            'buffer_min' => 20,
            'base_price' => 5250.00,
        ],
        [
            'sku' => 'WAX-ARM-RICA',
            'category' => 'lashes-waxing-threading',
            'name' => 'Full Arm RICA Waxing',
            'description' => 'Full arm waxing using RICA\'s low-temperature formula, a gentler option for sensitive or easily reddened skin.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1350.00,
        ],
        [
            'sku' => 'WAX-FACE-FULL',
            'category' => 'lashes-waxing-threading',
            'name' => 'Full Face Waxing (includes Cooling Mask)',
            'description' => 'Complete facial waxing finished with a soothing cooling mask to settle any redness before you leave.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1125.00,
        ],
        [
            'sku' => 'THR-EYEBROW',
            'category' => 'lashes-waxing-threading',
            'name' => 'Eyebrow Precision Threading',
            'description' => 'Brows mapped and threaded to a clean, precise shape that suits your face rather than a passing trend.',
            'duration_min' => 15,
            'buffer_min' => 5,
            'base_price' => 150.00,
        ],
        [
            'sku' => 'THR-FACE-FULL',
            'category' => 'lashes-waxing-threading',
            'name' => 'Full Face Threading (with Eyebrow Shape)',
            'description' => 'Threading across the full face including a full brow shape, for an even finish with no wax on the skin at all.',
            'duration_min' => 30,
            'buffer_min' => 5,
            'base_price' => 600.00,
        ],

        // ─── Massage, Spa & Special Services (8) ──────────────────────────────────────────
        [
            'sku' => 'SPA-FULLBODY-LUX',
            'category' => 'massage-spa-specials',
            'name' => 'Full Body Luxury Spa & Massage',
            'description' => 'A full-body massage and spa ritual in our quiet treatment room, with pressure adjusted throughout to what your body actually needs.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 15750.00,
            'is_featured' => true,
        ],
        [
            'sku' => 'SPA-THAI',
            'category' => 'massage-spa-specials',
            'name' => 'Authentic Thai Body Therapy',
            'description' => 'Traditional Thai technique combining assisted stretching with firm pressure to release deep tension and restore mobility.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 12000.00,
        ],
        [
            'sku' => 'SPA-MOROCCAN',
            'category' => 'massage-spa-specials',
            'name' => 'Traditional Moroccan Body Scrub & Spa',
            'description' => 'A steam-and-scrub hammam ritual that lifts away dull skin and leaves the whole body noticeably softer and smoother.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 15750.00,
        ],
        [
            'sku' => 'SPA-HOTSTONE',
            'category' => 'massage-spa-specials',
            'name' => 'Volcanic Hot Stone Spa Therapy',
            'description' => 'Heated volcanic stones worked along the back and shoulders, letting warmth reach muscle that hands alone cannot fully release.',
            'duration_min' => 60,
            'buffer_min' => 15,
            'base_price' => 15750.00,
        ],
        [
            'sku' => 'SPEC-MEHNDI-SINGLE',
            'category' => 'massage-spa-specials',
            'name' => 'Single Side Artistic Mehndi / Henna',
            'description' => 'Hand-drawn mehndi on one side, from fine traditional motifs to more contemporary minimal patterns.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 375.00,
        ],
        [
            'sku' => 'HAIR-KERATIN-SHORT',
            'category' => 'massage-spa-specials',
            'name' => 'Short Hair Keratin Smoothing',
            'description' => 'A keratin smoothing treatment priced for short lengths, taming frizz and cutting daily styling time considerably.',
            'duration_min' => 120,
            'buffer_min' => 15,
            'base_price' => 8250.00,
        ],
        [
            'sku' => 'SPEC-BRIDAL-CONSULT',
            'category' => 'massage-spa-specials',
            'name' => 'Bridal Hair & Makeup Consultation',
            'description' => 'A dedicated sit-down before your wedding to plan the look, agree shades and lock the timeline — no application, just the planning.',
            'duration_min' => 30,
            'buffer_min' => 10,
            'base_price' => 1500.00,
        ],
        [
            'sku' => 'SPEC-NAIL-GEL',
            'category' => 'massage-spa-specials',
            'name' => 'Nail Art & Gel Extensions Package',
            'description' => 'Gel extensions sculpted to your chosen length and shape, finished with the nail art you pick during the appointment.',
            'duration_min' => 60,
            'buffer_min' => 10,
            'base_price' => 3500.00,
        ],
    ];

    public function run(): void
    {
        $categories = [];

        foreach (self::CATEGORIES as $slug => $category) {
            $categories[$slug] = ServiceCategory::updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $category['name'],
                    'stock_image_url' => $category['stock_image_url'],
                    'sort' => $category['sort'],
                    'is_active' => true,
                    'seo_title' => "{$category['name']} in Lahore | Looks Smart Beauty Salon",
                ],
            );
        }

        foreach (self::SERVICES as $index => $service) {
            Service::updateOrCreate(
                ['slug' => Str::slug($service['name'])],
                [
                    'service_category_id' => $categories[$service['category']]->id,
                    'sku' => $service['sku'],
                    'name' => $service['name'],
                    'description' => $service['description'],
                    'duration_min' => $service['duration_min'],
                    'buffer_min' => $service['buffer_min'],
                    'base_price' => $service['base_price'],
                    'is_featured' => $service['is_featured'] ?? false,
                    'is_active' => true,
                    'sort' => $index + 1,
                    'seo_title' => "{$service['name']} | Looks Smart Beauty Salon, Lahore",
                    'seo_description' => $service['description'],
                ],
            );
        }
    }
}
