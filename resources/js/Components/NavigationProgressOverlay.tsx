import { router } from '@inertiajs/react';
import * as React from 'react';

import { LoadingDots } from '@/Components/LoadingDots';

/**
 * Ad hoc task 31/32/33: on top of Inertia's own thin top progress bar (`app.tsx`'s `progress`
 * config), a genuine full-page Inertia visit — "Claim Offer" on a deal card most of all, per the
 * original complaint — shows this small floating pulse, so it's immediately obvious a navigation is
 * in flight rather than the click having silently done nothing. Site-wide (mounted once in
 * `PublicLayout`) rather than duplicated per page, since ANY Inertia `<Link>`/`router.visit()` call
 * anywhere benefits identically.
 *
 * Task 33 fix (a real, reproducing bug, not a style tweak): `router.on('start', ...)` fires for
 * EVERY visit Inertia makes — including a `prefetch` `<Link>`'s background hover fetch, not just a
 * real navigation. Since scrolling with the mouse held still slides page content UNDER a stationary
 * cursor, a "Claim Offer" button drifting under the pointer fires a genuine `mouseenter`, which
 * triggers its `prefetch` — showing this exact indicator on every such scroll, for a request the user
 * never asked for and that changes nothing on screen. Fixed by reading the visit payload
 * (`start`/`finish`/`error` all receive one) and ignoring anything with `visit.prefetch === true`.
 *
 * Deliberately no backdrop/dimming/blur (removed per explicit follow-up feedback — the earlier
 * full-page dim "didn't look good") and `pointer-events-none`, so it never visually or physically
 * blocks the page underneath — just a small, non-blocking pulse near the top of the screen.
 *
 * The 150ms delay before showing anything is deliberate: a fast navigation (prefetched, or simply a
 * quick local response) should never flash the indicator just to immediately remove it — that reads
 * as more broken than no feedback at all. Only a genuinely slow visit ever shows it.
 */
export function NavigationProgressOverlay() {
  const [visible, setVisible] = React.useState(false);

  React.useEffect(() => {
    let showTimer: number | undefined;

    const removeStart = router.on('start', (event) => {
      if (event.detail.visit.prefetch) return;
      showTimer = window.setTimeout(() => setVisible(true), 150);
    });
    const clear = () => {
      if (showTimer) window.clearTimeout(showTimer);
      setVisible(false);
    };
    // 'finish' fires for prefetch visits too, so it's gated the same way as 'start' — otherwise a
    // background prefetch finishing could clear an unrelated REAL navigation's indicator mid-flight.
    // 'error' carries no `visit`/`prefetch` field at all (a different payload shape entirely) and in
    // practice only ever fires for a real, user-initiated visit, so it clears unconditionally.
    const removeFinish = router.on('finish', (event) => {
      if (event.detail.visit.prefetch) return;
      clear();
    });
    const removeError = router.on('error', clear);

    return () => {
      removeStart();
      removeFinish();
      removeError();
      if (showTimer) window.clearTimeout(showTimer);
    };
  }, []);

  if (!visible) return null;

  return (
    <div
      className="pointer-events-none fixed inset-x-0 top-28 z-50 flex justify-center"
      role="status"
      aria-live="polite"
      aria-label="Loading next page"
    >
      <LoadingDots dotClassName="size-3 shadow-md" />
    </div>
  );
}
