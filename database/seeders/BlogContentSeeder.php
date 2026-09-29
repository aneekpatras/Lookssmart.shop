<?php

namespace Database\Seeders;

use App\Models\Post;
use App\Models\PostCategory;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Replaces the previous `Post::factory()->count(15)` seeding, which produced fake-sentence titles,
 * plain-text bodies with no headings (so the article page's table-of-contents extraction and
 * heading typography had nothing real to render), no category on a single post, and no tags at
 * all — meaning the category filter tabs, tag pills and "trending" list all rendered empty against
 * factory data. This seeds genuine written articles instead.
 *
 * CATEGORIES: seeded from the admin panel task's own example list (Hair, Skin Care, Facial, Bridal,
 * Nails & Spa, Laser, Tips & Advice) rather than the blog listing task's filter-tab screenshot
 * description, which mixes in things like "Lahore" and "Treatments" that read as TAGS, not
 * categories — a post has exactly one category but many tags, so folding tag-like words into the
 * category list would have made the single-select category field misleading. Those words are
 * seeded as real TAGS instead and shown as pills on the cards, which is what they actually are.
 *
 * COVER IMAGES: only ONE real photograph exists in the project for this purpose
 * (`public/images/Blog · Treatments.avif`, the exact asset the task names), so it is used for the
 * one article it is genuinely relevant to (the flagship laser-hair-removal piece, which needs a
 * treatment-room-style photo). The other seven categories get a real Unsplash stock photo each —
 * the same already-CSP-allowed, already-established pattern this project uses for service-category
 * imagery — rather than reusing one unrelated photo across every article or leaving the rest with
 * no image at all.
 */
class BlogContentSeeder extends Seeder
{
    /**
     * name => stock cover image URL. `null` for Laser, since that category's one post uses the
     * real local photograph instead.
     *
     * @var array<string, string|null>
     */
    private const CATEGORIES = [
        'Hair' => 'https://images.unsplash.com/photo-1522337360788-8b13dee7a37e?w=900&h=500&fit=crop',
        'Skin Care' => 'https://images.unsplash.com/photo-1570172619644-dfd03ed5d881?w=900&h=500&fit=crop',
        'Facial' => 'https://images.unsplash.com/photo-1512290923902-8a9f81dc236c?w=900&h=500&fit=crop',
        'Bridal' => 'https://images.unsplash.com/photo-1487412947147-5cebf100ffc2?w=900&h=500&fit=crop',
        'Nails & Spa' => 'https://images.unsplash.com/photo-1604654894610-df63bc536371?w=900&h=500&fit=crop',
        'Laser' => null,
        'Tips & Advice' => 'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=900&h=500&fit=crop',
    ];

    private const TAGS = [
        'Lahore', 'Treatments', 'Beauty Tips', 'Hair Care', 'Skincare', 'Bridal Makeup', 'Aftercare',
    ];

    public function run(): void
    {
        $author = User::first();
        $categories = [];

        foreach (self::CATEGORIES as $name => $coverUrl) {
            $categories[$name] = PostCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name],
            );
        }

        $tags = collect(self::TAGS)->mapWithKeys(
            fn (string $name) => [$name => Tag::updateOrCreate(['slug' => Str::slug($name)], ['name' => $name])],
        );

        foreach ($this->articles() as $article) {
            $post = Post::updateOrCreate(
                ['slug' => $article['slug']],
                [
                    'post_category_id' => $categories[$article['category']]->id,
                    'title' => $article['title'],
                    'excerpt' => $article['excerpt'],
                    'body' => $article['body'],
                    // No local upload for seeded content — the external URL below is what
                    // actually renders; see the migration and Post model for why both columns
                    // exist and PublicWebsiteController::postCoverUrl() for the precedence.
                    'cover_image_path' => null,
                    'cover_image_url' => $article['cover_path'] ?? self::CATEGORIES[$article['category']],
                    'seo_title' => "{$article['title']} | Looks Smart Beauty Salon",
                    'seo_description' => $article['excerpt'],
                    'status' => 'published',
                    'published_at' => now()->subDays($article['days_ago']),
                    'author_id' => $author?->id,
                ],
            );

            $post->tags()->sync(collect($article['tags'])->map(fn (string $name) => $tags[$name]->id));
        }
    }

    /**
     * @return list<array{slug: string, title: string, category: string, tags: list<string>, excerpt: string, body: string, days_ago: int, cover_path?: string}>
     */
    private function articles(): array
    {
        return [
            [
                'slug' => 'laser-hair-removal-in-lahore-your-questions-answered-honestly',
                'title' => 'Laser Hair Removal in Lahore: Your Questions Answered Honestly',
                'category' => 'Laser',
                'tags' => ['Lahore', 'Treatments', 'Aftercare'],
                'excerpt' => 'Laser hair removal is one of the most asked-about treatments at Looks Smart — here is what it actually involves, honestly, with no exaggerated claims.',
                'days_ago' => 3,
                // The exact asset named in the task, used for the article it genuinely fits.
                'cover_path' => '/images/' . rawurlencode('Blog · Treatments.avif'),
                'body' => <<<'HTML'
                    <p>Laser hair removal is one of the most asked-about treatments at Looks Smart, and also one of the most misunderstood. Clients come in having read wildly different things online — that it is permanent after one session, that it does not work on darker skin, that it is unbearably painful. None of that is quite true. Here is what we actually tell clients in the consultation room, without the marketing gloss.</p>

                    <h2>Does Laser Hair Removal Work on Pakistani Skin?</h2>
                    <p>Yes — but the technology matters more than the marketing. Laser hair removal targets the pigment (melanin) in the hair follicle, which means the biggest technical challenge is telling hair pigment apart from skin pigment. On medium-to-deeper skin tones, an older or poorly-calibrated laser can struggle with exactly that distinction, which is where the "it doesn't work on darker skin" myth comes from.</p>
                    <p>In practice, the right wavelength and a properly trained operator make this a non-issue for the vast majority of clients we see in Lahore. We assess your specific skin tone and hair colour at consultation and set the machine accordingly — a blanket "one setting for everyone" approach is exactly what causes disappointing results or, worse, burns.</p>

                    <h2>Is Laser Hair Removal Painful?</h2>
                    <p>Most clients describe it as a warm snap against the skin, closer to a rubber band flick than a burn. It is more noticeable on thinner-skinned areas — the upper lip and underarms tend to register more than the legs or arms. It is not comfortable, but it is not the ordeal some clients expect either, and it is far quicker per pulse than waxing is per strip.</p>

                    <h2>How Many Sessions Do You Need?</h2>
                    <p>Hair grows in cycles, and laser only affects follicles that are actively growing at the time of treatment — which is why a single session was never going to be enough, regardless of what a machine's marketing claims. Most clients need <strong>6 to 8 sessions</strong>, spaced 4 to 6 weeks apart, to catch enough of the growth cycle for a lasting reduction. Coarse, dark hair typically responds faster than fine or lighter hair.</p>

                    <h2>Is It Safe?</h2>
                    <p>Done properly, yes. "Done properly" is doing real work in that sentence, so here is what we actually check before and after every session:</p>
                    <ul>
                        <li>A real skin-tone and hair-colour assessment before the very first session — not a five-minute glance, an actual conversation about your skin's history, including any recent sun exposure or tanning.</li>
                        <li>A patch test on a small area if you have never had laser before, or if it has been a long gap since your last session.</li>
                        <li>No treatment over active breakouts, cold sores, sunburn, or recently tattooed skin in the treatment area.</li>
                        <li>A cooling gel or device used throughout, not skipped to save time.</li>
                        <li>Clear aftercare instructions given verbally AND in writing before you leave — not assumed you already know them.</li>
                    </ul>

                    <h2>What Should You Avoid Before and After Each Session?</h2>
                    <p><strong>Before your appointment:</strong></p>
                    <ul>
                        <li>Avoid sun exposure and tanning (including self-tan) on the treatment area for at least two weeks — sun-affected skin cannot be safely treated.</li>
                        <li>Do not wax, pluck or use hair-removal cream for at least four weeks beforehand — the laser needs the hair root intact to target it.</li>
                        <li>Shave the area 24 hours before your session rather than arriving unshaved.</li>
                    </ul>
                    <p><strong>After your appointment:</strong></p>
                    <ul>
                        <li>Avoid sun exposure on the treated area for two weeks, and wear SPF 30 or higher after that.</li>
                        <li>Skip hot showers, saunas and swimming pools for 24-48 hours — heat and chlorine both irritate freshly treated skin.</li>
                        <li>Do not wax or pluck between sessions; shaving is fine if regrowth appears.</li>
                        <li>Some redness and slight puffiness around the follicle is normal for a few hours — a cool compress settles it quickly.</li>
                    </ul>

                    <h2>How to Book Laser Hair Removal at Looks Smart Lahore</h2>
                    <p>Every laser client starts with a real consultation, not a straight booking — we look at your skin and hair in person before agreeing a session plan, and we will tell you plainly if laser is not the right option for you rather than book you in anyway.</p>

                    <blockquote>Ready to find out if laser is right for you? Book a consultation with our team, or message us on WhatsApp with a photo of the area and we will talk you through it honestly before you commit to anything.</blockquote>
                    HTML,
            ],

            [
                'slug' => 'how-to-take-care-of-your-hair-in-lahores-weather',
                'title' => "How to Take Care of Your Hair in Lahore's Weather: A Practical Guide",
                'category' => 'Hair',
                'tags' => ['Lahore', 'Hair Care', 'Beauty Tips'],
                'excerpt' => "Lahore's heat, humidity, and dust don't ask nicely before they damage your hair. Here is what actually helps, season by season.",
                'days_ago' => 6,
                'body' => <<<'HTML'
                    <p>Lahore's climate is genuinely hard on hair — long, humid summers, a dusty pre-monsoon stretch, and a short but harsh dry winter. Most of the damage we see at the salon is not from any single bad decision, it is from the weather quietly working against a routine that was never adjusted for it.</p>

                    <h2>Summer: fighting frizz and oil together</h2>
                    <p>Humidity swells the hair cuticle, which is what causes frizz — and Lahore's summer humidity is relentless. A lightweight, silicone-free serum on damp hair helps seal the cuticle before it has the chance to absorb moisture from the air. Wash less often than you think you need to; over-washing strips natural oils, which paradoxically makes the scalp produce even more oil to compensate.</p>

                    <h2>Dust and pollution</h2>
                    <p>A cleansing (clarifying) shampoo once every one to two weeks removes the build-up that ordinary shampoo leaves behind — dust, pollution particles and product residue all sit on the hair shaft and dull its shine over time. Do not use a clarifying shampoo more often than that, though; it will strip colour-treated hair unnecessarily fast.</p>

                    <h2>Winter dryness</h2>
                    <p>Lahore's winter is short but genuinely drying, especially with indoor heating. Swap a lightweight summer conditioner for a richer one, and consider a weekly hair-oil treatment before washing rather than after — oil applied to dry hair before a wash protects the cuticle during shampooing, which is the most damaging part of the routine.</p>

                    <blockquote>If your hair has been struggling with the season, a Hot Oil Scalp Massage or a proper Hair Botox Restorative Treatment resets things far faster than switching shampoos alone. Ask our team which suits your hair type.</blockquote>
                    HTML,
            ],

            [
                'slug' => 'why-a-hydrafacial-is-the-most-popular-treatment-right-now',
                'title' => 'Why a HydraFacial Is the Most Popular Treatment Right Now — and Whether It Is Right for You',
                'category' => 'Facial',
                'tags' => ['Skincare', 'Treatments', 'Beauty Tips'],
                'excerpt' => 'HydraFacial has become the treatment everyone asks for by name. Here is what it actually does, and who it genuinely suits.',
                'days_ago' => 10,
                'body' => <<<'HTML'
                    <p>We get more requests for a "HydraFacial" by name than for almost any other treatment, which is unusual — most clients ask what a facial does rather than which brand of device performed it. It is worth explaining what makes it different from a standard facial, because the popularity is genuinely earned, not just a marketing trend.</p>

                    <h2>What actually happens during the treatment</h2>
                    <p>A HydraFacial combines cleansing, gentle exfoliation, extraction and hydration in one continuous device pass, using a vortex-style tip rather than manual extraction. That is the real difference from a manual facial: extractions are suction-based rather than pressed out by hand, which is both gentler on the skin and more consistent across the whole face.</p>

                    <h2>Who it suits</h2>
                    <p>Almost every skin type, which is part of why it has become the default recommendation. It is gentle enough for sensitive skin that reacts badly to more aggressive peels, but still delivers a visible, immediate glow — which is exactly why it has become the pre-event facial of choice.</p>

                    <h2>What it will not do</h2>
                    <p>Honestly: it is not a substitute for a course of treatment on deep-set pigmentation or significant acne scarring. It is excellent maintenance and an excellent reset, but if you are dealing with a specific skin concern, we will usually recommend it alongside a more targeted treatment rather than instead of one.</p>
                    HTML,
            ],

            [
                'slug' => 'the-truth-about-balayage-hair-in-pakistan',
                'title' => 'The Truth About Balayage Hair in Pakistan (And How to Get It Right)',
                'category' => 'Hair',
                'tags' => ['Hair Care', 'Beauty Tips'],
                'excerpt' => 'Balayage is one of the most requested colour looks in Pakistan right now — and one of the most frequently done incorrectly. Here is what to actually ask for.',
                'days_ago' => 14,
                'body' => <<<'HTML'
                    <p>Balayage requests have grown enormously over the last few years, but a lot of what gets delivered under that name is closer to a set of ombré highlights than genuine balayage. The difference matters, and it is worth understanding before you book.</p>

                    <h2>What balayage actually is</h2>
                    <p>Balayage is a hand-painted, freehand colour technique — the colourist applies lightener directly to sections of hair with no foil, building depth gradually from mid-lengths to ends. Done properly, it grows out with no harsh line, because there was never a hard boundary in the first place.</p>

                    <h2>Why it is so often done wrong</h2>
                    <p>Foiled highlights are faster to apply and more predictable, so a rushed or under-trained colourist will often produce a foiled result and call it balayage. The tell is usually a visible line where the colour starts — genuine balayage should fade in gradually, not switch on abruptly partway down the hair.</p>

                    <h2>What to ask your colourist</h2>
                    <p>Ask specifically whether the technique is hand-painted or foiled, and ask to see examples of the colourist's own previous balayage work rather than a general portfolio. A confident colourist will not mind either question.</p>
                    HTML,
            ],

            [
                'slug' => 'four-hand-massage-for-the-most-relaxing-hour-of-your-life',
                'title' => 'Four Hand Massage: The Most Relaxing Hour of Your Week',
                'category' => 'Nails & Spa',
                'tags' => ['Treatments', 'Aftercare'],
                'excerpt' => 'Two therapists, one synchronised technique, and genuinely deeper muscle release than a single-therapist massage can achieve.',
                'days_ago' => 18,
                'body' => <<<'HTML'
                    <p>A four-hand massage is exactly what it sounds like — two therapists working in synchronised technique across your back and shoulders at once. It is not simply "twice as much massage"; the synchronisation is what actually changes the effect.</p>

                    <h2>Why synchronisation matters</h2>
                    <p>Two therapists mirroring each other's movement on either side of the spine creates an even, balanced pressure that is genuinely difficult for one therapist to replicate alone, since a single therapist naturally favours one side while reaching across the body. Clients consistently describe it as deeper without being harder — the pressure feels distributed rather than concentrated.</p>

                    <h2>Who it suits</h2>
                    <p>Anyone dealing with real shoulder and upper-back tension, or anyone who simply wants the most complete relaxation experience we offer. It pairs particularly well with a Volcanic Hot Stone Spa Therapy booked as a follow-up session later the same week.</p>
                    HTML,
            ],

            [
                'slug' => 'seven-skin-mistakes-pakistani-women-make-in-summer',
                'title' => '7 Skin Mistakes Pakistani Women Make in Summer (And How to Fix Them)',
                'category' => 'Skin Care',
                'tags' => ['Skincare', 'Beauty Tips', 'Lahore'],
                'excerpt' => "Summer skin problems are rarely one dramatic mistake — they're usually a handful of small habits stacking up. Here are the ones we see most.",
                'days_ago' => 21,
                'body' => <<<'HTML'
                    <p>Summer in Lahore is hard on skin — heat, humidity, dust and long hours outdoors all compound each other. Most of the summer skin complaints we hear at the salon trace back to a handful of habits, not one dramatic cause.</p>

                    <h2>1. Skipping sunscreen on cloudy or overcast days</h2>
                    <p>UV exposure does not stop when the sun is hidden behind cloud — most UV passes straight through. Apply SPF every single day, not just on visibly sunny ones.</p>

                    <h2>2. Over-exfoliating to "control" oil</h2>
                    <p>Stripping the skin more aggressively when it feels oily usually backfires — it damages the moisture barrier, which then produces even more oil to compensate. Twice a week is enough for most skin types.</p>

                    <h2>3. Heavy, occlusive moisturiser in humid weather</h2>
                    <p>A rich winter moisturiser applied in July traps heat and sweat under the skin, which is a common cause of summer breakouts. Switch to a lighter, gel-based formula for the season.</p>

                    <h2>4. Not reapplying sunscreen</h2>
                    <p>One morning application does not last a full day outdoors — SPF needs reapplying roughly every two to three hours of real sun exposure to stay effective.</p>

                    <h2>5. Popping heat-related breakouts</h2>
                    <p>Summer breakouts are often heat rash or clogged pores rather than true acne, and picking at them in humid weather significantly raises the risk of scarring and infection.</p>

                    <h2>6. Ignoring the neck and hands</h2>
                    <p>Sunscreen habitually stops at the jawline. Both the neck and the backs of the hands show sun damage earlier than the face does, precisely because they are the most commonly skipped.</p>

                    <h2>7. Waiting too long between facials</h2>
                    <p>Summer skin accumulates congestion faster than winter skin does. A monthly facial through the hot months keeps pores genuinely clear rather than just superficially clean.</p>
                    HTML,
            ],

            [
                'slug' => 'bridal-makeup-in-lahore-what-every-bride-needs-to-know-before-booking',
                'title' => 'Bridal Makeup in Lahore: What Every Bride Needs to Know Before Booking',
                'category' => 'Bridal',
                'tags' => ['Bridal Makeup', 'Lahore', 'Beauty Tips'],
                'excerpt' => 'Planning your bridal look in Lahore? Start earlier than you think you need to, and ask these questions before you commit to an artist.',
                'days_ago' => 25,
                'body' => <<<'HTML'
                    <p>Bridal season in Lahore books up fast, and the single biggest mistake we see brides make is starting the process too late. Here is what actually matters when planning a bridal look, based on years of Barat, Walima and Nikkah bookings.</p>

                    <h2>Book your trial early — earlier than feels necessary</h2>
                    <p>A trial should happen at least four to six weeks before the event, not the week before. It gives time to adjust anything that does not photograph the way you expected, and to book a second trial if the first genuinely needs rethinking.</p>

                    <h2>Bring your outfit and jewellery to the trial</h2>
                    <p>Makeup that looks perfect against a plain top can read completely differently under real jewellery and against your actual outfit colour. Bring both, or at least clear photographs, so the artist can genuinely match the look to the event rather than guess.</p>

                    <h2>Ask about touch-up plans for a long day</h2>
                    <p>A Barat day in particular runs long, with multiple outfit changes and hours of photography. Ask in advance whether touch-ups are included and what products are being used underneath — long-wear, humidity-resistant bases matter enormously in Lahore's climate specifically.</p>

                    <h2>Have an honest conversation about the look you actually want</h2>
                    <p>The best bridal results come from a genuine conversation, not a rigid reference photo followed exactly regardless of your features or skin tone. Bring inspiration, but stay open to what your artist actually recommends for you.</p>

                    <blockquote>Considering your bridal look for an upcoming event? Book a Bridal Hair &amp; Makeup Consultation before your date — no application, just a proper plan.</blockquote>
                    HTML,
            ],

            [
                'slug' => 'why-lahore-women-are-switching-to-lash-lifts-and-never-going-back',
                'title' => 'Why Lahore Women Are Switching to Lash Lifts (and Never Going Back)',
                'category' => 'Tips & Advice',
                'tags' => ['Beauty Tips', 'Lahore', 'Treatments'],
                'excerpt' => 'No extensions, no daily mascara, no maintenance appointments every two weeks. Here is what a lash lift actually involves.',
                'days_ago' => 29,
                'body' => <<<'HTML'
                    <p>Lash extensions get most of the attention, but a growing number of clients at Looks Smart are choosing a lash lift instead — and once they try it, they rarely go back to extensions.</p>

                    <h2>What a lash lift actually does</h2>
                    <p>Unlike extensions, which add synthetic lashes to your own, a lift uses only your natural lashes — they are set on a small silicone shield and a lifting lotion curls them from the root. There is nothing artificial added, and nothing to infill later.</p>

                    <h2>Why it suits a lower-maintenance routine</h2>
                    <p>A lift lasts six to eight weeks and needs no infills, no special cleansers, and no avoiding oil-based products the way extensions do. You wake up with open, lifted eyes and can go straight to your normal routine.</p>

                    <h2>Who should choose a lift over extensions</h2>
                    <p>Anyone with naturally decent lash length who mainly wants lift and curl rather than dramatic added volume, or anyone who found extensions high-maintenance and is looking for a simpler alternative.</p>
                    HTML,
            ],
        ];
    }
}
