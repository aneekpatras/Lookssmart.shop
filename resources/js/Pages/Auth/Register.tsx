import { Head, Link, useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { LoadingDots } from '@/Components/LoadingDots';
import AuthLayout from '@/Layouts/AuthLayout';

export default function Register() {
  const { data, setData, post, processing, errors } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
  });
  const [showPassword, setShowPassword] = React.useState(false);
  const [showPasswordConfirmation, setShowPasswordConfirmation] = React.useState(false);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/register');
  }

  return (
    <>
      <Head title="Create an account" />

      <form onSubmit={submit} className="space-y-4">
        <div className="space-y-1.5">
          <Label htmlFor="name">Name</Label>
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
              autoComplete="new-password"
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
          <p className="text-ink-muted text-xs">At least 12 characters.</p>
        </div>

        <div className="space-y-1.5">
          <Label htmlFor="password_confirmation">Confirm password</Label>
          <div className="relative">
            <Input
              id="password_confirmation"
              type={showPasswordConfirmation ? 'text' : 'password'}
              autoComplete="new-password"
              value={data.password_confirmation}
              onChange={(e) => setData('password_confirmation', e.target.value)}
              className="pr-10"
              required
            />
            <button
              type="button"
              onClick={() => setShowPasswordConfirmation((v) => !v)}
              className="text-ink-muted hover:text-ink absolute inset-y-0 right-0 flex items-center px-3"
              aria-label={showPasswordConfirmation ? 'Hide password' : 'Show password'}
              tabIndex={-1}
            >
              {showPasswordConfirmation ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
            </button>
          </div>
        </div>

        <Button type="submit" className="w-full" disabled={processing}>
          {processing ? <LoadingDots dotClassName="size-1.5" /> : 'Create account'}
        </Button>

        <p className="text-ink-muted text-center text-sm">
          Already have an account?{' '}
          <Link href="/login" className="text-accent-600 hover:underline">
            Log in
          </Link>
        </p>
      </form>
    </>
  );
}

Register.layout = (page: React.ReactNode) => (
  <AuthLayout title="Create your account" description="Book faster and track your visit history.">
    {page}
  </AuthLayout>
);
