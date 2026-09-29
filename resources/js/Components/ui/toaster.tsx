import { Toaster as Sonner } from 'sonner';

export function Toaster() {
  return (
    <Sonner
      position="top-right"
      toastOptions={{
        classNames: {
          toast: 'rounded-lg border border-border-soft bg-surface text-ink shadow-glass',
          description: 'text-ink-muted',
          actionButton: 'bg-accent-500 text-ivory',
          cancelButton: 'bg-accent-50 text-ink',
        },
      }}
    />
  );
}
