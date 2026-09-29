import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
  const { data, setData, post, processing, errors } = useForm({
    token,
    email,
    password: '',
    password_confirmation: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/reset-password');
  }

  return (
    <>
      <Head title="Reset password" />

      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="email">Email</Label>
          <Input id="email" type="email" value={data.email} disabled />
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">New password</Label>
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
          <Label htmlFor="password_confirmation">Confirm new password</Label>
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
          Reset password
        </Button>
      </form>
    </>
  );
}

ResetPassword.layout = (page: React.ReactNode) => (
  <AuthLayout title="Set a new password">{page}</AuthLayout>
);
