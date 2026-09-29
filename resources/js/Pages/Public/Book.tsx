import axios, { isAxiosError } from 'axios';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Check, Clock3, LoaderCircle, Plus, Search, Sparkles, Trash2 } from 'lucide-react';
import * as React from 'react';

import { CategoryPillBar } from '@/Components/CategoryPillBar';
import { LoadingDots } from '@/Components/LoadingDots';
import { SeoHead } from '@/Components/SeoHead';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import PublicLayout from '@/Layouts/PublicLayout';
import { clearCart, getCartItems, setCartItems } from '@/lib/cart';
import { formatCurrency } from '@/lib/currency';

interface Service {
  id: number;
  name: string;
  duration_min: number;
  price: string;
  category: string | null;
  category_id: number | null;
  image_url: string | null;
}
interface Category {
  id: number;
  name: string;
}
/**
 * Ad hoc task 30: the picker's slot shape is now a fixed-width, salon-wide capacity grid — no
 * `staff_id` at all (a real staff member is resolved invisibly, server-side, only once a slot is
 * actually held). `is_available` is `false` for a slot that's reached its configured booking cap;
 * it still appears in the list (rendered disabled), never silently removed.
 */
interface Slot {
  starts_at: string;
  ends_at: string;
  is_available: boolean;
}
interface BookProps {
  services: Service[];
  categories: Category[];
  authUser: { name: string; email: string } | null;
}

const SERVICES_TOP_ANCHOR = 'book-services-top';

const steps = ['Select services', 'Date, time & details'];

/**
 * The browser's own LOCAL calendar date as `YYYY-MM-DD` — deliberately NOT `date.toISOString()`,
 * which always returns the UTC date. For any timezone ahead of UTC (this salon is in Lahore,
 * Pakistan, UTC+5), the local calendar date is already the next day for roughly 5 hours out of
 * every 24 (from local midnight until UTC catches up) — during that window `toISOString().slice(0,
 * 10)` yields YESTERDAY relative to "now", which the backend correctly rejects as already in the
 * past (`AvailabilityEngine::getSlots()` returns an empty array for any date before today), so the
 * wizard's default date silently loaded a day that could never have slots — a real, reproducing
 * root cause of "No times found for this date" (ad hoc task 29 fix), not a backend/API bug at all.
 */
function localDateString(date: Date = new Date()): string {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

/** Ad hoc task 35: formats a raw minute total for the "Your Selection" card's Total Service Time —
 * "7 Hours" for an exact multiple of 60, "7 Hours 30 Mins" otherwise, "45 Mins" under an hour. */
function formatTotalDuration(totalMinutes: number): string {
  if (totalMinutes <= 0) return '0 Mins';
  const hours = Math.floor(totalMinutes / 60);
  const minutes = totalMinutes % 60;
  const hoursLabel = hours > 0 ? `${hours} ${hours === 1 ? 'Hour' : 'Hours'}` : '';
  const minutesLabel = minutes > 0 ? `${minutes} Mins` : '';
  return [hoursLabel, minutesLabel].filter(Boolean).join(' ');
}

/** Morning ends before noon, afternoon before 5pm, everything after is evening — used purely to
 * group the compact time-slot picker into labelled sections, not for any booking logic. */
function slotPeriod(iso: string): 'Morning' | 'Afternoon' | 'Evening' {
  const hour = new Date(iso).getHours();
  if (hour < 12) return 'Morning';
  if (hour < 17) return 'Afternoon';
  return 'Evening';
}

/**
 * A 2-step wizard (down from the original 5: services → staff → date → time → your details →
 * confirm). Staff selection is removed entirely — `AvailabilityEngine` already resolves a real,
 * working staff member per returned slot when `staff_id` is omitted from the availability request,
 * and `chooseSlot()` below reads that assignment straight off the chosen slot, exactly like the old
 * wizard's "Any available" option already did. Category pills filter by the real `ServiceCategory`
 * list the controller sends (never a client-derived list from whatever category strings happen to
 * already be present in `services`, which would silently omit a real category with zero services).
 */
export default function Book({ services, categories, authUser }: BookProps) {
  const [step, setStep] = React.useState(0);
  const [search, setSearch] = React.useState('');
  // Honours every repeated `?service=<id>` param that a "Book now"/"Add to Cart" link on the Home,
  // Services, Deals and service-detail pages passes (a deal's card can carry several — one per
  // bundled service), UNIONED with whatever was already in the cart from an earlier visit this same
  // tab (sessionStorage) — so adding a service from Home, browsing to Deals, and adding another deal
  // there both land in the same cart on /book rather than the second visit wiping out the first. Both
  // sources are validated against the real services list: a stale or hand-edited id is silently
  // dropped instead of poisoning the selection with one the quote endpoint would later reject.
  const [selected, setSelected] = React.useState<number[]>(() => {
    if (typeof window === 'undefined') return [];
    const fromUrl = new URLSearchParams(window.location.search).getAll('service').map(Number);
    const combined = [...getCartItems().map((item) => item.id), ...fromUrl].filter((id) =>
      services.some((service) => service.id === id),
    );
    return Array.from(new Set(combined));
  });

  // Keeps the shared cart in sync as the selection changes, so it survives navigating away and back,
  // and so the header badge/drawer (sibling components elsewhere in the tree) reflect it instantly —
  // written as real display items (name/price/thumbnail), not bare ids, so the redesigned sidebar and
  // the header drawer can render a real line-item list without an extra round trip.
  React.useEffect(() => {
    setCartItems(
      services
        .filter((service) => selected.includes(service.id))
        .map((service) => ({
          id: service.id,
          name: service.name,
          price: service.price,
          image_url: service.image_url,
        })),
    );
  }, [selected, services]);
  const [date, setDate] = React.useState(localDateString());
  const [slots, setSlots] = React.useState<Slot[]>([]);
  const [slot, setSlot] = React.useState<Slot | null>(null);
  const [promoCode, setPromoCode] = React.useState(() =>
    typeof window === 'undefined' ? '' : new URLSearchParams(window.location.search).get('code') ?? '',
  );
  const [holdToken, setHoldToken] = React.useState<string | null>(null);
  // The real staff member the SERVER resolved and locked for the active hold (ad hoc task 30) — the
  // picker itself no longer knows or cares which staff member serves a given slot, but release()/
  // store() calls still need to reference the exact same one the hold was acquired under.
  const [heldStaffId, setHeldStaffId] = React.useState<number | null>(null);
  // The salon-wide capacity "seat" claim the hold response also returns — a per-staff hold alone
  // can't enforce `max_bookings_per_slot` (two customers auto-resolved onto two different, both
  // genuinely-free staff would otherwise both sail past it), so this gets echoed back on release()
  // exactly like `holdToken`/`heldStaffId` already are.
  const [heldCapacitySeat, setHeldCapacitySeat] = React.useState<string | null>(null);
  const [seconds, setSeconds] = React.useState(0);
  const [form, setForm] = React.useState({
    name: authUser?.name ?? '',
    email: authUser?.email ?? '',
    phone: '',
    subject: '',
    notes: '',
  });
  const [busy, setBusy] = React.useState(false);
  const [error, setError] = React.useState('');
  const [complete, setComplete] = React.useState<string | null>(null);
  // Guards against a genuine race condition (ad hoc task 28 fix, reproduced via a real Playwright
  // loop of rapid date changes): `loadSlots()` has no built-in request cancellation, so switching
  // dates quickly can let an OLDER, slower response for a since-abandoned date arrive AFTER a newer
  // one and silently overwrite it — leaving the picker showing stale/empty slots (sometimes with
  // NEITHER a slot list nor the "No times found" message, since a stale response's own `finally`
  // could also clear `busy` after a newer request had already set it). Each `loadSlots()` call stamps
  // its own id here; a response only gets applied if it's still the most recent one requested.
  const slotsRequestIdRef = React.useRef(0);

  React.useEffect(() => {
    if (!seconds) return;
    const timer = window.setInterval(() => setSeconds((value) => Math.max(0, value - 1)), 1000);
    return () => window.clearInterval(timer);
  }, [seconds]);

  React.useEffect(
    () => () => {
      // `heldStaffId` is legitimately `null` when no single staff covers the selected combination
      // (ad hoc task 41) — `holdToken`/`slot` are the correct "is there an active hold" signal, set
      // together with `heldStaffId` in the same response, never independently of it.
      if (holdToken && slot) {
        void axios.delete('/api/booking/hold', {
          data: {
            staff_id: heldStaffId,
            starts_at: slot.starts_at,
            hold_token: holdToken,
            capacity_seat: heldCapacitySeat ?? undefined,
          },
        });
      }
    },
    [holdToken, slot, heldStaffId, heldCapacitySeat],
  );

  function toggleService(id: number) {
    setSelected((current) =>
      current.includes(id) ? current.filter((item) => item !== id) : [...current, id],
    );
  }

  async function loadSlots(forDate: string) {
    const requestId = ++slotsRequestIdRef.current;
    setBusy(true);
    setError('');
    try {
      const response = await axios.get('/api/booking/availability', {
        params: {
          service_ids: selected,
          date: forDate,
          timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        },
      });
      if (requestId !== slotsRequestIdRef.current) return; // superseded by a newer date change

      // Ad hoc task 30: `AvailabilityEngine` now returns one entry per fixed-width salon slot (not
      // one per staff member — the task-26 per-staff duplication this used to dedupe against no
      // longer exists, since slot generation is fully detached from individual staff schedules), each
      // carrying its own real `is_available` flag rather than being silently omitted when full.
      setSlots(response.data.slots as Slot[]);
    } catch {
      if (requestId !== slotsRequestIdRef.current) return;
      setError('We could not load availability. Please try again.');
    } finally {
      if (requestId === slotsRequestIdRef.current) setBusy(false);
    }
  }

  // Combined checkout step: entering it (or changing the date once inside it) loads real slots
  // automatically — no separate "Find times"/"Refresh times" click needed.
  function goToCheckout() {
    setStep(1);
    void loadSlots(date);
  }

  // Slot Lock Guard (carried over from the previous wizard): picking a new date must instantly drop
  // any stale "slot taken"/load-failure error and the previous date's slot list, and also release
  // whatever hold was active — otherwise an error/hold from date A survives a switch to date B.
  async function chooseDate(next: string) {
    if (holdToken && slot) {
      await axios.delete('/api/booking/hold', {
        data: {
          staff_id: heldStaffId,
          starts_at: slot.starts_at,
          hold_token: holdToken,
          capacity_seat: heldCapacitySeat ?? undefined,
        },
      });
      setHoldToken(null);
      setHeldStaffId(null);
      setHeldCapacitySeat(null);
      setSeconds(0);
    }
    setDate(next);
    setError('');
    setSlots([]);
    setSlot(null);
    void loadSlots(next);
  }

  /**
   * Ad hoc task 30: the hold request no longer names a staff member — the server resolves and locks
   * a real, genuinely-free one invisibly and hands its id back in the response, which is kept in
   * `heldStaffId` purely so the later release()/store() calls reference the exact same one. It ALSO
   * claims a salon-wide capacity seat (`heldCapacitySeat`) alongside the staff-level lock.
   */
  async function chooseSlot(next: Slot) {
    if (!next.is_available) return;

    setBusy(true);
    setError('');
    try {
      if (holdToken && slot) {
        await axios.delete('/api/booking/hold', {
          data: {
            staff_id: heldStaffId,
            starts_at: slot.starts_at,
            hold_token: holdToken,
            capacity_seat: heldCapacitySeat ?? undefined,
          },
        });
      }
      const response = await axios.post('/api/booking/hold', {
        service_ids: selected,
        starts_at: next.starts_at,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
      });
      setSlot(next);
      setHoldToken(response.data.hold_token);
      setHeldStaffId(response.data.staff_id);
      setHeldCapacitySeat(response.data.capacity_seat ?? null);
      setSeconds(response.data.expires_in);
    } catch (err) {
      setError(
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : 'That slot is no longer available.',
      );
    } finally {
      setBusy(false);
    }
  }

  /**
   * The old wizard's "Review" (quote) and "Confirm" screens, merged behind this one button — the
   * quote is still fetched server-side (`StoreBookingRequest` requires a real signed quote payload),
   * just without surfacing it as a separate step the customer has to click through.
   *
   * Releases the wizard's own UX-level hold before the real commit — `CreateBookingAction` acquires
   * its OWN Redis lock on the exact same staff+time as part of its 3-layer double-booking guard, and
   * that atomic SET NX collides with a still-active hold from this same session.
   */
  async function confirmBooking() {
    if (!slot) {
      setError('Please choose a date and time first.');
      return;
    }
    if (!form.name.trim() || !form.email.trim()) {
      setError('Please fill in your name and email.');
      return;
    }

    setBusy(true);
    setError('');

    try {
      if (holdToken) {
        await axios.delete('/api/booking/hold', {
          data: {
            staff_id: heldStaffId,
            starts_at: slot.starts_at,
            hold_token: holdToken,
            capacity_seat: heldCapacitySeat ?? undefined,
          },
        });
      }

      const quoteResponse = await axios.post('/api/booking/quote', {
        service_ids: selected,
        code: promoCode || undefined,
      });

      const response = await axios.post('/api/booking', {
        service_ids: selected,
        staff_id: heldStaffId ?? undefined,
        starts_at: slot.starts_at,
        timezone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        guest_name: form.name,
        guest_email: form.email,
        guest_phone: form.phone || undefined,
        subject: form.subject || undefined,
        notes: form.notes || undefined,
        quote: quoteResponse.data,
      });

      clearCart();
      setComplete(response.data.code);
    } catch (err) {
      setError(
        isAxiosError(err) && err.response?.data?.message
          ? err.response.data.message
          : 'We could not complete your booking. Please try again.',
      );
    } finally {
      setBusy(false);
    }
  }

  const selectedServices = React.useMemo(
    () => services.filter((service) => selected.includes(service.id)),
    [services, selected],
  );
  const liveTotal = React.useMemo(
    () => selectedServices.reduce((sum, service) => sum + Number(service.price), 0),
    [selectedServices],
  );
  // Ad hoc task 35: the raw sum of every selected service's own duration — a real, honest total (not
  // the padded/buffered block duration `CreateBookingAction` uses internally to schedule the real
  // appointment), so what the customer sees here matches what they'd naively expect from adding up
  // each service's own listed "X min" underneath it.
  const totalDurationMin = React.useMemo(
    () => selectedServices.reduce((sum, service) => sum + service.duration_min, 0),
    [selectedServices],
  );

  // Instant, client-side search — every active service is already loaded, so there is no reason to
  // round-trip the server per keystroke. Category filtering became stacked sections (below) rather
  // than a single-select filter, matching the same pattern now used on Services/Deals.
  const visibleServices = React.useMemo(() => {
    const query = search.trim().toLowerCase();
    return query ? services.filter((service) => service.name.toLowerCase().includes(query)) : services;
  }, [services, search]);

  const serviceSections = React.useMemo(
    () =>
      categories
        .map((category) => ({
          category,
          items: visibleServices.filter((service) => service.category_id === category.id),
        }))
        .filter((section) => section.items.length > 0),
    [categories, visibleServices],
  );

  const servicePills = React.useMemo(
    () =>
      serviceSections.map((section) => ({
        id: `book-category-${section.category.id}`,
        label: section.category.name,
      })),
    [serviceSections],
  );

  // Groups the returned slots into Morning/Afternoon/Evening sections for the compact picker,
  // preserving whichever periods actually have times rather than always rendering all 3.
  const slotsByPeriod = React.useMemo(() => {
    const groups: Record<'Morning' | 'Afternoon' | 'Evening', Slot[]> = {
      Morning: [],
      Afternoon: [],
      Evening: [],
    };
    for (const available of slots) groups[slotPeriod(available.starts_at)].push(available);
    return groups;
  }, [slots]);

  if (complete) {
    return (
      <>
        <Head title="Booking confirmed" />
        <section className="px-6 py-20">
          <div className="mx-auto max-w-xl text-center">
            <div className="bg-accent-500 text-ivory mx-auto flex size-14 items-center justify-center rounded-full">
              <Check />
            </div>
            <h1 className="mt-6 text-4xl">You’re all booked.</h1>
            <p className="text-ink-muted mt-3">
              Your confirmation code is <strong className="text-ink">{complete}</strong>.
            </p>
            <div className="mt-8 flex justify-center gap-3">
              {authUser && (
                <Button asChild variant="outline">
                  <Link href="/my-bookings">View my bookings</Link>
                </Button>
              )}
              <Button asChild variant="accent">
                <Link href="/">Return home</Link>
              </Button>
            </div>
          </div>
        </section>
      </>
    );
  }

  return (
    <>
      <SeoHead
        title="Book an Appointment"
        description="Choose your service, time, and details at Looks Smart Beauty Salon."
      />
      {/* `pb-24` (only while the mobile sticky summary bar below is actually showing) keeps the fixed
          bar from covering the tail end of the page's own content — e.g. the Notes field or the
          aside's own Confirm Booking button. `lg:pb-12` restores the section's normal bottom padding
          at the breakpoint where that bar is hidden. */}
      <section className={`px-6 py-12 lg:px-10 lg:pb-12 ${selectedServices.length > 0 ? 'pb-28' : ''}`}>
        <div className="mx-auto max-w-6xl">
          <p className="text-accent-700 text-sm font-semibold uppercase tracking-[0.18em]">
            Your visit
          </p>
          <h1 className="mt-3 text-5xl font-medium">Book an appointment</h1>

          <div className="mt-10 grid grid-cols-2 gap-2" role="group" aria-label="Booking progress">
            {steps.map((label, index) => (
              <div
                key={label}
                className={`border-t-2 pt-3 text-xs ${index <= step ? 'border-accent-500 text-ink' : 'border-border-soft text-ink-muted'}`}
              >
                <span className="font-semibold">0{index + 1}</span>
                <span className="ml-2">{label}</span>
              </div>
            ))}
          </div>

          {/* Both `grid-cols-1` (base) and the explicit `minmax(0,…)` in the `lg:` arbitrary value
              are load-bearing, not decorative: Tailwind's numbered `grid-cols-*` utilities emit
              `repeat(N, minmax(0, 1fr))` automatically, but a raw arbitrary value like
              `[1fr_320px]` does NOT get that same treatment — it compiles to a literal `1fr`, whose
              *content-based* minimum size can still force the track wider than its share of the
              grid. `minmax(0,1fr)` is what actually lets this column's content (the category pill
              bar's horizontally-scrolling row, in particular) scroll within its own bounds instead
              of stretching the whole grid — and the page itself — to fit it. Confirmed by measuring:
              omitting this blew a 375px-wide mobile viewport out to ~1130px, and even at the `lg:`
              breakpoint (1440px), the bare `1fr` alone still pushed the 320px sidebar ~165px past
              the right edge of the viewport (ad hoc task 25 fix — both were real, measured overflow
              bugs, not hypothetical ones). */}
          <div className="mt-10 grid grid-cols-1 gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
            {/* `order-2 lg:order-none` (paired with the same on `<aside>` below) puts "Your
                Selection" ABOVE the service list on mobile — a real reported issue: on a phone, the
                grid stacks to one column in DOM order, so the summary/Continue button sat below every
                service card, forcing a long scroll just to proceed (ad hoc task 26 fix). `lg:order-none`
                resets both back to plain source order at the `lg:` breakpoint, where the 2-column
                grid already places them side by side correctly regardless of order. */}
            <Card className="order-2 lg:order-none">
              <CardHeader>
                <CardTitle>{steps[step]}</CardTitle>
              </CardHeader>
              <CardContent>
                {step === 0 && (
                  <div id={SERVICES_TOP_ANCHOR} className="scroll-mt-24">
                    <div className="relative mb-4">
                      <Search
                        className="text-ink-muted absolute top-1/2 left-3 size-4 -translate-y-1/2"
                        aria-hidden="true"
                      />
                      <Input
                        aria-label="Search services"
                        value={search}
                        onChange={(event) => setSearch(event.target.value)}
                        placeholder="Search services, packages, or treatments..."
                        className="pl-9"
                      />
                    </div>

                    {servicePills.length > 0 && (
                      <CategoryPillBar
                        pills={servicePills}
                        topAnchorId={SERVICES_TOP_ANCHOR}
                        ariaLabel="Filter services by category"
                        className="mb-6"
                      />
                    )}

                    <div className="space-y-10">
                      {serviceSections.map(({ category, items }) => (
                        <div key={category.id} id={`book-category-${category.id}`} className="scroll-mt-40">
                          <h3 className="text-ink-muted text-xs font-semibold tracking-wider uppercase">
                            {category.name}
                          </h3>
                          {/* 3-column responsive grid (1 on mobile, 2 on tablet, 3 on desktop). */}
                          <div data-testid="service-grid" className="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            {items.map((service) => {
                              const isSelected = selected.includes(service.id);
                              return (
                                <div
                                  key={service.id}
                                  className={`flex flex-col rounded-lg border p-4 transition ${isSelected ? 'border-accent-600 bg-accent-50' : 'border-border-soft'}`}
                                >
                                  <span className="text-accent-700 text-xs uppercase">
                                    {service.category}
                                  </span>
                                  <strong className="mt-1 block text-lg leading-snug">
                                    {service.name}
                                  </strong>
                                  <span className="text-ink-muted mt-3 flex items-center justify-between text-sm">
                                    <span>
                                      <Clock3 className="mr-1 inline size-4" />
                                      {service.duration_min} min
                                    </span>
                                    {formatCurrency(service.price)}
                                  </span>
                                  <Button
                                    type="button"
                                    size="sm"
                                    variant={isSelected ? 'outline' : 'accent'}
                                    aria-pressed={isSelected}
                                    aria-label={`${service.name}: ${isSelected ? 'Added' : 'Add'}`}
                                    onClick={() => toggleService(service.id)}
                                    className="mt-4"
                                  >
                                    {isSelected ? (
                                      <>
                                        <Check className="size-4" aria-hidden="true" /> Added
                                      </>
                                    ) : (
                                      <>
                                        <Plus className="size-4" aria-hidden="true" /> Add
                                      </>
                                    )}
                                  </Button>
                                </div>
                              );
                            })}
                          </div>
                        </div>
                      ))}
                    </div>

                    {serviceSections.length === 0 && (
                      <p className="text-ink-muted py-10 text-center text-sm">
                        No services match that search.
                      </p>
                    )}
                  </div>
                )}

                {step === 1 && (
                  <div className="space-y-6">
                    <Button variant="ghost" size="sm" onClick={() => setStep(0)} className="-ml-2">
                      <ArrowLeft className="size-4" aria-hidden="true" /> Back to services
                    </Button>

                    <div>
                      <label className="text-sm font-medium" htmlFor="booking-date">
                        Choose a date
                      </label>
                      <Input
                        id="booking-date"
                        type="date"
                        min={localDateString()}
                        value={date}
                        onChange={(event) => void chooseDate(event.target.value)}
                        className="mt-2 max-w-xs"
                      />

                      {/* Compact, scrollable time-slot picker — grouped into Morning/Afternoon/
                          Evening chips instead of one long, repetitive multi-column grid. */}
                      <div className="border-border-soft mt-4 max-h-64 space-y-4 overflow-y-auto rounded-lg border p-3">
                        {busy && slots.length === 0 && (
                          <div className="flex justify-center py-6">
                            <LoadingDots label="Loading available times" />
                          </div>
                        )}
                        {(['Morning', 'Afternoon', 'Evening'] as const).map((period) =>
                          slotsByPeriod[period].length > 0 ? (
                            <div key={period}>
                              <p className="text-ink-muted text-xs font-semibold uppercase tracking-wider">
                                {period}
                              </p>
                              <div className="mt-2 flex flex-wrap gap-2">
                                {/* Ad hoc task 30: a slot at its configured booking cap is still
                                    rendered — never silently dropped from the list — as a disabled,
                                    visibly dimmed button labelled "Not Available", so the customer can
                                    see the salon operates that hour without being able to select it. */}
                                {slotsByPeriod[period].map((available) => {
                                  const timeLabel = new Date(available.starts_at).toLocaleTimeString([], {
                                    hour: 'numeric',
                                    minute: '2-digit',
                                  });
                                  const isChosen = slot?.starts_at === available.starts_at;

                                  return (
                                    <button
                                      type="button"
                                      key={available.starts_at}
                                      disabled={busy || !available.is_available}
                                      aria-pressed={isChosen}
                                      aria-label={available.is_available ? timeLabel : `${timeLabel}: Not available`}
                                      onClick={() => void chooseSlot(available)}
                                      className={`rounded-full border px-3.5 py-1.5 text-sm transition-colors ${
                                        !available.is_available
                                          ? 'border-border-soft text-ink-muted/50 cursor-not-allowed opacity-50'
                                          : `disabled:opacity-50 ${isChosen ? 'border-accent-600 bg-accent-50' : 'border-border-soft hover:border-accent-500'}`
                                      }`}
                                    >
                                      {timeLabel}
                                      {!available.is_available && (
                                        <span className="ml-1.5 text-xs">· Not Available</span>
                                      )}
                                    </button>
                                  );
                                })}
                              </div>
                            </div>
                          ) : null,
                        )}
                        {slots.length === 0 && !busy && (
                          <p className="text-ink-muted py-4 text-center text-sm">
                            No times found for this date.
                          </p>
                        )}
                      </div>

                      {seconds > 0 && (
                        <p className="text-accent-700 mt-4 text-sm">
                          Your time is held for {Math.floor(seconds / 60)}:
                          {String(seconds % 60).padStart(2, '0')}
                        </p>
                      )}
                    </div>

                    <div className="border-border-soft grid gap-4 border-t pt-6 sm:grid-cols-2">
                      <div>
                        <label className="text-sm font-medium" htmlFor="booking-name">
                          Full Name
                        </label>
                        <Input
                          id="booking-name"
                          value={form.name}
                          onChange={(event) => setForm({ ...form, name: event.target.value })}
                          required
                        />
                      </div>
                      <div>
                        <label className="text-sm font-medium" htmlFor="booking-phone">
                          Phone Number
                        </label>
                        <Input
                          id="booking-phone"
                          type="tel"
                          value={form.phone}
                          onChange={(event) => setForm({ ...form, phone: event.target.value })}
                        />
                      </div>
                      <div className="sm:col-span-2">
                        <label className="text-sm font-medium" htmlFor="booking-email">
                          Email Address
                        </label>
                        <Input
                          id="booking-email"
                          type="email"
                          value={form.email}
                          onChange={(event) => setForm({ ...form, email: event.target.value })}
                          required
                        />
                      </div>
                      <div className="sm:col-span-2">
                        <label className="text-sm font-medium" htmlFor="booking-subject">
                          Subject / Inquiry Topic{' '}
                          <span className="text-ink-muted font-normal">(optional)</span>
                        </label>
                        <Input
                          id="booking-subject"
                          value={form.subject}
                          onChange={(event) => setForm({ ...form, subject: event.target.value })}
                          placeholder="e.g. First-time visit, allergy question..."
                          className="mt-2"
                        />
                      </div>
                      <div className="sm:col-span-2">
                        <label className="text-sm font-medium" htmlFor="booking-notes">
                          Notes <span className="text-ink-muted font-normal">(optional)</span>
                        </label>
                        <Textarea
                          id="booking-notes"
                          value={form.notes}
                          onChange={(event) => setForm({ ...form, notes: event.target.value })}
                          placeholder="Anything the team should know before your visit?"
                          className="mt-2 min-h-20"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {error && (
                  <p role="alert" className="mt-5 rounded-md bg-red-50 p-3 text-sm text-red-700">
                    {error}
                  </p>
                )}
              </CardContent>
            </Card>

            {/* Sticky side panel — every selected service with its price, a live total, and the
                primary CTA attached directly underneath so the customer never has to scroll back
                down to proceed. */}
            {/* `static lg:sticky lg:top-28`: on mobile this sits at the top of the flow (per the
                `order-1` above) and scrolls away naturally with the page — only the category pill
                bar stays sticky there. Desktop keeps the existing sticky side-panel behavior
                unchanged (ad hoc task 27 fix). */}
            <aside className="border-border-soft bg-surface static order-1 h-fit rounded-lg border p-5 lg:sticky lg:top-28 lg:order-none">
              <div className="flex items-baseline justify-between">
                <p className="text-accent-700 text-xs font-semibold uppercase tracking-wider">
                  Your selection
                </p>
                {selectedServices.length > 0 && (
                  <p className="text-accent-700 text-xs font-semibold uppercase tracking-wider">
                    Price
                  </p>
                )}
              </div>
              {selectedServices.length ? (
                selectedServices.map((service) => (
                  <div key={service.id} className="mt-3 flex items-center gap-3">
                    <div className="bg-accent-50 flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-md">
                      {service.image_url ? (
                        <img src={service.image_url} alt="" className="size-full object-cover" />
                      ) : (
                        <Sparkles className="text-accent-600 size-4" aria-hidden="true" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{service.name}</p>
                      <p className="text-ink-muted text-xs">{service.duration_min} min</p>
                    </div>
                    <span className="shrink-0 text-right text-sm tabular-nums">
                      {formatCurrency(service.price)}
                    </span>
                    {/* Ad hoc task 34: lets a service be removed directly from the selection list
                        rather than only by scrolling back to its "Added" card and toggling it off. */}
                    <button
                      type="button"
                      aria-label={`Remove ${service.name} from your selection`}
                      onClick={() => toggleService(service.id)}
                      className="text-ink-muted hover:text-red-600 shrink-0 rounded-md p-1"
                    >
                      <Trash2 className="size-4" aria-hidden="true" />
                    </button>
                  </div>
                ))
              ) : (
                <p className="text-ink-muted mt-3 text-sm">Select a service to begin.</p>
              )}
              {slot && (
                <p className="border-border-soft text-ink-muted mt-5 border-t pt-4 text-sm">
                  {new Date(slot.starts_at).toLocaleString()}
                </p>
              )}
              {selectedServices.length > 0 && (
                <div className="border-border-soft mt-4 border-t pt-4">
                  {/* Ad hoc task 35: the raw sum of every selected service's own duration, placed
                      directly above the Total Amount it accompanies. */}
                  <p className="text-ink-muted flex justify-between text-sm">
                    <span>Total Service Time</span>
                    <span className="tabular-nums">{formatTotalDuration(totalDurationMin)}</span>
                  </p>
                  <p className="text-ink mt-1.5 flex justify-between font-semibold">
                    <span>Total Amount</span>
                    <span className="tabular-nums">{formatCurrency(String(liveTotal))}</span>
                  </p>
                </div>
              )}

              {step === 0 ? (
                <Button
                  variant="accent"
                  className="mt-5 w-full"
                  disabled={!selected.length}
                  onClick={goToCheckout}
                >
                  Continue
                </Button>
              ) : (
                <>
                  <div className="mt-5">
                    <label className="text-sm font-medium" htmlFor="booking-promo">
                      Promo Code
                    </label>
                    <Input
                      id="booking-promo"
                      value={promoCode}
                      onChange={(event) => setPromoCode(event.target.value)}
                      className="mt-2"
                    />
                  </div>
                  <Button
                    variant="accent"
                    className="mt-4 w-full"
                    disabled={busy || !slot || !form.name || !form.email}
                    onClick={() => void confirmBooking()}
                  >
                    {busy && <LoaderCircle className="animate-spin" />} Confirm Booking
                  </Button>
                </>
              )}
            </aside>
          </div>
        </div>
      </section>

      {/* Ad hoc task 34: a mobile-only sticky summary bar, fixed to the bottom of the screen. The
          full "Your Selection" card already sits at the top of the flow on mobile (`order-1`, task
          26), but a customer scrolled deep into a long service list or the date/time form still had
          to scroll all the way back up to see their live total or proceed — this small persistent bar
          keeps both always in view. Its own CTA mirrors whichever primary action the current step's
          own button performs, so it's a genuine shortcut, not a second, different control. `lg:hidden`
          matches this page's own already-established mobile/desktop split (`order-1 lg:order-none`,
          `static lg:sticky` on the aside above) rather than introducing a separate breakpoint that
          would leave a tablet-width gap where neither bar is active. */}
      {selectedServices.length > 0 && (
        <div className="border-border-soft bg-ivory fixed inset-x-0 bottom-0 z-40 border-t px-4 py-3 shadow-[0_-4px_16px_rgba(0,0,0,0.08)] lg:hidden">
          <div className="mx-auto flex max-w-6xl items-center justify-between gap-4">
            <div className="min-w-0">
              <p className="text-ink-muted text-xs">
                {selectedServices.length} {selectedServices.length === 1 ? 'service' : 'services'} selected
              </p>
              <p className="text-ink font-semibold tabular-nums">{formatCurrency(String(liveTotal))}</p>
            </div>
            <Button
              variant="accent"
              size="sm"
              className="shrink-0"
              disabled={step === 0 ? !selected.length : busy || !slot || !form.name || !form.email}
              onClick={() => (step === 0 ? goToCheckout() : void confirmBooking())}
            >
              {step === 0 ? 'Continue' : (
                <>
                  {busy && <LoaderCircle className="animate-spin" />} Confirm Booking
                </>
              )}
            </Button>
          </div>
        </div>
      )}
    </>
  );
}

Book.layout = (page: React.ReactNode) => <PublicLayout solidHeader>{page}</PublicLayout>;
