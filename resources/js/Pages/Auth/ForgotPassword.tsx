import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { LoadingDots } from '@/Components/LoadingDots';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ForgotPassword({ status }: { status?: string }) {
  const { data, setData, post, processing, errors } = useForm({ email: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/forgot-password');
  }

  return (
    <>
      <Head title="Forgot password" />

      {status ? (
        <p className="mb-4 text-sm text-emerald-600">{status}</p>
      ) : (
        <p className="text-ink-muted mb-4 text-sm">
          Enter your email and we&apos;ll send you a password reset link.
        </p>
      )}

      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            type="email"
            value={data.email}
            onChange={(e) => setData('email', e.target.value)}
            aria-invalid={!!errors.email}
            required
          />
          {errors.email ? <p className="text-sm text-red-600">{errors.email}</p> : null}
        </div>

        <Button type="submit" className="w-full" disabled={processing}>
          {processing ? <LoadingDots dotClassName="size-1.5" /> : 'Email password reset link'}
        </Button>
      </form>
    </>
  );
}

ForgotPassword.layout = (page: React.ReactNode) => (
  <AuthLayout title="Reset your password">{page}</AuthLayout>
);
