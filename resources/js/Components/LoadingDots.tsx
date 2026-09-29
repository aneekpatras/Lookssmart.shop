import { motion, useReducedMotion, type Variants } from 'framer-motion';

const dotVariants: Variants = {
  pulse: {
    scale: [1, 1.5, 1],
    transition: {
      duration: 1.2,
      repeat: Infinity,
      ease: 'easeInOut',
    },
  },
};

interface LoadingDotsProps {
  /** Merged onto the flex row wrapping the 3 dots — spacing/sizing utilities only. */
  className?: string;
  /** Merged onto each individual dot — size utilities only (defaults to `size-3`). */
  dotClassName?: string;
  /** When given, this element announces itself as its own live `role="status"` region with this
   * label. Omit when embedding inside an ancestor that already provides its own status region/label
   * (e.g. `NavigationProgressOverlay`) — two nested live regions both announcing the same moment is
   * redundant for screen reader users, not doubly helpful. */
  label?: string;
}

/**
 * Ad hoc task 32: replaces every skeleton-block loading indicator on the public site (per explicit
 * user request) with this 3-dot pulse — the exact animation/timing supplied, themed to the site's own
 * `accent-500` (rather than the original's `--hue-1` custom property, which doesn't exist here) so it
 * reads as part of this design system rather than a foreign component. A staggered negative delay
 * (`staggerChildren: -0.2`, `staggerDirection: -1`) is what gives the 3 dots their rolling, one-after-
 * another pulse rather than all 3 scaling in lockstep.
 */
export function LoadingDots({ className, dotClassName, label }: LoadingDotsProps) {
  const prefersReducedMotion = useReducedMotion();
  const statusProps = label ? { role: 'status' as const, 'aria-label': label } : {};

  return (
    <motion.div
      animate={prefersReducedMotion ? undefined : 'pulse'}
      transition={{ staggerChildren: -0.2, staggerDirection: -1 }}
      className={`flex items-center justify-center gap-4 ${className ?? ''}`}
      {...statusProps}
    >
      {[0, 1, 2].map((index) => (
        <motion.div
          key={index}
          variants={dotVariants}
          className={`bg-accent-500 rounded-full ${dotClassName ?? 'size-3'}`}
        />
      ))}
    </motion.div>
  );
}
