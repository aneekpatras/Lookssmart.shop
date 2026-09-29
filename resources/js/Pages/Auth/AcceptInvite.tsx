import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthLayout from '@/Layouts/AuthLayout';

interface AcceptInvitePageProps {
  email: string;
  role: string;
}

export default function AcceptInvite({ email, role }: AcceptInvitePageProps) {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    password: '',
    password_confirmation: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    // Posts back to the current URL, preserving the signed query string (email/role/expires/
    // signature) exactly — the server reads email/role from that signed query string, never from
    // this form's body.
    post(window.location.pathname + window.location.search);
  }

  return (
    <>
      <Head title="Accept your invitation" />

      <form onSubmit={submit} className="space-y-4">
        <div className="border-border-soft bg-accent-50/60 rounded-lg border p-3 text-sm">
          <p className="text-ink">
            <strong>{email}</strong>
          </p>
          <p className="text-ink-muted">Joining as {role.replace('-', ' ')}</p>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="name">Your name</Label>
          <Input
            id="name"
            value={data.name}
            onChange={(e) => setData('name', e.target.value)}
            aria-invalid={!!errors.name}
            required
          />
          {errors.name ? <p className="text-sm text-red-600">{errors.name}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            autoComplete="new-password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
            aria-invalid={!!errors.password}
            required
          />
          {errors.password ? <p className="text-sm text-red-600">{errors.password}</p> : null}
          <p className="text-ink-muted text-xs">At least 12 characters.</p>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password_confirmation">Confirm password</Label>
          <Input
            id="password_confirmation"
            type="password"
            autoComplete="new-password"
            value={data.password_confirmation}
            onChange={(e) => setData('password_confirmation', e.target.value)}
            required
          />
        </div>

        <Button type="submit" className="w-full" disabled={processing}>
          Accept invitation
        </Button>
      </form>
    </>
  );
}

AcceptInvite.layout = (page: React.ReactNode) => (
  <AuthLayout title="You've been invited" description="Set a password to activate your account.">
    {page}
  </AuthLayout>
);
