import { ChevronLeft, ChevronRight } from 'lucide-react';
import * as React from 'react';

/**
 * Auto-scrolling horizontal carousel for deal cards, left-to-right, with pause-on-hover and manual
 * prev/next controls.
 *
 * DELIBERATELY NOT `BridalMarquee`'s approach. That component drives motion with a CSS
 * `translateX` keyframe animation — correct for a strip of plain images with no interactive
 * content, but wrong here: deal cards carry real buttons (Book, Details, Claim, WhatsApp), and a
 * card sliding out from under a user's cursor mid-click is both bad UX and an accessibility
 * problem, plus a CSS transform's position cannot be coherently combined with the "manual
 * navigation controls" this task explicitly asks for — you cannot cleanly react to an arrow-button
 * click by nudging a value that a running keyframe animation is simultaneously overwriting every
 * frame.
 *
 * This uses genuine `scrollLeft`, not a transform, so arrow buttons and auto-advance both just call
 * `scrollBy(...)` on the same real scroll position — no conflict, and it inherits correct native
 * keyboard/trackpad/touch scrolling for free. `scrollBy`/`scrollTo` are DOM methods, not inline
 * `style` mutations, so nothing here is affected by this app's no-`unsafe-inline` `style-src` CSP.
 *
 * Seamless loop: the children are rendered twice (second pass `aria-hidden`), and once the real
 * scroll position passes the width of one full pass, it is silently reset back by that same amount
 * — imperceptible, since the duplicate content is pixel-identical at that boundary. Same technique
 * as `BridalMarquee`'s `-50%` transform reset, just expressed as a `scrollLeft` subtraction instead.
 */
export function DealCarousel({
  children,
  itemCount,
  ariaLabel,
}: {
  children: React.ReactNode;
  itemCount: number;
  ariaLabel: string;
}) {
  const viewportRef = React.useRef<HTMLDivElement>(null);
  const [paused, setPaused] = React.useState(false);

  const cardStep = React.useCallback(() => {
    const viewport = viewportRef.current;
    if (!viewport) return 300;
    const firstCard = viewport.querySelector<HTMLElement>('[data-carousel-item]');
    return firstCard ? firstCard.getBoundingClientRect().width + 20 : 300;
  }, []);

  const advance = React.useCallback(
    (direction: 1 | -1) => {
      const viewport = viewportRef.current;
      if (!viewport) return;
      viewport.scrollBy({ left: direction * cardStep(), behavior: 'smooth' });
    },
    [cardStep],
  );

  // Auto-advance, paused on hover/focus/touch and while a manual scroll or drag is likely still
  // settling. Only runs with more than one real card — a single-item section has nothing to scroll
  // to, and a looping single card would just sit still anyway.
  React.useEffect(() => {
    if (paused || itemCount <= 1) return;

    const interval = window.setInterval(() => {
      const viewport = viewportRef.current;
      if (!viewport) return;

      const halfwayPoint = viewport.scrollWidth / 2;

      // Past the first full pass — snap back by exactly one pass width. Instant (no smooth
      // behavior here specifically), because the destination is pixel-identical to where the
      // animation already visually is, so the jump is genuinely invisible.
      if (viewport.scrollLeft >= halfwayPoint - 1) {
        viewport.scrollLeft -= halfwayPoint;
      }

      viewport.scrollBy({ left: cardStep(), behavior: 'smooth' });
    }, 3500);

    return () => window.clearInterval(interval);
  }, [paused, itemCount, cardStep]);

  return (
    <div className="group relative">
      <div
        ref={viewportRef}
        className="no-scrollbar flex gap-5 overflow-x-auto scroll-smooth"
        role="group"
        aria-label={ariaLabel}
        onMouseEnter={() => setPaused(true)}
        onMouseLeave={() => setPaused(false)}
        onFocus={() => setPaused(true)}
        onBlur={() => setPaused(false)}
        onPointerDown={() => setPaused(true)}
      >
        {children}
      </div>

      {itemCount > 1 && (
        <>
          <button
            type="button"
            onClick={() => advance(-1)}
            aria-label={`Scroll ${ariaLabel} left`}
            className="border-border-soft bg-surface text-ink shadow-soft absolute top-1/2 -left-3 hidden -translate-y-1/2 rounded-full border p-2 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 sm:block"
          >
            <ChevronLeft className="size-4" aria-hidden="true" />
          </button>
          <button
            type="button"
            onClick={() => advance(1)}
            aria-label={`Scroll ${ariaLabel} right`}
            className="border-border-soft bg-surface text-ink shadow-soft absolute top-1/2 -right-3 hidden -translate-y-1/2 rounded-full border p-2 opacity-0 transition-opacity group-hover:opacity-100 focus-visible:opacity-100 sm:block"
          >
            <ChevronRight className="size-4" aria-hidden="true" />
          </button>
        </>
      )}
    </div>
  );
}
