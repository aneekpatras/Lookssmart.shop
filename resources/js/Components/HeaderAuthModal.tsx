import { useForm } from '@inertiajs/react';
import { Eye, EyeOff } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { LoadingDots } from '@/Components/LoadingDots';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface HeaderAuthModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}

/**
 * The logged-out header user icon's popup — real login/registration forms posting to the exact
 * same `/login`/`/register` Fortify routes the full-page `Auth/Login.tsx`/`Auth/Register.tsx` use
 * (not a reimplementation), just presented in a modal so a visitor never has to leave the page
 * they're on. A successful submit triggers Fortify's own redirect, which Inertia follows as a
 * normal page visit — the modal's job ends the moment that happens, since the resulting page
 * already reflects the logged-in state (including this same header's dropdown).
 */
export function HeaderAuthModal({ open, onOpenChange }: HeaderAuthModalProps) {
  const [tab, setTab] = React.useState<'login' | 'register'>('login');
  const [showLoginPassword, setShowLoginPassword] = React.useState(false);
  const [showRegisterPassword, setShowRegisterPassword] = React.useState(false);
  const [showRegisterPasswordConfirmation, setShowRegisterPasswordConfirmation] = React.useState(false);

  const loginForm = useForm({ email: '', password: '', remember: false as boolean });
  const registerForm = useForm({ name: '', email: '', password: '', password_confirmation: '' });

  function submitLogin(event: React.FormEvent) {
    event.preventDefault();
    loginForm.post('/login', { onSuccess: () => onOpenChange(false) });
  }

  function submitRegister(event: React.FormEvent) {
    event.preventDefault();
    registerForm.post('/register', { onSuccess: () => onOpenChange(false) });
  }

  function handleOpenChange(next: boolean) {
    if (!next) {
      loginForm.reset();
      registerForm.reset();
      loginForm.clearErrors();
      registerForm.clearErrors();
    }
    onOpenChange(next);
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-sm">
        <DialogHeader>
          <DialogTitle>{tab === 'login' ? 'Welcome back' : 'Create your account'}</DialogTitle>
          <DialogDescription>
            {tab === 'login'
              ? 'Log in to manage your bookings and profile.'
              : 'Book faster and track your visit history.'}
          </DialogDescription>
        </DialogHeader>

        <div className="border-border-soft flex gap-1 border-b" role="tablist" aria-label="Account access">
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'login'}
            onClick={() => setTab('login')}
            className={`border-b-2 px-3 py-2 text-sm ${tab === 'login' ? 'border-accent-500 text-ink' : 'border-transparent text-ink-muted'}`}
          >
            Log in
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={tab === 'register'}
            onClick={() => setTab('register')}
            className={`border-b-2 px-3 py-2 text-sm ${tab === 'register' ? 'border-accent-500 text-ink' : 'border-transparent text-ink-muted'}`}
          >
            Create account
          </button>
        </div>

        {tab === 'login' && (
          <form onSubmit={submitLogin} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="header-login-email">Email</Label>
              <Input
                id="header-login-email"
                type="email"
                autoComplete="username"
                value={loginForm.data.email}
                onChange={(e) => loginForm.setData('email', e.target.value)}
                aria-invalid={!!loginForm.errors.email}
                required
              />
              {loginForm.errors.email && (
                <p className="text-sm text-red-600">{loginForm.errors.email}</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="header-login-password">Password</Label>
              <div className="relative">
                <Input
                  id="header-login-password"
                  type={showLoginPassword ? 'text' : 'password'}
                  autoComplete="current-password"
                  value={loginForm.data.password}
                  onChange={(e) => loginForm.setData('password', e.target.value)}
                  aria-invalid={!!loginForm.errors.password}
                  className="pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowLoginPassword((v) => !v)}
                  className="text-ink-muted hover:text-ink absolute inset-y-0 right-0 flex items-center px-3"
                  aria-label={showLoginPassword ? 'Hide password' : 'Show password'}
                  tabIndex={-1}
                >
                  {showLoginPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
              {loginForm.errors.password && (
                <p className="text-sm text-red-600">{loginForm.errors.password}</p>
              )}
            </div>
            <label className="text-ink-muted flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="border-border-soft size-4 rounded"
                checked={loginForm.data.remember}
                onChange={(e) => loginForm.setData('remember', e.target.checked)}
              />
              Remember me
            </label>
            <Button type="submit" className="w-full" disabled={loginForm.processing}>
              {loginForm.processing ? <LoadingDots dotClassName="size-1.5" /> : 'Log in'}
            </Button>
            <p className="text-ink-muted text-center text-xs">
              <a href="/forgot-password" className="hover:underline">
                Forgot password?
              </a>
            </p>
          </form>
        )}

        {tab === 'register' && (
          <form onSubmit={submitRegister} className="space-y-4">
            <div className="space-y-1.5">
              <Label htmlFor="header-register-name">Name</Label>
              <Input
                id="header-register-name"
                value={registerForm.data.name}
                onChange={(e) => registerForm.setData('name', e.target.value)}
                aria-invalid={!!registerForm.errors.name}
                required
              />
              {registerForm.errors.name && (
                <p className="text-sm text-red-600">{registerForm.errors.name}</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="header-register-email">Email</Label>
              <Input
                id="header-register-email"
                type="email"
                autoComplete="username"
                value={registerForm.data.email}
                onChange={(e) => registerForm.setData('email', e.target.value)}
                aria-invalid={!!registerForm.errors.email}
                required
              />
              {registerForm.errors.email && (
                <p className="text-sm text-red-600">{registerForm.errors.email}</p>
              )}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="header-register-password">Password</Label>
              <div className="relative">
                <Input
                  id="header-register-password"
                  type={showRegisterPassword ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={registerForm.data.password}
                  onChange={(e) => registerForm.setData('password', e.target.value)}
                  aria-invalid={!!registerForm.errors.password}
                  className="pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowRegisterPassword((v) => !v)}
                  className="text-ink-muted hover:text-ink absolute inset-y-0 right-0 flex items-center px-3"
                  aria-label={showRegisterPassword ? 'Hide password' : 'Show password'}
                  tabIndex={-1}
                >
                  {showRegisterPassword ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
              {registerForm.errors.password && (
                <p className="text-sm text-red-600">{registerForm.errors.password}</p>
              )}
              <p className="text-ink-muted text-xs">At least 12 characters.</p>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="header-register-password-confirmation">Confirm password</Label>
              <div className="relative">
                <Input
                  id="header-register-password-confirmation"
                  type={showRegisterPasswordConfirmation ? 'text' : 'password'}
                  autoComplete="new-password"
                  value={registerForm.data.password_confirmation}
                  onChange={(e) => registerForm.setData('password_confirmation', e.target.value)}
                  className="pr-10"
                  required
                />
                <button
                  type="button"
                  onClick={() => setShowRegisterPasswordConfirmation((v) => !v)}
                  className="text-ink-muted hover:text-ink absolute inset-y-0 right-0 flex items-center px-3"
                  aria-label={showRegisterPasswordConfirmation ? 'Hide password' : 'Show password'}
                  tabIndex={-1}
                >
                  {showRegisterPasswordConfirmation ? <EyeOff className="size-4" /> : <Eye className="size-4" />}
                </button>
              </div>
            </div>
            <Button type="submit" className="w-full" disabled={registerForm.processing}>
              {registerForm.processing ? <LoadingDots dotClassName="size-1.5" /> : 'Create account'}
            </Button>
          </form>
        )}
      </DialogContent>
    </Dialog>
  );
}
