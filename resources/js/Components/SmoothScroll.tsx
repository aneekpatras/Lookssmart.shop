import { useReducedMotion } from 'framer-motion';
import Lenis from 'lenis';
import * as React from 'react';

/**
 * Wraps Lenis smooth-scroll. Skipped entirely under prefers-reduced-motion — Lenis overrides native
 * scroll physics, which is exactly what that preference asks us not to do (Brief §6).
 */
export function SmoothScroll({ children }: { children: React.ReactNode }) {
  const prefersReducedMotion = useReducedMotion();

  React.useEffect(() => {
    if (prefersReducedMotion) {
      return;
    }

    const lenis = new Lenis({
      duration: 1.1,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
    });

    let frameId: number;
    function raf(time: number) {
      lenis.raf(time);
      frameId = requestAnimationFrame(raf);
    }
    frameId = requestAnimationFrame(raf);

    return () => {
      cancelAnimationFrame(frameId);
      lenis.destroy();
    };
  }, [prefersReducedMotion]);

  return <>{children}</>;
}
