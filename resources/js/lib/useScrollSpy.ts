import * as React from 'react';

/**
 * Tracks which of the given section element ids currently occupies the "active" band of the
 * viewport — just below the sticky header + pill bar, down to 60% of the viewport height — and
 * returns its id. Used to auto-highlight the matching category pill as the user scrolls through
 * stacked sections on Services/Deals/Book (ad hoc task 24). Picks whichever observed section has
 * the greatest visible ratio inside that band, rather than "first intersecting", so scrolling past
 * a short section doesn't flicker the highlight onto whichever callback happened to fire last.
 */
export function useScrollSpy(ids: string[]): string | null {
  const [activeId, setActiveId] = React.useState<string | null>(ids[0] ?? null);
  const key = ids.join(',');

  React.useEffect(() => {
    if (!key) return;
    const elements = key
      .split(',')
      .map((id) => document.getElementById(id))
      .filter((el): el is HTMLElement => el !== null);
    if (elements.length === 0) return;

    const visibleRatios = new Map<string, number>();

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          visibleRatios.set(entry.target.id, entry.isIntersecting ? entry.intersectionRatio : 0);
        }
        let bestId: string | null = null;
        let bestRatio = 0;
        for (const [id, ratio] of visibleRatios) {
          if (ratio > bestRatio) {
            bestRatio = ratio;
            bestId = id;
          }
        }
        if (bestId) setActiveId(bestId);
      },
      { rootMargin: '-140px 0px -60% 0px', threshold: [0, 0.25, 0.5, 0.75, 1] },
    );

    for (const el of elements) observer.observe(el);
    return () => observer.disconnect();
  }, [key]);

  return activeId;
}

/** Smoothly scrolls to a section, respecting `prefers-reduced-motion`. Sections should carry a
 * `scroll-mt-*` class matching the sticky header + pill bar height so the target isn't hidden
 * underneath them once scrolled to. */
export function scrollToSection(id: string): void {
  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.getElementById(id)?.scrollIntoView({ behavior: prefersReducedMotion ? 'auto' : 'smooth', block: 'start' });
}
