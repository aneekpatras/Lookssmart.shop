import axios from 'axios';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import * as React from 'react';

import { CancelBookingDialog } from '@/Components/CancelBookingDialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatCurrency } from '@/lib/currency';

interface PageProps {
  [key: string]: unknown;
  auth: {
    user: {
      id: number;
      name: string;
      email: string;
      email_verified: boolean;
      two_factor_enabled: boolean;
    };
  };
  bookings: { data: Booking[]; current_page: number; last_page: number; total: number };
  upcoming: Booking[];
  profile: Preferences;
}

interface Booking {
  id: number;
  code: string;
  status: string;
  starts_at: string | null;
  ends_at: string | null;
  staff_id: number;
  services: string[];
  total: string;
  can_cancel: boolean;
  within_cancellation_fee_window: boolean;
}

interface Preferences {
  phone: string | null;
  marketing_opt_in: boolean;
  email_opt_out: boolean;
  sms_opt_out: boolean;
  whatsapp_opt_out: boolean;
}

function ProfileForm() {
  const { props } = usePage<PageProps>();
  const { data, setData, put, processing, errors, recentlySuccessful } = useForm({
    name: props.auth.user.name,
    email: props.auth.user.email,
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put('/user/profile-information');
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="name">Name</Label>
        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
        {errors.name ? <p className="text-sm text-red-600">{errors.name}</p> : null}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="email">Email</Label>
        <Input
          id="email"
          type="email"
          value={data.email}
          onChange={(e) => setData('email', e.target.value)}
        />
        {errors.email ? <p className="text-sm text-red-600">{errors.email}</p> : null}
        {!props.auth.user.email_verified ? (
          <p className="text-xs text-amber-600">
            Your email is not verified.{' '}
            <button
              type="button"
              className="underline"
              onClick={() => router.post('/email/verification-notification')}
            >
              Resend verification email
            </button>
          </p>
        ) : null}
      </div>
      <div className="flex items-center gap-3">
        <Button type="submit" disabled={processing}>
          Save
        </Button>
        {recentlySuccessful ? <span className="text-sm text-emerald-600">Saved.</span> : null}
      </div>
    </form>
  );
}

function PasswordForm() {
  const { data, setData, put, processing, errors, reset, recentlySuccessful } = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    put('/user/password', {
      onSuccess: () => reset(),
      errorBag: 'updatePassword',
    });
  }

  return (
    <form onSubmit={submit} className="space-y-4">
      <div className="space-y-1.5">
        <Label htmlFor="current_password">Current password</Label>
        <Input
          id="current_password"
          type="password"
          value={data.current_password}
          onChange={(e) => setData('current_password', e.target.value)}
        />
        {errors.current_password ? (
          <p className="text-sm text-red-600">{errors.current_password}</p>
        ) : null}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="password">New password</Label>
        <Input
          id="password"
          type="password"
          value={data.password}
          onChange={(e) => setData('password', e.target.value)}
        />
        {errors.password ? <p className="text-sm text-red-600">{errors.password}</p> : null}
      </div>
      <div className="space-y-1.5">
        <Label htmlFor="password_confirmation">Confirm new password</Label>
        <Input
          id="password_confirmation"
          type="password"
          value={data.password_confirmation}
          onChange={(e) => setData('password_confirmation', e.target.value)}
        />
      </div>
      <div className="flex items-center gap-3">
        <Button type="submit" disabled={processing}>
          Update password
        </Button>
        {recentlySuccessful ? <span className="text-sm text-emerald-600">Updated.</span> : null}
      </div>
    </form>
  );
}

function PreferencesForm({ profile }: { profile: Preferences }) {
  const { data, setData, put, processing, recentlySuccessful } = useForm(profile);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    put('/my-account/preferences');
  }

  return <form onSubmit={submit} className="space-y-4"><div className="space-y-1.5"><Label htmlFor="phone">Phone</Label><Input id="phone" type="tel" value={data.phone ?? ''} onChange={(event) => setData('phone', event.target.value)} /></div><label className="flex items-center gap-3 text-sm"><input type="checkbox" checked={data.marketing_opt_in} onChange={(event) => setData('marketing_opt_in', event.target.checked)} /> Receive occasional salon offers</label><p className="text-ink-muted text-xs">Service reminders remain available separately from marketing preferences.</p><div className="grid gap-3 sm:grid-cols-3"><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.email_opt_out} onChange={(event) => setData('email_opt_out', event.target.checked)} /> Email opt-out</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.sms_opt_out} onChange={(event) => setData('sms_opt_out', event.target.checked)} /> SMS opt-out</label><label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={data.whatsapp_opt_out} onChange={(event) => setData('whatsapp_opt_out', event.target.checked)} /> WhatsApp opt-out</label></div><div className="flex items-center gap-3"><Button type="submit" disabled={processing}>Save preferences</Button>{recentlySuccessful && <span className="text-sm text-emerald-600">Saved.</span>}</div></form>;
}

function BookingRow({ booking }: { booking: Booking }) {
  const [rescheduleAt, setRescheduleAt] = React.useState('');
  const [busy, setBusy] = React.useState(false);
  const [cancelOpen, setCancelOpen] = React.useState(false);

  async function reschedule() {
    if (!rescheduleAt) return;
    setBusy(true);
    await axios.post(`/api/booking/${booking.code}/reschedule`, { staff_id: booking.staff_id, starts_at: rescheduleAt, timezone: Intl.DateTimeFormat().resolvedOptions().timeZone });
    router.reload({ only: ['bookings', 'upcoming'] });
    setBusy(false);
  }

  return <Card><CardContent className="flex flex-col gap-4 p-5 md:flex-row md:items-center md:justify-between"><div><div className="flex items-center gap-3"><h3 className="text-lg">{booking.services.join(', ') || 'Appointment'}</h3><Badge variant={booking.status === 'cancelled' ? 'destructive' : booking.status === 'completed' ? 'success' : 'outline'}>{booking.status}</Badge></div><p className="text-ink-muted mt-2 text-sm">{booking.starts_at ? new Date(booking.starts_at).toLocaleString() : 'Date pending'} · {booking.code}</p><p className="text-ink mt-1 text-sm font-medium">{formatCurrency(booking.total)}</p></div>{booking.can_cancel && <div className="flex flex-wrap items-center gap-2"><Input aria-label={`New time for ${booking.code}`} type="datetime-local" value={rescheduleAt} onChange={(event) => setRescheduleAt(event.target.value)} className="w-52" /><Button size="sm" variant="outline" disabled={busy || !rescheduleAt} onClick={() => void reschedule()}>Reschedule</Button><Button size="sm" variant="destructive" disabled={busy} onClick={() => setCancelOpen(true)}>Cancel</Button></div>}</CardContent>{booking.can_cancel && <CancelBookingDialog booking={booking} open={cancelOpen} onOpenChange={setCancelOpen} onCancelled={() => router.reload({ only: ['bookings', 'upcoming'] })} />}</Card>;
}

export default function Profile() {
  const { props } = usePage<PageProps>();
  const [tab, setTab] = React.useState<'overview' | 'bookings' | 'profile'>('overview');

  // Genuine bug found via real-browser testing, not a defensive guess: `PublicLayout`'s
  // `AnimatePresence` keeps this page's React tree mounted for its exit-fade WHILE Inertia's page
  // store has already swapped to the destination page's props (proven by triggering it: logging out
  // from `/my-account` — which navigates to `/` — threw "Cannot read properties of undefined
  // (reading 'length')" on `props.upcoming`/`props.bookings.data`, since the still-fading-out
  // Profile component's own `usePage()` call picks up the NEW page's props, which have neither key).
  // Safe local fallbacks make the brief exit-transition render inert instead of crashing.
  const upcoming = props.upcoming ?? [];
  const bookings = props.bookings ?? { data: [], current_page: 1, last_page: 1, total: 0 };

  return (
    <>
      <Head title="My Account" />

      <div className="mx-auto max-w-5xl space-y-6 px-6 py-16">
        <div><p className="text-accent-700 text-sm font-semibold uppercase tracking-[0.18em]">Welcome back</p><h1 className="font-display text-ink mt-2 text-4xl font-medium">Your account</h1></div>
        <div className="border-border-soft flex gap-1 overflow-x-auto border-b" role="tablist" aria-label="Account sections">{(['overview', 'bookings', 'profile'] as const).map((item) => <button type="button" role="tab" aria-selected={tab === item} key={item} onClick={() => setTab(item)} className={`border-b-2 px-4 py-3 text-sm capitalize ${tab === item ? 'border-accent-500 text-ink' : 'border-transparent text-ink-muted'}`}>{item}</button>)}</div>
        {tab === 'overview' && <div className="space-y-6"><Card><CardHeader><CardTitle>Upcoming appointments</CardTitle><CardDescription>Your next visits at a glance.</CardDescription></CardHeader><CardContent className="space-y-3">{upcoming.length ? upcoming.map((booking) => <BookingRow key={booking.id} booking={booking} />) : <EmptyState title="No upcoming appointments" description="Choose a service when you’re ready for your next visit." />}</CardContent></Card><div className="grid gap-4 sm:grid-cols-3"><Card><CardContent className="p-5"><p className="text-ink-muted text-sm">Total bookings</p><p className="mt-2 text-3xl">{bookings.total}</p></CardContent></Card><Card><CardContent className="p-5"><p className="text-ink-muted text-sm">Next step</p><p className="mt-2 text-lg">Keep your details current</p></CardContent></Card><Card><CardContent className="p-5"><Link href="/book" className="text-accent-700 text-sm font-semibold">Book another visit <ArrowRight className="inline size-4" /></Link></CardContent></Card></div></div>}
        {tab === 'bookings' && <div className="space-y-4">{bookings.data.length ? bookings.data.slice(0, 5).map((booking) => <BookingRow key={booking.id} booking={booking} />) : <EmptyState title="No bookings yet" description="Your appointment history will appear here." />}<Link href="/my-bookings" className="text-accent-700 inline-flex items-center gap-1 text-sm font-semibold hover:underline">View all {bookings.total} booking{bookings.total === 1 ? '' : 's'} <ArrowRight className="size-4" /></Link></div>}
        {tab === 'profile' && <>
          <Card>
          <CardHeader>
            <CardTitle>Profile</CardTitle>
            <CardDescription>Update your name and email address.</CardDescription>
          </CardHeader>
          <CardContent>
            <ProfileForm />
          </CardContent>
          </Card>
          <Card><CardHeader><CardTitle>Communication preferences</CardTitle><CardDescription>Choose how we contact you.</CardDescription></CardHeader><CardContent><PreferencesForm profile={props.profile} /></CardContent></Card>

          <Card>
          <CardHeader>
            <CardTitle>Password</CardTitle>
            <CardDescription>Change your password.</CardDescription>
          </CardHeader>
          <CardContent>
            <PasswordForm />
          </CardContent>
          </Card>

          <Card>
          <CardHeader>
            <CardTitle>Two-factor authentication</CardTitle>
            <CardDescription>
              {props.auth.user.two_factor_enabled
                ? 'Enabled on your account.'
                : 'Optional for customer accounts, required for staff/admin.'}
            </CardDescription>
          </CardHeader>
          <CardContent className="flex items-center gap-3">
            <Badge variant={props.auth.user.two_factor_enabled ? 'success' : 'outline'}>
              {props.auth.user.two_factor_enabled ? 'Enabled' : 'Disabled'}
            </Badge>
            {!props.auth.user.two_factor_enabled ? (
              <Link href="/two-factor-setup" className="text-accent-600 text-sm hover:underline">
                Set up two-factor authentication
              </Link>
            ) : null}
          </CardContent>
          </Card>
        </>}

        <Card>
          <CardHeader>
            <CardTitle>Sessions</CardTitle>
            <CardDescription>
              See where you&apos;re logged in and sign out of other devices.
            </CardDescription>
          </CardHeader>
          <CardContent>
            <Link href="/user/sessions">
              <Button variant="outline">Manage sessions</Button>
            </Link>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

Profile.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
