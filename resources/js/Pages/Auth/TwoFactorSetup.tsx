import { Head, router } from '@inertiajs/react';
import axios, { isAxiosError } from 'axios';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Toaster } from '@/Components/ui/toaster';

// Every Fortify 2FA endpoint (enable/confirm/qr-code/recovery-codes) is gated by the `password.confirm`
// middleware, since config/fortify.php sets `confirmPassword: true` for twoFactorAuthentication.
// A fresh login never has a recent password confirmation in session, so this page must clear that
// gate FIRST — otherwise every 2FA endpoint 423s with "Password confirmation required."
type Step = 'loading' | 'need-password' | 'enable' | 'confirm' | 'done';

export default function TwoFactorSetup() {
  const [step, setStep] = React.useState<Step>('loading');
  const [password, setPassword] = React.useState('');
  const [qrSvg, setQrSvg] = React.useState<string | null>(null);
  const [recoveryCodes, setRecoveryCodes] = React.useState<string[]>([]);
  const [code, setCode] = React.useState('');
  const [error, setError] = React.useState<string | null>(null);
  const [submitting, setSubmitting] = React.useState(false);

  React.useEffect(() => {
    axios
      .get('/user/confirmed-password-status')
      .then((response) => {
        setStep(response.data.confirmed ? 'enable' : 'need-password');
      })
      .catch(() => setStep('need-password'));
  }, []);

  // Once the password gate is cleared, check whether 2FA is already enabled (has a QR code) or
  // needs to be started fresh — this only runs after 'need-password'/'enable' clear the gate.
  async function checkTwoFactorStatus() {
    try {
      const qr = await axios.get('/user/two-factor-qr-code');
      setQrSvg(qr.data.svg);
      setStep('confirm');
    } catch {
      setStep('enable');
    }
  }

  async function confirmPassword(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await axios.post('/user/confirm-password', { password });
      await checkTwoFactorStatus();
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 422) {
        setError('That password is incorrect. Please try again.');
      } else {
        setError('Could not confirm your password. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function enable() {
    setSubmitting(true);
    setError(null);

    try {
      await axios.post('/user/two-factor-authentication');
      const qr = await axios.get('/user/two-factor-qr-code');
      setQrSvg(qr.data.svg);
      setStep('confirm');
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 423) {
        // Password confirmation timed out between steps — send them back to re-confirm.
        setStep('need-password');
      } else {
        setError('Could not start 2FA setup. Please try again.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  async function confirm(e: React.FormEvent) {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      await axios.post('/user/confirmed-two-factor-authentication', { code });
      const recovery = await axios.get('/user/two-factor-recovery-codes');
      setRecoveryCodes(recovery.data);
      setStep('done');
    } catch (err) {
      if (isAxiosError(err) && err.response?.status === 423) {
        setStep('need-password');
      } else {
        setError('That code did not work. Please check your authenticator app and try again.');
      }
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="bg-ivory flex min-h-screen justify-center px-4 py-16">
      <Head title="Set up two-factor authentication" />
      <Toaster />

      <div className="border-border-soft bg-surface shadow-soft h-fit w-full max-w-lg space-y-6 rounded-2xl border p-8">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">
            Two-factor authentication required
          </h1>
          <p className="text-ink-muted mt-1 text-sm">
            Your role requires 2FA before you can use the admin dashboard (Brief §5).
          </p>
        </div>

        {error ? <p className="text-sm text-red-600">{error}</p> : null}

        {step === 'loading' ? <p className="text-ink-muted text-sm">Loading…</p> : null}

        {step === 'need-password' ? (
          <form onSubmit={confirmPassword} className="space-y-3">
            <p className="text-ink-muted text-sm">
              For your security, please confirm your password before setting up 2FA.
            </p>
            <div className="space-y-1.5">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
              />
            </div>
            <Button type="submit" disabled={submitting}>
              Confirm password
            </Button>
          </form>
        ) : null}

        {step === 'enable' ? (
          <Button onClick={enable} disabled={submitting}>
            Start setup
          </Button>
        ) : null}

        {step === 'confirm' && qrSvg ? (
          <div className="space-y-4">
            <p className="text-ink-muted text-sm">
              Scan this QR code with your authenticator app (Google Authenticator, Authy, 1Password,
              etc.), then enter the 6-digit code it shows.
            </p>
            <div
              className="border-border-soft bg-surface w-fit rounded-lg border p-4"
              dangerouslySetInnerHTML={{ __html: qrSvg }}
            />
            <form onSubmit={confirm} className="space-y-3">
              <div className="space-y-1.5">
                <Label htmlFor="code">6-digit code</Label>
                <Input
                  id="code"
                  inputMode="numeric"
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  required
                />
              </div>
              <Button type="submit" disabled={submitting}>
                Confirm
              </Button>
            </form>
          </div>
        ) : null}

        {step === 'done' ? (
          <div className="space-y-4">
            <p className="text-sm text-emerald-600">Two-factor authentication is now enabled.</p>
            <div>
              <p className="text-ink text-sm font-medium">
                Save these recovery codes somewhere safe
              </p>
              <p className="text-ink-muted text-xs">
                Each can be used once if you lose access to your authenticator app.
              </p>
              <ul className="border-border-soft bg-surface mt-2 grid grid-cols-2 gap-1 rounded-lg border p-4 font-mono text-sm">
                {recoveryCodes.map((recoveryCode) => (
                  <li key={recoveryCode}>{recoveryCode}</li>
                ))}
              </ul>
            </div>
            <Button onClick={() => router.visit('/admin')}>Continue to dashboard</Button>
          </div>
        ) : null}
      </div>
    </div>
  );
}
