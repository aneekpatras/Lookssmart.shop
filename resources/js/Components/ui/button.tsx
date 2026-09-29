import { Slot } from '@radix-ui/react-slot';
import { cva, type VariantProps } from 'class-variance-authority';
import * as React from 'react';

import { cn } from '@/lib/utils';

const buttonVariants = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium ' +
    'motion-safe-transition transition-[color,background-color,box-shadow,transform] duration-200 ' +
    'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 ' +
    'disabled:pointer-events-none disabled:opacity-50 [&_svg]:size-4 [&_svg]:shrink-0',
  {
    variants: {
      variant: {
        default: 'bg-ink text-ivory shadow-soft hover:bg-ink/90',
        accent: 'bg-accent-500 text-ivory shadow-soft hover:bg-accent-600',
        outline: 'border border-border-soft bg-surface text-ink hover:bg-accent-50',
        ghost: 'text-ink hover:bg-accent-50',
        link: 'text-accent-600 underline-offset-4 hover:underline',
        destructive: 'bg-red-600 text-white shadow-soft hover:bg-red-700',
      },
      size: {
        default: 'h-11 min-w-11 px-5',
        sm: 'h-9 min-w-9 px-4 text-sm',
        lg: 'h-12 min-w-12 px-7 text-base',
        icon: 'size-11',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  },
);

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement>, VariantProps<typeof buttonVariants> {
  asChild?: boolean;
}

export const Button = React.forwardRef<HTMLButtonElement, ButtonProps>(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button';

    return (
      <Comp className={cn(buttonVariants({ variant, size, className }))} ref={ref} {...props} />
    );
  },
);
Button.displayName = 'Button';
