<?php

namespace Database\Seeders;

use App\Models\Review;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the homepage reviews carousel with Google-sourced reviews (`source = 'google'`).
 *
 * **Honesty note, read before assuming these are real**: this app has no Google Places/Business
 * Profile API credentials anywhere (confirmed — `integrations.google_maps_api_key` exists in
 * `SettingService::SCHEMA` but is unset, and no Google Places client is installed), so there is no
 * legitimate way to actually fetch this salon's real Google reviews. The task's own text
 * acknowledges this by saying "seed with real/realistic" content. These 12 reviews are AUTHORED
 * content written to read like genuine 5-star Google reviews for a Lahore salon (specific, varied
 * phrasing, real treatment names from the seeded catalog, no generic filler) — they are not scraped
 * or copied from any actual customer. If real Google reviews are ever synced (via the Places API,
 * once credentials exist), replace this seeder's rows rather than layering fabricated ones on top of
 * genuine ones — `source = 'google'` makes the two easy to tell apart and bulk-replace.
 *
 * Pre-approved and pre-published directly (no moderation queue): a seeded review is, by definition,
 * already reviewed by whoever wrote this seeder, unlike a real visitor-submitted review which still
 * needs `Admin\ReviewController::approve()`.
 */
class GoogleReviewSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->reviews() as $index => $review) {
            Review::updateOrCreate(
                ['source' => 'google', 'reviewer_name' => $review['name'], 'title' => $review['title']],
                [
                    'rating' => 5,
                    'body' => $review['body'],
                    'reviewer_category' => $review['category'],
                    'status' => 'approved',
                    // Spread across the last few months so `latest('published_at')` doesn't show 12
                    // reviews all dated the same instant.
                    'published_at' => Carbon::now()->subDays(3 + $index * 11)->subHours($index * 3),
                ],
            );
        }
    }

    /**
     * @return list<array{name: string, title: string, category: string, body: string}>
     */
    private function reviews(): array
    {
        return [
            [
                'name' => 'Amna Riaz',
                'title' => 'Keratin treatment exceeded expectations',
                'category' => 'Hair Treatments',
                'body' => 'Extremely impressed with the Hair Botox treatment here! My hair feels silky, soft, and so much healthier than before. The stylist explained every step and my frizz is genuinely gone weeks later. Highly recommended!',
            ],
            [
                'name' => 'Sana Malik',
                'title' => 'Best haircut I\'ve had in Lahore',
                'category' => 'Hair Treatments',
                'body' => 'Went in for a blow dry and left with the best haircut I\'ve had in years. The team really listens before they pick up the scissors, and the salon itself is calm and clean. Booked my next appointment before I even left.',
            ],
            [
                'name' => 'Hira Ahmed',
                'title' => 'X-Tenso smoothing, zero regrets',
                'category' => 'Hair Treatments',
                'body' => 'Was nervous about a smoothing treatment ruining my hair texture but the L\'Oréal X-Tenso here was handled so carefully. Three months in and it still looks natural, not stiff or fake. Worth every rupee.',
            ],
            [
                'name' => 'Fatima Sheikh',
                'title' => 'Gold Hydra Facial glow is real',
                'category' => 'Facials & Skin Care',
                'body' => 'My skin was looking so dull before my wedding events started and the Gold Hydra Facial completely turned it around. The therapist actually checked my skin type before choosing products instead of just running a standard routine. Glowing for days after.',
            ],
            [
                'name' => 'Mahnoor Iqbal',
                'title' => 'Gentle, thorough, and honest advice',
                'category' => 'Facials & Skin Care',
                'body' => 'What I appreciated most was that they told me which add-ons I actually needed instead of upselling everything on the menu. The polish + mask combo left my skin so smooth. Clean facility and very professional staff.',
            ],
            [
                'name' => 'Zara Nasir',
                'title' => 'My go-to for pre-event skin prep',
                'category' => 'Facials & Skin Care',
                'body' => 'I always book a facial here a few days before any big event and it never disappoints. This time I tried their full-face threading with the polish and my skin looked flawless under makeup the next day.',
            ],
            [
                'name' => 'Ayesha Khan',
                'title' => 'Bridal makeup that actually lasted the whole day',
                'category' => 'Bridal & Makeup',
                'body' => 'Booked the Bridal Day Package for my Barat and it was worth every minute of the 2-week advance booking. The makeup lasted through pictures, dinner, and dancing without needing a single touch-up. My artist was so patient with my vision.',
            ],
            [
                'name' => 'Noor Fatima',
                'title' => 'Engagement look was exactly what I wanted',
                'category' => 'Bridal & Makeup',
                'body' => 'Senior artist did my engagement makeup and it was elegant, not overdone like some places tend to go. She listened to my reference photos properly. Threading beforehand made such a difference to how clean my face looked in photos.',
            ],
            [
                'name' => 'Rabia Tariq',
                'title' => 'Party glam that photographs beautifully',
                'category' => 'Bridal & Makeup',
                'body' => 'Tried the Party Glam Duo package for a friend\'s mehndi and got so many compliments. The HD finish held up under the event lighting and camera flashes all night. Booking again for my own function next month.',
            ],
            [
                'name' => 'Komal Yousaf',
                'title' => 'Painless and professional laser sessions',
                'category' => 'Laser Hair Removal',
                'body' => 'Was hesitant about laser after a bad experience elsewhere, but the technician here explained the process, did a patch test, and the sessions were far more comfortable than I expected. Already seeing real reduction after a few visits.',
            ],
            [
                'name' => 'Sadia Bashir',
                'title' => 'Consistent results, clean equipment',
                'category' => 'Laser Hair Removal',
                'body' => 'Five sessions in and the results speak for themselves. What stood out was how seriously they take hygiene — fresh equipment covers every time and they actually track your progress between visits instead of just running you through.',
            ],
            [
                'name' => 'Warda Aslam',
                'title' => 'Finally a salon that doesn\'t overbook appointments',
                'category' => 'Laser Hair Removal',
                'body' => 'My laser sessions have always started on time here, no long waits like other places in Lahore. The staff is knowledgeable about aftercare too — genuinely helped with the sensitivity I had after my first couple of sessions.',
            ],
        ];
    }
}
