import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthLayout from '@/Layouts/AuthLayout';

export default function ConfirmPassword() {
  const { data, setData, post, processing, errors } = useForm({ password: '' });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/user/confirm-password');
  }

  return (
    <>
      <Head title="Confirm password" />

      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="password">Password</Label>
          <Input
            id="password"
            type="password"
            autoComplete="current-password"
            value={data.password}
            onChange={(e) => setData('password', e.target.value)}
            aria-invalid={!!errors.password}
            required
          />
          {errors.password ? <p className="text-sm text-red-600">{errors.password}</p> : null}
        </div>

        <Button type="submit" className="w-full" disabled={processing}>
          Confirm
        </Button>
      </form>
    </>
  );
}

ConfirmPassword.layout = (page: React.ReactNode) => (
  <AuthLayout
    title="Confirm your password"
    description="This is a sensitive action — please confirm your password before continuing."
  >
    {page}
  </AuthLayout>
);
