import * as React from 'react';

import { Label } from '@/Components/ui/label';
import { useUnsavedChangesGuard } from '@/hooks/useUnsavedChangesGuard';
import { cn } from '@/lib/utils';

interface ResourceFormProps extends Omit<React.FormHTMLAttributes<HTMLFormElement>, 'onSubmit'> {
  onSubmit: (event: React.FormEvent<HTMLFormElement>) => void;
  /** Pass `useForm()`'s own `isDirty` straight through — this component doesn't track dirtiness
   * itself, since Inertia's `useForm` already does it correctly (including array/object fields). */
  isDirty: boolean;
  children: React.ReactNode;
}

/**
 * Brief Phase 5 spec: "Inertia useForm, inline errors, unsaved-changes guard, optional autosave
 * draft." This component provides the guard and a consistent `<form>`/`FormField` shell; the
 * autosave draft itself is `useAutosaveDraft` (opt-in per page, since not every form wants it) and
 * `useForm()` is still called by the page itself — wrapping it here would hide its return value
 * (data/setData/errors/processing) from the very form fields that need it.
 */
export function ResourceForm({
  onSubmit,
  isDirty,
  children,
  className,
  ...props
}: ResourceFormProps) {
  const allowNextVisit = useUnsavedChangesGuard(isDirty);

  function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    // Let the form's own save visit through — see useUnsavedChangesGuard's comment on why the
    // guard would otherwise intercept the very submit it's meant to protect.
    allowNextVisit();
    onSubmit(event);
  }

  return (
    <form onSubmit={handleSubmit} className={cn('space-y-6', className)} {...props}>
      {children}
    </form>
  );
}

interface FormFieldProps {
  label: string;
  htmlFor: string;
  error?: string;
  hint?: string;
  children: React.ReactNode;
}

export function FormField({ label, htmlFor, error, hint, children }: FormFieldProps) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={htmlFor}>{label}</Label>
      {children}
      {error ? (
        <p className="text-sm text-red-600" role="alert">
          {error}
        </p>
      ) : hint ? (
        <p className="text-ink-muted text-sm">{hint}</p>
      ) : null}
    </div>
  );
}
