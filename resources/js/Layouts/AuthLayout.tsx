import { Link } from '@inertiajs/react';
import { motion, useReducedMotion } from 'framer-motion';
import * as React from 'react';

import { Toaster } from '@/Components/ui/toaster';

export default function AuthLayout({
  title,
  description,
  children,
}: {
  title: string;
  description?: string;
  children: React.ReactNode;
}) {
  const prefersReducedMotion = useReducedMotion();

  return (
    <div className="bg-ivory flex min-h-screen items-center justify-center px-4 py-12">
      <Toaster />
      <motion.div
        initial={prefersReducedMotion ? undefined : { opacity: 0, y: 8 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
        className="glass w-full max-w-md rounded-2xl p-8"
      >
        <Link href="/" className="font-display text-ink text-lg font-medium">
          Looks Smart
        </Link>

        <div className="mt-6 space-y-1.5">
          <h1 className="font-display text-ink text-2xl font-medium">{title}</h1>
          {description ? <p className="text-ink-muted text-sm">{description}</p> : null}
        </div>

        <div className="mt-6">{children}</div>
      </motion.div>
    </div>
  );
}
