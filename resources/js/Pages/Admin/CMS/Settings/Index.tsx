import { Head, useForm } from '@inertiajs/react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';
import { cn } from '@/lib/utils';

const MASK = '••••••••';

type SettingValue = string | number | boolean | null;

interface SettingsGroups {
  business: Record<string, SettingValue>;
  booking: Record<string, SettingValue>;
  payments: Record<string, SettingValue>;
  integrations: Record<string, SettingValue>;
  seo: Record<string, SettingValue>;
  security: Record<string, SettingValue>;
}

interface BusinessHourRow {
  weekday: number;
  open_time: string | null;
  close_time: string | null;
  is_closed: boolean;
}

interface SettingsIndexPageProps {
  settings: SettingsGroups;
  businessHours: BusinessHourRow[];
}

const TABS = [
  { id: 'general', label: 'General' },
  { id: 'hours', label: 'Business Hours' },
  { id: 'booking', label: 'Booking Rules' },
  { id: 'payments', label: 'Payments & Taxes' },
  { id: 'integrations', label: 'Integrations' },
  { id: 'seo-security', label: 'SEO & Security' },
] as const;

type TabId = (typeof TABS)[number]['id'];

function TextField({
  id,
  label,
  value,
  onChange,
  error,
  placeholder,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  error?: string;
  placeholder?: string;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      <Input id={id} value={value} placeholder={placeholder} onChange={(e) => onChange(e.target.value)} aria-invalid={!!error} />
      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  );
}

function SecretField({
  id,
  label,
  value,
  onChange,
  isSet,
  error,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  isSet: boolean;
  error?: string;
}) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id} className="flex items-center gap-2">
        {label}
        {isSet && <span className="text-ink-muted text-xs font-normal">(currently set)</span>}
      </Label>
      <Input
        id={id}
        type="password"
        autoComplete="off"
        value={value}
        placeholder={isSet ? 'Leave blank to keep current value' : 'Not set'}
        onChange={(e) => onChange(e.target.value)}
        aria-invalid={!!error}
      />
      {error && <p className="text-sm text-red-600">{error}</p>}
    </div>
  );
}

function ToggleField({
  id,
  label,
  checked,
  onChange,
}: {
  id: string;
  label: string;
  checked: boolean;
  onChange: (checked: boolean) => void;
}) {
  return (
    <label htmlFor={id} className="flex items-center gap-2">
      <Switch id={id} checked={checked} onCheckedChange={(value) => onChange(value === true)} />
      <span className="text-sm">{label}</span>
    </label>
  );
}

function GroupForm({
  group,
  title,
  description,
  fields,
  children,
}: {
  group: string;
  title: string;
  description?: string;
  fields: Record<string, string>;
  children: (data: Record<string, string>, setField: (key: string, value: string) => void, fieldError: (key: string) => string | undefined) => React.ReactNode;
}) {
  const { data, setData, transform, put, processing, errors } = useForm<Record<string, string>>(fields);

  function submit(event: React.FormEvent) {
    event.preventDefault();
    transform((current) => ({
      settings: Object.fromEntries(Object.entries(current).map(([key, value]) => [`${group}.${key}`, value])),
    }));
    put(`/admin/settings/${group}`, {
      preserveScroll: true,
      onSuccess: () => toast.success('Settings saved — cache cleared.'),
    });
  }

  function fieldError(key: string): string | undefined {
    return errors[`settings.${group}.${key}` as keyof typeof errors];
  }

  return (
    <Card>
      <CardContent className="space-y-5 p-6">
        <div>
          <h2 className="font-display text-ink text-lg font-medium">{title}</h2>
          {description && <p className="text-ink-muted text-sm">{description}</p>}
        </div>
        <form onSubmit={submit} className="space-y-4">
          {children(data, (key, value) => setData(key, value), fieldError)}
          <Button type="submit" disabled={processing}>
            Save
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function toFieldValue(value: SettingValue | undefined): string {
  if (value === null || value === undefined) return '';
  return String(value);
}

function GeneralTab({ business }: { business: Record<string, SettingValue> }) {
  const logoForm = useForm<{ logo: File | null }>({ logo: null });
  const faviconForm = useForm<{ favicon: File | null }>({ favicon: null });

  function uploadLogo(file: File) {
    logoForm.setData('logo', file);
    logoForm.post('/admin/settings/business/logo', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => toast.success('Logo updated.'),
    });
  }

  function uploadFavicon(file: File) {
    faviconForm.setData('favicon', file);
    faviconForm.post('/admin/settings/business/favicon', {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => toast.success('Favicon updated.'),
    });
  }

  return (
    <div className="space-y-6">
      <GroupForm
        group="business"
        title="Business Info"
        fields={{
          name: toFieldValue(business['business.name']),
          phone: toFieldValue(business['business.phone']),
          email: toFieldValue(business['business.email']),
          address: toFieldValue(business['business.address']),
          currency: toFieldValue(business['business.currency']),
          timezone: toFieldValue(business['business.timezone']),
          social_facebook: toFieldValue(business['business.social_facebook']),
          social_instagram: toFieldValue(business['business.social_instagram']),
          latitude: toFieldValue(business['business.latitude']),
          longitude: toFieldValue(business['business.longitude']),
        }}
      >
        {(data, setField, fieldError) => (
          <>
            <TextField id="business.name" label="Business name" value={data.name ?? ''} onChange={(v) => setField('name', v)} error={fieldError('name')} />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <TextField id="business.phone" label="Phone" value={data.phone ?? ''} onChange={(v) => setField('phone', v)} error={fieldError('phone')} />
              <TextField id="business.email" label="Email" value={data.email ?? ''} onChange={(v) => setField('email', v)} error={fieldError('email')} />
            </div>
            <TextField id="business.address" label="Address" value={data.address ?? ''} onChange={(v) => setField('address', v)} error={fieldError('address')} />
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <TextField id="business.currency" label="Currency" value={data.currency ?? ''} onChange={(v) => setField('currency', v)} error={fieldError('currency')} />
              <TextField id="business.timezone" label="Timezone" value={data.timezone ?? ''} onChange={(v) => setField('timezone', v)} error={fieldError('timezone')} />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <TextField id="business.social_facebook" label="Facebook URL" value={data.social_facebook ?? ''} onChange={(v) => setField('social_facebook', v)} />
              <TextField id="business.social_instagram" label="Instagram URL" value={data.social_instagram ?? ''} onChange={(v) => setField('social_instagram', v)} />
            </div>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
              <TextField id="business.latitude" label="Latitude (optional, for map SEO)" value={data.latitude ?? ''} onChange={(v) => setField('latitude', v)} error={fieldError('latitude')} />
              <TextField id="business.longitude" label="Longitude (optional, for map SEO)" value={data.longitude ?? ''} onChange={(v) => setField('longitude', v)} error={fieldError('longitude')} />
            </div>
          </>
        )}
      </GroupForm>

      <Card>
        <CardContent className="space-y-5 p-6">
          <h2 className="font-display text-ink text-lg font-medium">Branding</h2>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div className="space-y-1.5">
              <Label htmlFor="logo">Logo</Label>
              {business['business.logo_path'] && typeof business['business.logo_path'] === 'string' && (
                <img src={business['business.logo_path']} alt="" className="mb-2 h-12 object-contain" />
              )}
              <Input
                id="logo"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) uploadLogo(file);
                }}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="favicon">Favicon</Label>
              {business['business.favicon_path'] && typeof business['business.favicon_path'] === 'string' && (
                <img src={business['business.favicon_path']} alt="" className="mb-2 h-8 w-8 object-contain" />
              )}
              <Input
                id="favicon"
                type="file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                onChange={(e) => {
                  const file = e.target.files?.[0];
                  if (file) uploadFavicon(file);
                }}
              />
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

function HoursTab({ businessHours }: { businessHours: BusinessHourRow[] }) {
  const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const { data, setData, put, processing } = useForm<{ hours: BusinessHourRow[] }>({
    hours: WEEKDAY_NAMES.map((_, weekday) => businessHours.find((h) => h.weekday === weekday) ?? {
      weekday,
      open_time: '09:00',
      close_time: '19:00',
      is_closed: false,
    }),
  });

  function updateRow(weekday: number, patch: Partial<BusinessHourRow>) {
    setData(
      'hours',
      data.hours.map((row) => (row.weekday === weekday ? { ...row, ...patch } : row)),
    );
  }

  function submit(event: React.FormEvent) {
    event.preventDefault();
    put('/admin/settings/hours', {
      preserveScroll: true,
      onSuccess: () => toast.success('Operating hours saved — cache cleared.'),
    });
  }

  return (
    <Card>
      <CardContent className="space-y-5 p-6">
        <h2 className="font-display text-ink text-lg font-medium">Operating Hours</h2>
        <form onSubmit={submit} className="space-y-3">
          {data.hours.map((row) => (
            <div key={row.weekday} className="grid grid-cols-[100px_1fr_1fr_auto] items-center gap-3">
              <span className="text-sm font-medium">{WEEKDAY_NAMES[row.weekday]}</span>
              <Input
                type="time"
                aria-label={`${WEEKDAY_NAMES[row.weekday]} opening time`}
                value={row.open_time ?? ''}
                disabled={row.is_closed}
                onChange={(e) => updateRow(row.weekday, { open_time: e.target.value })}
              />
              <Input
                type="time"
                aria-label={`${WEEKDAY_NAMES[row.weekday]} closing time`}
                value={row.close_time ?? ''}
                disabled={row.is_closed}
                onChange={(e) => updateRow(row.weekday, { close_time: e.target.value })}
              />
              <div className="flex items-center gap-2 text-sm whitespace-nowrap">
                <Switch
                  checked={row.is_closed}
                  onCheckedChange={(checked) => updateRow(row.weekday, { is_closed: checked === true })}
                  aria-label={`${WEEKDAY_NAMES[row.weekday]} closed`}
                />
                <span>Closed</span>
              </div>
            </div>
          ))}
          <Button type="submit" disabled={processing}>
            Save hours
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function BookingTab({ booking }: { booking: Record<string, SettingValue> }) {
  return (
    <GroupForm
      group="booking"
      title="Booking Rules & Policies"
      fields={{
        slot_minutes: toFieldValue(booking['booking.slot_minutes']),
        max_advance_days: toFieldValue(booking['booking.max_advance_days']),
        hold_minutes: toFieldValue(booking['booking.hold_minutes']),
        min_lead_minutes: toFieldValue(booking['booking.min_lead_minutes']),
        cancellation_window_hours: toFieldValue(booking['booking.cancellation_window_hours']),
        tax_rate: toFieldValue(booking['booking.tax_rate']),
        max_bookings_per_slot: toFieldValue(booking['booking.max_bookings_per_slot']),
      }}
    >
      {(data, setField) => (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <TextField id="booking.slot_minutes" label="Slot size (minutes)" value={data.slot_minutes ?? ''} onChange={(v) => setField('slot_minutes', v)} />
          <TextField id="booking.max_bookings_per_slot" label="Max bookings per slot" value={data.max_bookings_per_slot ?? ''} onChange={(v) => setField('max_bookings_per_slot', v)} />
          <TextField id="booking.max_advance_days" label="Max advance booking (days)" value={data.max_advance_days ?? ''} onChange={(v) => setField('max_advance_days', v)} />
          <TextField id="booking.hold_minutes" label="Slot hold (minutes)" value={data.hold_minutes ?? ''} onChange={(v) => setField('hold_minutes', v)} />
          <TextField id="booking.min_lead_minutes" label="Minimum lead time (minutes)" value={data.min_lead_minutes ?? ''} onChange={(v) => setField('min_lead_minutes', v)} />
          <TextField id="booking.cancellation_window_hours" label="Cancellation window (hours)" value={data.cancellation_window_hours ?? ''} onChange={(v) => setField('cancellation_window_hours', v)} />
          <TextField id="booking.tax_rate" label="Tax rate (%)" value={data.tax_rate ?? ''} onChange={(v) => setField('tax_rate', v)} />
        </div>
      )}
    </GroupForm>
  );
}

function PaymentsTab({ payments }: { payments: Record<string, SettingValue> }) {
  const { data, setData, transform, put, processing, reset } = useForm<Record<string, string | boolean>>({
    cash_enabled: payments['payments.cash_enabled'] === true,
    card_enabled: payments['payments.card_enabled'] === true,
    stripe_public_key: toFieldValue(payments['payments.stripe_public_key']),
    stripe_secret_key: '',
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    transform((current) => ({
      settings: Object.fromEntries(Object.entries(current).map(([key, value]) => [`payments.${key}`, value])),
    }));
    put('/admin/settings/payments', {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Settings saved — cache cleared.');
        reset('stripe_secret_key');
      },
    });
  }

  return (
    <Card>
      <CardContent className="space-y-5 p-6">
        <div>
          <h2 className="font-display text-ink text-lg font-medium">Payments</h2>
          <p className="text-ink-muted text-sm">
            Tax rate is managed on the Booking Rules tab. These payment fields are not yet connected
            to a live payment processor (built in Phase 12).
          </p>
        </div>
        <form onSubmit={submit} className="space-y-4">
          <ToggleField id="cash_enabled" label="Accept cash" checked={data.cash_enabled === true} onChange={(v) => setData('cash_enabled', v)} />
          <ToggleField id="card_enabled" label="Accept card" checked={data.card_enabled === true} onChange={(v) => setData('card_enabled', v)} />
          <TextField
            id="stripe_public_key"
            label="Stripe publishable key"
            value={String(data.stripe_public_key ?? '')}
            onChange={(v) => setData('stripe_public_key', v)}
          />
          <SecretField
            id="stripe_secret_key"
            label="Stripe secret key"
            value={String(data.stripe_secret_key ?? '')}
            onChange={(v) => setData('stripe_secret_key', v)}
            isSet={payments['payments.stripe_secret_key'] === MASK}
          />
          <Button type="submit" disabled={processing}>
            Save
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function IntegrationsTab({ integrations }: { integrations: Record<string, SettingValue> }) {
  const { data, setData, transform, put, processing, reset } = useForm<Record<string, string>>({
    twilio_sid: '',
    twilio_auth_token: '',
    twilio_sms_from: toFieldValue(integrations['integrations.twilio_sms_from']),
    twilio_whatsapp_from: toFieldValue(integrations['integrations.twilio_whatsapp_from']),
    google_maps_api_key: '',
    google_analytics_id: toFieldValue(integrations['integrations.google_analytics_id']),
    google_tag_manager_id: toFieldValue(integrations['integrations.google_tag_manager_id']),
    google_site_verification: toFieldValue(integrations['integrations.google_site_verification']),
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    transform((current) => ({
      settings: Object.fromEntries(Object.entries(current).map(([key, value]) => [`integrations.${key}`, value])),
    }));
    put('/admin/settings/integrations', {
      preserveScroll: true,
      onSuccess: () => {
        toast.success('Settings saved — cache cleared.');
        reset('twilio_sid', 'twilio_auth_token', 'google_maps_api_key');
      },
    });
  }

  return (
    <Card>
      <CardContent className="space-y-5 p-6">
        <div>
          <h2 className="font-display text-ink text-lg font-medium">Integrations</h2>
          <p className="text-ink-muted text-sm">
            Google Analytics, Tag Manager, and Search Console verification are live on every public
            page (Phase 13) as soon as they&rsquo;re saved here. Mail/SMS/WhatsApp delivery and Google
            Maps still read from server environment configuration until a later phase wires those
            through.
          </p>
        </div>
        <form onSubmit={submit} className="space-y-4">
          <SecretField id="twilio_sid" label="Twilio Account SID" value={data.twilio_sid ?? ''} onChange={(v) => setData('twilio_sid', v)} isSet={integrations['integrations.twilio_sid'] === MASK} />
          <SecretField id="twilio_auth_token" label="Twilio Auth Token" value={data.twilio_auth_token ?? ''} onChange={(v) => setData('twilio_auth_token', v)} isSet={integrations['integrations.twilio_auth_token'] === MASK} />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField id="twilio_sms_from" label="SMS from number" value={data.twilio_sms_from ?? ''} onChange={(v) => setData('twilio_sms_from', v)} />
            <TextField id="twilio_whatsapp_from" label="WhatsApp from number" value={data.twilio_whatsapp_from ?? ''} onChange={(v) => setData('twilio_whatsapp_from', v)} />
          </div>
          <SecretField id="google_maps_api_key" label="Google Maps API key" value={data.google_maps_api_key ?? ''} onChange={(v) => setData('google_maps_api_key', v)} isSet={integrations['integrations.google_maps_api_key'] === MASK} />
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <TextField id="google_analytics_id" label="Google Analytics 4 ID" value={data.google_analytics_id ?? ''} onChange={(v) => setData('google_analytics_id', v)} placeholder="G-XXXXXXXXXX" />
            <TextField id="google_tag_manager_id" label="Google Tag Manager ID" value={data.google_tag_manager_id ?? ''} onChange={(v) => setData('google_tag_manager_id', v)} placeholder="GTM-XXXXXXX" />
          </div>
          <TextField id="google_site_verification" label="Google Search Console verification code" value={data.google_site_verification ?? ''} onChange={(v) => setData('google_site_verification', v)} />
          <Button type="submit" disabled={processing}>
            Save
          </Button>
        </form>
      </CardContent>
    </Card>
  );
}

function SeoSecurityTab({ seo, security }: { seo: Record<string, SettingValue>; security: Record<string, SettingValue> }) {
  return (
    <div className="space-y-6">
      <GroupForm
        group="seo"
        title="SEO Defaults"
        fields={{
          default_title: toFieldValue(seo['seo.default_title']),
          default_description: toFieldValue(seo['seo.default_description']),
        }}
      >
        {(data, setField) => (
          <>
            <TextField id="seo.default_title" label="Default meta title" value={data.default_title ?? ''} onChange={(v) => setField('default_title', v)} />
            <div className="space-y-1.5">
              <Label htmlFor="seo.default_description">Default meta description</Label>
              <Textarea id="seo.default_description" rows={2} value={data.default_description ?? ''} onChange={(e) => setField('default_description', e.target.value)} />
            </div>
          </>
        )}
      </GroupForm>

      <GroupForm
        group="security"
        title="Security"
        description="Admin IP allowlist and session lifetime are stored here but not yet enforced — a later security pass will wire these into the auth/session middleware."
        fields={{
          admin_ip_allowlist: toFieldValue(security['security.admin_ip_allowlist']),
          session_lifetime_minutes: toFieldValue(security['security.session_lifetime_minutes']),
        }}
      >
        {(data, setField) => (
          <>
            <TextField
              id="security.admin_ip_allowlist"
              label="Admin IP allowlist (comma-separated)"
              value={data.admin_ip_allowlist ?? ''}
              onChange={(v) => setField('admin_ip_allowlist', v)}
              placeholder="203.0.113.0/24, 198.51.100.7"
            />
            <TextField
              id="security.session_lifetime_minutes"
              label="Session lifetime (minutes)"
              value={data.session_lifetime_minutes ?? ''}
              onChange={(v) => setField('session_lifetime_minutes', v)}
            />
          </>
        )}
      </GroupForm>
    </div>
  );
}

export default function SettingsIndex({ settings, businessHours }: SettingsIndexPageProps) {
  const [tab, setTab] = React.useState<TabId>('general');

  return (
    <>
      <Head title="Settings" />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">Settings</h1>
          <p className="text-ink-muted text-sm">Business configuration, cached in Redis for fast reads.</p>
        </div>

        <div role="tablist" aria-label="Settings sections" className="border-border-soft flex flex-wrap gap-1 border-b pb-px">
          {TABS.map((t) => (
            <button
              key={t.id}
              type="button"
              role="tab"
              id={`tab-${t.id}`}
              aria-selected={tab === t.id}
              aria-controls={`panel-${t.id}`}
              onClick={() => setTab(t.id)}
              className={cn(
                'rounded-t-md px-4 py-2 text-sm font-medium',
                tab === t.id
                  ? 'border-accent-500 text-accent-700 border-b-2'
                  : 'text-ink-muted hover:text-ink',
              )}
            >
              {t.label}
            </button>
          ))}
        </div>

        <div id={`panel-${tab}`} role="tabpanel" aria-labelledby={`tab-${tab}`}>
          {tab === 'general' && <GeneralTab business={settings.business} />}
          {tab === 'hours' && <HoursTab businessHours={businessHours} />}
          {tab === 'booking' && <BookingTab booking={settings.booking} />}
          {tab === 'payments' && <PaymentsTab payments={settings.payments} />}
          {tab === 'integrations' && <IntegrationsTab integrations={settings.integrations} />}
          {tab === 'seo-security' && <SeoSecurityTab seo={settings.seo} security={settings.security} />}
        </div>
      </div>
    </>
  );
}

SettingsIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
