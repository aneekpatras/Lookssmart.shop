import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { LoadingDots } from '@/Components/LoadingDots';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Login({ status }: { status?: string }) {
  const { data, setData, post, processing, errors } = useForm({
    email: '',
    password: '',
    remember: false as boolean,
  });
  const [showPassword, setShowPassword] = React.useState(false);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/login');
  }

  return (
    <>
      <Head title="Log in" />
      {status ? <p className="mb-4 text-sm text-emerald-600">{status}</p> : null}

      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="email">Email</Label>
          <Input
            id="email"
            type="email"
            autoComplete="username"
            value={data.email}
            onChange={(e) => setData('email', e.target.value)}
            aria-invalid={!!errors.email}
            required
          />
          {errors.email ? <p className="text-sm text-red-600">{errors.email}</p> : null}
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password">Password</Label>
          <div className="relative">
            <Input
              id="password"
              type={showPassword ? 'text' : 'password'}
              autoComplete="current-password"
              value={data.password}
              onChange={(e) => setData('password', e.target.value)}
              aria-invalid={!!errors.password}
              className="pr-10"
              required
            />
            <button
              type="button"
              onClick={() => setShowPassword((v) => !v)}
              className="text-ink-muted hover:text-ink absolute inset-y-0 right-0 flex items-center px-3"
              aria-label={showPassword ? 'Hide password' : 'Show password'}
              tabIndex={-1}
            >
              {showPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
          {errors.password ? <p className="text-sm text-red-600">{errors.password}</p> : null}
        </div>

        <label className="text-ink-muted flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            className="border-border-soft size-4 rounded"
            checked={data.remember}
            onChange={(e) => setData('remember', e.target.checked)}
          />
          Remember me
        </label>

        <Button type="submit" className="w-full" disabled={processing}>
          {processing ? <LoadingDots dotClassName="size-1.5" /> : 'Log in'}
        </Button>

        <div className="flex items-center justify-between text-sm">
          <Link href="/register" className="text-accent-600 hover:underline">
            Create an account
          </Link>
          <Link href="/forgot-password" className="text-ink-muted hover:underline">
            Forgot password?
          </Link>
        </div>
      </form>
    </>
  );
}

Login.layout = (page: React.ReactNode) => (
  <AuthLayout title="Welcome back" description="Log in to manage your bookings and profile.">
    {page}
  </AuthLayout>
);
