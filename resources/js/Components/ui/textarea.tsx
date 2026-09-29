import * as React from 'react';

import { cn } from '@/lib/utils';

export const Textarea = React.forwardRef<
  HTMLTextAreaElement,
  React.TextareaHTMLAttributes<HTMLTextAreaElement>
>(({ className, ...props }, ref) => {
  return (
    <textarea
      className={cn(
        'border-border-soft bg-surface text-ink flex min-h-24 w-full rounded-md border px-4 py-3 text-sm',
        'motion-safe-transition placeholder:text-ink-muted transition-[border-color,box-shadow] duration-200',
        'focus-visible:border-accent-500 focus-visible:ring-accent-500/30 focus-visible:ring-2 focus-visible:outline-none',
        'disabled:cursor-not-allowed disabled:opacity-50',
        'aria-invalid:border-red-500 aria-invalid:ring-red-500/30',
        className,
      )}
      ref={ref}
      {...props}
    />
  );
});
Textarea.displayName = 'Textarea';
