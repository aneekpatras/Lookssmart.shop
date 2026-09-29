import { Head, router } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import AuthLayout from '@/Layouts/AuthLayout';

export default function VerifyEmail({ status }: { status?: string }) {
  const [sent, setSent] = React.useState(status === 'verification-link-sent');

  function resend() {
    router.post(
      '/email/verification-notification',
      {},
      {
        onSuccess: () => setSent(true),
      },
    );
  }

  return (
    <>
      <Head title="Verify email" />

      <p className="text-ink-muted text-sm">
        Thanks for signing up! Before getting started, please verify your email address by clicking
        the link we just emailed you.
      </p>

      {sent ? (
        <p className="mt-4 text-sm text-emerald-600">
          A new verification link has been sent to the email address you provided.
        </p>
      ) : null}

      <div className="mt-6 flex items-center justify-between">
        <Button onClick={resend}>Resend verification email</Button>
        <Button variant="ghost" onClick={() => router.post('/logout')}>
          Log out
        </Button>
      </div>
    </>
  );
}

VerifyEmail.layout = (page: React.ReactNode) => (
  <AuthLayout title="Verify your email">{page}</AuthLayout>
);
