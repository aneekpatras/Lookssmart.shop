import * as React from 'react';

export interface MarqueeImage {
  id: number | string;
  url: string;
  webp_url?: string | null;
  alt: string;
}

interface BridalMarqueeProps {
  images: MarqueeImage[];
}

/**
 * Auto-scrolling bridal photo marquee.
 *
 * The animation lives entirely in CSS (`.marquee-*` in resources/css/app.css) rather than in JS, on
 * purpose: this app's CSP is `style-src 'self' 'nonce-…'` with no `unsafe-inline`, and inline styles
 * are already being blocked on public pages (§10 #39). Driving a transform from framer-motion or
 * setting an inline `--marquee-duration` would risk silently not applying, so everything dynamic is
 * expressed as a DATA ATTRIBUTE (`data-count`, `data-paused`), which CSP does not police.
 *
 * Seamless loop: the image list is rendered TWICE and the track translates exactly -50%, so the
 * duplicate arrives precisely where the original began and there is no snap-back frame. The second
 * copy is `aria-hidden` so screen readers and the accessibility tree see each photo once.
 *
 * Speed: the brief asked for a "3-second cycle". Read literally as three seconds for the whole loop,
 * a dozen portrait cards would strobe past unreadably, so it is applied as a per-image cadence —
 * roughly 3s per card, via `data-count` selecting a duration in the stylesheet.
 *
 * Interaction: hover/focus pause is pure CSS. Touch and mouse dragging come from the viewport being
 * a native horizontal scroller, which also gives correct trackpad, wheel and keyboard behaviour for
 * free; the animation is paused while a pointer is down so a drag is not fighting it.
 */
export function BridalMarquee({ images }: BridalMarqueeProps) {
  const [dragging, setDragging] = React.useState(false);

  if (images.length === 0) {
    return null;
  }

  // Duration classes are enumerated for 2-12 images; outside that the stylesheet default applies.
  const countAttribute = images.length >= 2 && images.length <= 12 ? String(images.length) : undefined;

  return (
    <div
      className="marquee-viewport relative"
      onPointerDown={() => setDragging(true)}
      onPointerUp={() => setDragging(false)}
      onPointerCancel={() => setDragging(false)}
      onPointerLeave={() => setDragging(false)}
    >
      <ul
        className="marquee-track m-0 flex list-none gap-5 p-0"
        data-count={countAttribute}
        data-paused={dragging ? 'true' : undefined}
      >
        {images.map((image) => (
          <MarqueeCard key={image.id} image={image} />
        ))}
        {/* Duplicate pass — hidden from assistive tech so each photo is announced once. */}
        {images.map((image) => (
          <MarqueeCard key={`duplicate-${image.id}`} image={image} duplicate />
        ))}
      </ul>
    </div>
  );
}

function MarqueeCard({ image, duplicate = false }: { image: MarqueeImage; duplicate?: boolean }) {
  return (
    <li
      className="w-[240px] shrink-0 sm:w-[264px]"
      aria-hidden={duplicate ? 'true' : undefined}
    >
      <div className="shadow-soft bg-accent-50 aspect-[3/4] overflow-hidden rounded-2xl">
        {/* <picture> so an uploaded image's generated .webp sibling is preferred where one exists,
            with the original as the fallback source — same pattern as the gallery page. */}
        <picture>
          {image.webp_url && <source srcSet={image.webp_url} type="image/webp" />}
          <img
            src={image.url}
            alt={duplicate ? '' : image.alt}
            loading="lazy"
            decoding="async"
            draggable={false}
            className="size-full object-cover"
          />
        </picture>
      </div>
    </li>
  );
}
