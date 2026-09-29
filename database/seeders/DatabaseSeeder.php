<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BusinessHour;
use App\Models\CustomerProfile;
use App\Models\Gallery;
use App\Models\GalleryImage;
use App\Models\Review;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SplFileInfo;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * The salon's real trading window, Monday-Sunday. Shared by `seedBusinessHours()` (what the
     * public site advertises) and `seedStaff()` (what AvailabilityEngine can actually offer) so the
     * two can never drift apart and publish unbookable hours.
     */
    private const OPENING_TIME = '10:00';

    private const CLOSING_TIME = '21:00';

    public function run(): void
    {
        // ADMIN_SEED_PASSWORD must be set in .env for real deployments (see .env.example); a random
        // fallback here only covers a bare `migrate:fresh --seed` run with no .env value present, so
        // seeding never fails or silently produces a null/guessable admin password.
        $admin = User::factory()->create([
            'name' => 'Admin',
            'email' => 'lookssmartbeautysalon@gmail.com',
            'password' => env('ADMIN_SEED_PASSWORD') ?: Str::password(32),
        ]);

        $this->seedBusinessHours();
        $this->seedSettings();

        $staff = $this->seedStaff();
        $services = $this->seedCatalog($staff);
        $this->call(DealShowcaseSeeder::class);

        $customers = $this->seedCustomers();

        $this->call(RolesAndPermissionsSeeder::class);

        $bookings = $this->seedBookings($staff, $services, $customers);
        $this->seedReviews($bookings);
        $this->call(GoogleReviewSeeder::class);
        $this->call(BlogContentSeeder::class);
        $this->seedBridalGallery();
    }

    /**
     * A real "Looks Smart Brides" album from the bridal photographs in `public/images/`, with every
     * image enabled for the homepage slider.
     *
     * The gallery module has been fully built since Phase 10 but nothing ever seeded it, so both
     * `/gallery` and the new homepage slider had no database content at all — the slider only ever
     * exercised its `public/images/` fallback. This makes the PRIMARY (admin-curated) path the live
     * one, and leaves the fallback for what it is meant for: a fresh install, or an admin who has
     * un-ticked everything.
     *
     * Copies the source files onto the `public` disk because `gallery_images.image_path` is resolved
     * relative to that disk via `asset("storage/…")`, not from `public/images/`. Skipped entirely if
     * the photographs are not present, so a clone without them still seeds cleanly.
     */
    private function seedBridalGallery(): void
    {
        $sourceDirectory = public_path('images');

        if (! is_dir($sourceDirectory)) {
            return;
        }

        $photographs = collect(File::files($sourceDirectory))
            ->filter(fn (SplFileInfo $file) => in_array(
                strtolower($file->getExtension()),
                ['jpg', 'jpeg', 'png', 'webp'],
                true,
            ))
            ->sortBy(fn (SplFileInfo $file) => $file->getFilename())
            ->values();

        if ($photographs->isEmpty()) {
            return;
        }

        $gallery = Gallery::create([
            'title' => 'Looks Smart Brides',
            'slug' => 'looks-smart-brides',
            'category' => 'Bridal',
            'sort' => 1,
            'is_active' => true,
        ]);

        foreach ($photographs as $index => $photograph) {
            $path = 'gallery/' . Str::slug($photograph->getBasename('.' . $photograph->getExtension()))
                . '.' . strtolower($photograph->getExtension());

            // Idempotent: re-seeding overwrites the same deterministic path rather than piling up
            // randomly-named duplicates on the disk the way SecureUploadService would.
            Storage::disk('public')->put($path, File::get($photograph->getPathname()));

            GalleryImage::create([
                'gallery_id' => $gallery->id,
                'image_path' => $path,
                'caption' => 'Bridal makeup and styling by Looks Smart',
                'show_on_homepage' => true,
                'sort' => $index,
            ]);
        }
    }

    /**
     * The salon's real trading hours: Monday-Sunday, 10:00-21:00, no closed day. Kept deliberately
     * in sync with `seedStaff()`'s working hours below — `AvailabilityEngine` intersects the two, so
     * business hours wider than any staff member's shift would advertise slots that can never be
     * booked.
     */
    private function seedBusinessHours(): void
    {
        foreach (range(0, 6) as $weekday) {
            BusinessHour::create([
                'weekday' => $weekday,
                'open_time' => self::OPENING_TIME,
                'close_time' => self::CLOSING_TIME,
                'is_closed' => false, // Open all seven days.
            ]);
        }
    }

    private function seedSettings(): void
    {
        $settings = [
            ['key' => 'business.name', 'value' => 'Looks Smart Beauty Salon', 'group' => 'business'],
            ['key' => 'business.phone', 'value' => '+92 305 9833859', 'group' => 'business'],
            ['key' => 'business.email', 'value' => 'lookssmartbeautysalon@gmail.com', 'group' => 'business'],
            ['key' => 'business.address', 'value' => '46-B, Commercial Central Park Housing Scheme, Main Ferozpur Road, Lahore', 'group' => 'business'],
            ['key' => 'business.currency', 'value' => 'PKR', 'group' => 'business'],
            // Deliberately still UTC. The salon trades in Asia/Karachi, but this key feeds
            // AvailabilityEngine's slot math, so switching it is a booking-behaviour change that
            // needs its own decision and verification pass — not a side effect of a content sync.
            ['key' => 'business.timezone', 'value' => 'UTC', 'group' => 'business'],
            ['key' => 'business.social_facebook', 'value' => 'https://www.facebook.com/people/Looks-Smart-Beauty-Salon/61574517810379/', 'group' => 'business'],
            ['key' => 'business.social_instagram', 'value' => 'https://www.instagram.com/lookssmartbeautysalon/', 'group' => 'business'],
            // 31°19'01.4"N 74°23'21.5"E — Central Park Housing Scheme, Lahore. Consumed by
            // SeoService's GeoCoordinates JSON-LD and by the Contact page's map embed.
            ['key' => 'business.latitude', 'value' => 31.317056, 'group' => 'business'],
            ['key' => 'business.longitude', 'value' => 74.389306, 'group' => 'business'],
            // Ad hoc task 30: the width of each fixed slot in the salon-wide capacity grid — was the
            // old per-staff engine's 15-min stepping granularity, repurposed (same admin-editable
            // "Slot size (minutes)" field) now that slots are fixed-width rather than a sliding
            // per-service window. Ad hoc task 35 changed the default to 30 minutes (10:00 AM, 10:30,
            // 11:00, ...) per an explicit spec update from task 30/34's original 1-hour default.
            ['key' => 'booking.slot_minutes', 'value' => 30, 'group' => 'booking'],
            ['key' => 'booking.max_advance_days', 'value' => 60, 'group' => 'booking'],
            ['key' => 'booking.hold_minutes', 'value' => 5, 'group' => 'booking'],
            // Phase 7: minimum lead time before a booking can start, the cancellation/reschedule
            // policy window, and the tax rate applied server-side at price-quote time.
            ['key' => 'booking.min_lead_minutes', 'value' => 60, 'group' => 'booking'],
            ['key' => 'booking.cancellation_window_hours', 'value' => 24, 'group' => 'booking'],
            ['key' => 'booking.tax_rate', 'value' => 0, 'group' => 'booking'],
            // Ad hoc task 30: how many simultaneous active bookings (any staff) one fixed slot allows
            // before the public picker shows it as full/disabled.
            ['key' => 'booking.max_bookings_per_slot', 'value' => 3, 'group' => 'booking'],
        ];

        foreach ($settings as $setting) {
            Setting::create($setting);
        }
    }

    /** @return Collection<int, Staff> */
    private function seedStaff(): Collection
    {
        return Staff::factory()
            ->count(5)
            ->create()
            ->each(function (Staff $staff) {
                foreach (range(0, 6) as $weekday) { // Sunday-Saturday: the salon trades every day.
                    $staff->workingHours()->create([
                        'weekday' => $weekday,
                        'start_time' => self::OPENING_TIME,
                        'end_time' => self::CLOSING_TIME,
                    ]);
                }
            });
    }

    /**
     * Delegates the real catalog to SalonCatalogSeeder (5 categories, 25 bookable services ingested
     * from the salon's own service export) and then attaches staff, which stays here because the
     * staff roster is demo data while the catalog is genuine — the two have different lifecycles.
     *
     * @param Collection<int, Staff> $staff
     * @return Collection<int, Service>
     */
    private function seedCatalog(Collection $staff): Collection
    {
        $this->call(SalonCatalogSeeder::class);

        return Service::all()->each(
            fn (Service $service) => $service->staff()->attach($staff->random(random_int(2, 4))->pluck('id')),
        );
    }

    /** @return Collection<int, User> */
    private function seedCustomers(): Collection
    {
        return User::factory()
            ->count(80)
            ->create()
            ->each(function (User $customer) {
                CustomerProfile::factory()->create(['user_id' => $customer->id]);
            });
    }

    /**
     * Builds a pool of (staff_id, start datetime) slots across the last 30 and next 30 days,
     * then samples 200 — guaranteeing the (staff_id, starts_at) uniqueness constraint holds.
     *
     * @param Collection<int, Staff> $staff
     * @param Collection<int, Service> $services
     * @param Collection<int, User> $customers
     * @return Collection<int, Booking>
     */
    private function seedBookings(Collection $staff, Collection $services, Collection $customers): Collection
    {
        $slots = collect();

        foreach ($staff as $member) {
            foreach (range(-30, 29) as $dayOffset) {
                $date = Carbon::now()->addDays($dayOffset)->startOfDay();

                // No Sunday skip: the salon trades all seven days (see OPENING_TIME/CLOSING_TIME).
                foreach (range(0, 9) as $hourStep) { // 10:00 through 19:00, inside the real hours.
                    $slots->push([
                        'staff_id' => $member->id,
                        'starts_at' => $date->copy()->addHours(10 + $hourStep),
                    ]);
                }
            }
        }

        return $slots->shuffle()->take(200)->map(function (array $slot) use ($services, $customers) {
            $isGuest = fake()->boolean(30);
            $bookingServices = $services->random(random_int(1, 2));
            $duration = $bookingServices->sum('duration_min');
            $total = $bookingServices->sum('base_price');

            $booking = Booking::factory()->create([
                'staff_id' => $slot['staff_id'],
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['starts_at']->copy()->addMinutes($duration),
                'customer_id' => $isGuest ? null : $customers->random()->id,
                'guest_name' => $isGuest ? fake()->name() : null,
                'guest_email' => $isGuest ? fake()->safeEmail() : null,
                'guest_phone' => $isGuest ? fake()->phoneNumber() : null,
                'total' => $total,
            ]);

            foreach ($bookingServices as $service) {
                BookingItem::create([
                    'booking_id' => $booking->id,
                    'service_id' => $service->id,
                    'price_snapshot' => $service->base_price,
                    'duration_snapshot' => $service->duration_min,
                ]);
            }

            return $booking;
        })->values();
    }

    /** @param Collection<int, Booking> $bookings */
    private function seedReviews(Collection $bookings): void
    {
        $bookings
            ->where('status', 'completed')
            ->whereNotNull('customer_id')
            ->shuffle()
            ->take(40)
            ->each(function (Booking $booking) {
                Review::factory()->create([
                    'customer_id' => $booking->customer_id,
                    'booking_id' => $booking->id,
                    'service_id' => $booking->items()->first()?->service_id,
                ]);
            });
    }
}
