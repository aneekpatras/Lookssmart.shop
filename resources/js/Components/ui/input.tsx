import * as React from 'react';

import { cn } from '@/lib/utils';

export const Input = React.forwardRef<
  HTMLInputElement,
  React.InputHTMLAttributes<HTMLInputElement>
>(({ className, type = 'text', ...props }, ref) => {
  return (
    <input
      type={type}
      className={cn(
        'border-border-soft bg-surface text-ink flex h-11 w-full min-w-0 rounded-md border px-4 text-sm',
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
Input.displayName = 'Input';
