import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import AuthLayout from '@/Layouts/AuthLayout';

export default function TwoFactorChallenge() {
  const [useRecoveryCode, setUseRecoveryCode] = React.useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    code: '',
    recovery_code: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/two-factor-challenge');
  }

  function toggleMode() {
    reset();
    setUseRecoveryCode((prev) => !prev);
  }

  return (
    <>
      <Head title="Two-factor verification" />

      <form onSubmit={submit} className="space-y-4">
        {useRecoveryCode ? (
          <div className="space-y-1.5">
            <Label htmlFor="recovery_code">Recovery code</Label>
            <Input
              id="recovery_code"
              value={data.recovery_code}
              onChange={(e) => setData('recovery_code', e.target.value)}
              aria-invalid={!!errors.recovery_code}
              required
            />
            {errors.recovery_code ? (
              <p className="text-sm text-red-600">{errors.recovery_code}</p>
            ) : null}
          </div>
        ) : (
          <div className="space-y-1.5">
            <Label htmlFor="code">Authentication code</Label>
            <Input
              id="code"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={data.code}
              onChange={(e) => setData('code', e.target.value)}
              aria-invalid={!!errors.code}
              required
            />
            {errors.code ? <p className="text-sm text-red-600">{errors.code}</p> : null}
          </div>
        )}

        <Button type="submit" className="w-full" disabled={processing}>
          Verify
        </Button>

        <button
          type="button"
          onClick={toggleMode}
          className="text-ink-muted w-full text-center text-sm hover:underline"
        >
          {useRecoveryCode ? 'Use an authentication code instead' : 'Use a recovery code instead'}
        </button>
      </form>
    </>
  );
}

TwoFactorChallenge.layout = (page: React.ReactNode) => (
  <AuthLayout
    title="Two-factor verification"
    description="Enter the code from your authenticator app to continue."
  >
    {page}
  </AuthLayout>
);
