import * as React from 'react';

import { scrollToSection, useScrollSpy } from '@/lib/useScrollSpy';

interface Pill {
  id: string;
  label: string;
}

interface CategoryPillBarProps {
  pills: Pill[];
  /** When given, renders a leading "All" pill that scrolls back to this anchor rather than
   * highlighting any specific category. */
  topAnchorId?: string;
  ariaLabel: string;
  /** Spacing utilities ONLY (e.g. `mb-6`) — merged onto this component's own root. Do NOT wrap this
   * component in an extra `<div>` for spacing instead: a `position: sticky` element only stays stuck
   * for as long as its immediate parent's box does, so a tight wrapper with no other content gives it
   * almost no room to actually stick before scrolling away (a real bug, found by scrolling the page,
   * not a hypothetical one — ad hoc task 25 fix). */
  className?: string;
}

function pillClass(active: boolean): string {
  return `shrink-0 rounded-full border px-4 py-2 text-sm ${
    active ? 'border-ink bg-ink text-ivory' : 'border-border-soft text-ink-muted hover:border-accent-500'
  }`;
}

/**
 * Shared category pill bar with scrollspy auto-highlight and click-to-scroll, used by Services,
 * Deals and Book's services step (ad hoc task 24) — one implementation rather than three
 * near-identical ones, since all three already used the exact same pill visual style.
 *
 * Deliberately NOT `position: sticky` (ad hoc task 31 fix, on explicit user request) — it now
 * scrolls away with the page like ordinary content instead of pinning itself below the floating
 * header. The solid `bg-ivory` background (the exact same `#faf7f2` the page body itself uses) is
 * kept regardless, since it's also what the header/masking fixes (tasks 25/29) rely on.
 *
 * `min-w-0` on the root: a flex/grid item's default `min-width` is `auto` (its content's natural
 * width), not `0` — without this, this bar's own horizontally-scrolling pill row forces any flex/grid
 * ANCESTOR (e.g. `Book.tsx`'s `lg:grid-cols-[1fr_320px]` step-0 column, which collapses to a single
 * implicit-width track on mobile) to stretch to fit every pill unwrapped, blowing out the whole page's
 * width instead of scrolling within its own bounds (ad hoc task 25 fix — a real mobile overflow bug).
 */
export function CategoryPillBar({ pills, topAnchorId, ariaLabel, className }: CategoryPillBarProps) {
  const ids = React.useMemo(() => pills.map((pill) => pill.id), [pills]);
  const activeId = useScrollSpy(ids);

  return (
    <div
      className={`border-border-soft bg-ivory -mx-1 min-w-0 border-b py-3 ${className ?? ''}`}
    >
      <div
        role="group"
        aria-label={ariaLabel}
        className="flex min-w-0 gap-2 overflow-x-auto whitespace-nowrap px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
      >
        {topAnchorId && (
          <button
            type="button"
            aria-pressed={activeId === null}
            onClick={() => scrollToSection(topAnchorId)}
            className={pillClass(activeId === null)}
          >
            All
          </button>
        )}
        {pills.map((pill) => (
          <button
            key={pill.id}
            type="button"
            aria-pressed={activeId === pill.id}
            onClick={() => scrollToSection(pill.id)}
            className={pillClass(activeId === pill.id)}
          >
            {pill.label}
          </button>
        ))}
      </div>
    </div>
  );
}
