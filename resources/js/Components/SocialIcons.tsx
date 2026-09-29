import * as React from 'react';

/**
 * Facebook and Instagram marks, drawn locally because `lucide-react` (v1.x) removed every brand
 * icon from its set — importing `Facebook`/`Instagram` from it is a hard TypeScript error, not a
 * deprecation warning. Rather than pull in a second icon package for two glyphs, these reproduce
 * lucide's own outline geometry and stroke conventions (24x24 viewBox, `currentColor`,
 * `strokeWidth: 2`, round caps/joins) so they sit correctly alongside the real lucide icons used in
 * the same footer and contact card.
 *
 * Inline SVG, deliberately — an external icon sprite or CDN would be blocked outright by this app's
 * `img-src 'self' data:` CSP (see SecurityHeaders).
 */
type IconProps = React.SVGProps<SVGSVGElement>;

const baseProps = {
  viewBox: '0 0 24 24',
  fill: 'none',
  stroke: 'currentColor',
  strokeWidth: 2,
  strokeLinecap: 'round' as const,
  strokeLinejoin: 'round' as const,
};

export function FacebookIcon({ className, ...props }: IconProps) {
  return (
    <svg {...baseProps} className={className} {...props}>
      <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z" />
    </svg>
  );
}

export function InstagramIcon({ className, ...props }: IconProps) {
  return (
    <svg {...baseProps} className={className} {...props}>
      <rect width="20" height="20" x="2" y="2" rx="5" ry="5" />
      <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z" />
      <line x1="17.5" x2="17.51" y1="6.5" y2="6.5" />
    </svg>
  );
}
