import { BadgeCheck, Star } from 'lucide-react';
import * as React from 'react';

import { Card, CardContent } from '@/Components/ui/card';

export interface ReviewShowcase {
  id: number;
  rating: number;
  title: string | null;
  body: string;
  name: string;
  category: string | null;
  source: string;
}

const AUTO_ADVANCE_MS = 5000;

function useVisibleCount(): number {
  const [visibleCount, setVisibleCount] = React.useState(1);

  React.useEffect(() => {
    const query = window.matchMedia('(min-width: 768px)');
    const apply = () => setVisibleCount(query.matches ? 3 : 1);

    apply();
    query.addEventListener('change', apply);

    return () => query.removeEventListener('change', apply);
  }, []);

  return visibleCount;
}

/** Mirrors `Home.tsx`'s `HeroBackground` — `matchMedia` with a change listener, not framer-motion's
 * one-shot `useReducedMotion()`. */
function useReducedMotionPreference(): boolean {
  const [reduced, setReduced] = React.useState(false);

  React.useEffect(() => {
    const query = window.matchMedia('(prefers-reduced-motion: reduce)');
    const apply = () => setReduced(query.matches);

    apply();
    query.addEventListener('change', apply);

    return () => query.removeEventListener('change', apply);
  }, []);

  return reduced;
}

/**
 * Card-based star rendering reused from the admin Reviews Index's own `Stars` component pattern
 * (`resources/js/Pages/Admin/CRM/Reviews/Index.tsx`) — lucide `Star` icons, not the unicode
 * `★`/`☆` characters the old static grid used, so the rating reads correctly to a screen reader via
 * `aria-hidden` on the icons plus one real text label.
 */
function ReviewStars({ rating }: { rating: number }) {
  return (
    <span className="text-accent-500 flex items-center gap-0.5" aria-hidden="true">
      {[...Array(5)].map((_, index) => (
        <Star key={index} className={`size-4 ${index < rating ? 'fill-current' : 'opacity-25'}`} />
      ))}
    </span>
  );
}

function ReviewCard({ review }: { review: ReviewShowcase }) {
  return (
    <Card
      data-carousel-item
      className="border-border-soft bg-surface shadow-soft flex h-full flex-col"
    >
      <CardContent className="flex h-full flex-col p-6">
        <div className="flex items-center justify-between gap-3">
          <ReviewStars rating={review.rating} />
          <span className="sr-only">{review.rating} out of 5 stars</span>
          {review.source === 'google' && (
            <span
              className="text-ink-muted inline-flex items-center gap-1 text-xs font-medium"
              title="Google Verified Review"
            >
              <BadgeCheck className="text-accent-600 size-3.5" aria-hidden="true" /> Google
              Verified
            </span>
          )}
        </div>

        {/* `line-clamp-6` caps card height so a longer review can't make one page of 3 cards taller
            than another and shift the layout when the carousel advances every 5s — the task's own
            "no layout jumps" requirement. */}
        <p className="text-ink mt-4 line-clamp-6 flex-1 text-lg leading-7">&ldquo;{review.body}&rdquo;</p>

        <div className="mt-5">
          <p className="text-ink text-sm font-medium">{review.name}</p>
          {review.category && <p className="text-ink-muted text-xs">{review.category}</p>}
        </div>
      </CardContent>
    </Card>
  );
}

export function ReviewsCarousel({ reviews }: { reviews: ReviewShowcase[] }) {
  const visibleCount = useVisibleCount();
  const reducedMotion = useReducedMotionPreference();
  const [start, setStart] = React.useState(0);
  const [paused, setPaused] = React.useState(false);

  // "Adjusting state when a prop changes" (the React-documented pattern — compare against a value
  // stored from the previous render and call `setState` directly in the render body, not inside an
  // effect) rather than an effect-based reset: when a genuinely new review is prepended (the Write a
  // Review modal's "appears instantly" requirement), jump back to page 1 so it is immediately
  // visible, instead of leaving `start` pointing at whatever index it used to be mid-array.
  const [lastKnownFirstId, setLastKnownFirstId] = React.useState(reviews[0]?.id);
  if (reviews[0]?.id !== lastKnownFirstId) {
    setLastKnownFirstId(reviews[0]?.id);
    setStart(0);
  }

  React.useEffect(() => {
    if (paused || reviews.length <= visibleCount) return;

    const interval = window.setInterval(() => {
      setStart((current) => (current + visibleCount) % reviews.length);
    }, AUTO_ADVANCE_MS);

    return () => window.clearInterval(interval);
  }, [paused, reviews.length, visibleCount]);

  if (reviews.length === 0) return null;

  // A page-size change (viewport crossing the md breakpoint) can leave `start` mid-page — align
  // down to the nearest page boundary for THIS render rather than storing corrected state, so no
  // extra effect/setState round-trip is needed just to keep the pagination dots in sync.
  const alignedStart = start - (start % visibleCount);

  const visibleReviews = Array.from(
    { length: Math.min(visibleCount, reviews.length) },
    (_, index) => reviews[(alignedStart + index) % reviews.length]!,
  );

  return (
    <div
      className="relative"
      onMouseEnter={() => setPaused(true)}
      onMouseLeave={() => setPaused(false)}
      onFocus={() => setPaused(true)}
      onBlur={() => setPaused(false)}
    >
      <div
        key={alignedStart}
        className={`grid gap-5 md:grid-cols-3 ${reducedMotion ? '' : 'reviews-fade'}`}
        role="group"
        aria-label="Client reviews"
        aria-live="polite"
      >
        {visibleReviews.map((review) => (
          <ReviewCard key={review.id} review={review} />
        ))}
      </div>

      {reviews.length > visibleCount && (
        <div className="mt-6 flex justify-center gap-1.5" role="tablist" aria-label="Review pages">
          {Array.from({ length: Math.ceil(reviews.length / visibleCount) }, (_, page) => {
            const pageStart = page * visibleCount;
            const isActive = pageStart === alignedStart;

            return (
              <button
                key={page}
                type="button"
                role="tab"
                aria-selected={isActive}
                aria-label={`Show review page ${page + 1}`}
                onClick={() => setStart(pageStart)}
                className={`h-1.5 rounded-full transition-all ${
                  isActive ? 'bg-accent-600 w-6' : 'bg-border-soft w-1.5'
                }`}
              />
            );
          })}
        </div>
      )}
    </div>
  );
}
