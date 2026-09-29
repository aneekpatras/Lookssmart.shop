import { type LucideIcon } from 'lucide-react';
import * as React from 'react';

import { cn } from '@/lib/utils';

interface EmptyStateProps extends React.HTMLAttributes<HTMLDivElement> {
  icon?: LucideIcon;
  title: string;
  description?: string;
  action?: React.ReactNode;
}

export function EmptyState({
  icon: Icon,
  title,
  description,
  action,
  className,
  ...props
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'border-border-soft flex flex-col items-center gap-3 rounded-xl border border-dashed p-12 text-center',
        className,
      )}
      {...props}
    >
      {Icon ? (
        <div className="bg-accent-50 text-accent-600 flex size-12 items-center justify-center rounded-full">
          <Icon className="size-6" />
        </div>
      ) : null}
      <div className="space-y-1">
        <p className="font-display text-ink text-base font-medium">{title}</p>
        {description ? <p className="text-ink-muted text-sm">{description}</p> : null}
      </div>
      {action}
    </div>
  );
}
