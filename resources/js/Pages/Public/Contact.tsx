import { Link, useForm } from '@inertiajs/react';
import {
  ArrowRight,
  Clock3,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Send,
} from 'lucide-react';
import * as React from 'react';

import { SeoHead } from '@/Components/SeoHead';
import { FacebookIcon, InstagramIcon } from '@/Components/SocialIcons';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import PublicLayout from '@/Layouts/PublicLayout';

interface BusinessInfo {
  name: string | null;
  phone: string | null;
  email: string | null;
  address: string | null;
  facebook: string | null;
  instagram: string | null;
  latitude: number | null;
  longitude: number | null;
}

interface BusinessHourRow {
  weekday: number;
  open_time: string | null;
  close_time: string | null;
  is_closed: boolean;
}

/** Matches `PublicWebsiteController::postListData()` exactly — the blog index's own mapper. */
interface PostCard {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  cover_image_path: string | null;
  published_at: string;
  category: { id: number; name: string; slug: string } | null;
  readingTimeMinutes: number;
}

interface ContactPageProps {
  business: BusinessInfo;
  businessHours: BusinessHourRow[];
  latestPosts: PostCard[];
}

const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

function digitsOnly(value: string): string {
  return value.replace(/[^\d+]/g, '');
}

/** MySQL returns TIME as `H:i:s`, SQLite as whatever was written — handle both. */
function formatTime(time: string | null): string {
  if (!time) return '';
  const [hours, minutes] = time.split(':');
  const date = new Date();
  date.setHours(Number(hours), Number(minutes ?? 0));

  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

/**
 * Collapses the seven `business_hours` rows into the fewest honest lines: one entry when every open
 * day shares a window, otherwise a line per distinct window, so an irregular week is never
 * flattened into a single misleading claim. Same logic as the footer and About page.
 */
function summariseHours(hours: BusinessHourRow[]): { days: string; hours: string }[] {
  if (hours.length === 0) return [];

  const ordered = [...hours].sort((a, b) => ((a.weekday + 6) % 7) - ((b.weekday + 6) % 7));
  const groups: { start: string; end: string; window: string }[] = [];

  for (const hour of ordered) {
    const dayName = WEEKDAY_NAMES[hour.weekday];
    if (!dayName) continue;

    const window = hour.is_closed
      ? 'Closed'
      : `${formatTime(hour.open_time)} – ${formatTime(hour.close_time)}`;
    const last = groups[groups.length - 1];

    if (last && last.window === window) {
      last.end = dayName;
      continue;
    }

    groups.push({ start: dayName, end: dayName, window });
  }

  return groups.map((group) => ({
    days: group.start === group.end ? group.start : `${group.start} – ${group.end}`,
    hours: group.window,
  }));
}

function SectionBadge({ children }: { children: React.ReactNode }) {
  return (
    <p className="text-accent-700 text-xs font-semibold uppercase tracking-[0.22em]">{children}</p>
  );
}

export default function Contact({ business, businessHours, latestPosts }: ContactPageProps) {
  const [submitted, setSubmitted] = React.useState(false);
  // Time-trap seed: a bot that posts the form faster than a human could read it is rejected
  // server-side by ProtectsPublicForms. Captured once on first render, never re-seeded.
  const [renderedAt] = React.useState(() => Math.floor(Date.now() / 1000));

  const { data, setData, post, processing, errors, reset } = useForm({
    name: '',
    phone: '',
    email: '',
    subject: '',
    message: '',
    website: '', // Honeypot — kept empty by real users.
    rendered_at: renderedAt,
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    post('/contact', {
      preserveScroll: true,
      onSuccess: () => {
        setSubmitted(true);
        reset('name', 'phone', 'email', 'subject', 'message');
      },
    });
  }

  const phoneHref = business.phone ? `tel:${digitsOnly(business.phone)}` : undefined;
  const whatsappHref = business.phone
    ? `https://wa.me/${digitsOnly(business.phone).replace('+', '')}`
    : 'https://wa.me/923059833859';

  const hasCoordinates = business.latitude !== null && business.longitude !== null;
  const coordinates = hasCoordinates ? `${business.latitude},${business.longitude}` : null;
  const directionsHref = coordinates
    ? `https://www.google.com/maps/dir/?api=1&destination=${coordinates}`
    : business.address
      ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(business.address)}`
      : undefined;
  const mapEmbedSrc = coordinates
    ? `https://www.google.com/maps?q=${coordinates}&z=17&output=embed`
    : undefined;

  const hourLines = summariseHours(businessHours);

  const channels = [
    {
      icon: MessageCircle,
      title: 'WhatsApp',
      body: 'Instant replies. Book, inquire, or send anything.',
      action: 'Chat now',
      href: whatsappHref,
      external: true,
    },
    {
      icon: Phone,
      title: 'Call us',
      body: business.phone ? `Speak with reception at ${business.phone}.` : 'Speak with reception.',
      action: 'Dial number',
      href: phoneHref,
      external: false,
    },
    {
      icon: Mail,
      title: 'Email',
      body: 'For inquiries, partnerships, or feedback.',
      action: 'Send email',
      href: business.email ? `mailto:${business.email}` : undefined,
      external: false,
    },
    {
      icon: MapPin,
      title: 'Visit salon',
      body: business.address ?? 'Central Park Housing Scheme, Lahore, Pakistan.',
      action: 'Get directions',
      href: directionsHref,
      external: true,
    },
  ].filter((channel) => Boolean(channel.href));

  return (
    <>
      <SeoHead
        title="Contact Us"
        description="Talk to Looks Smart Beauty Salon — book an appointment, ask about a treatment, or plan a bridal visit."
      />

      {/* 1. Compact hero — deliberately the same height band as the other inner pages, not the
             full-bleed banner in the reference design. */}
      <section className="bg-ivory border-border-soft border-b px-6 py-14 lg:px-10 lg:py-20">
        <div className="mx-auto max-w-6xl">
          <SectionBadge>Get in touch</SectionBadge>
          <h1 className="font-display text-ink mt-4 max-w-3xl text-4xl font-medium leading-[1.1] sm:text-5xl">
            Talk to Looks Smart — anytime, any way you like
          </h1>
          <p className="text-ink-muted mt-5 max-w-2xl leading-8">
            Book an appointment, ask about a treatment, share feedback, or plan a bridal visit. Our
            team replies promptly during operational hours.
          </p>
          <div className="mt-8 flex flex-wrap gap-3">
            <Button asChild size="lg" variant="accent">
              <a href={whatsappHref} target="_blank" rel="noopener noreferrer">
                <MessageCircle /> WhatsApp Us
              </a>
            </Button>
            {phoneHref && (
              <Button asChild size="lg" variant="outline">
                <a href={phoneHref}>
                  <Phone /> {business.phone}
                </a>
              </Button>
            )}
          </div>
        </div>
      </section>

      {/* 2. Quick contact methods */}
      <section className="px-6 py-16 lg:px-10 lg:py-20">
        <div className="mx-auto max-w-6xl">
          <div className="text-center">
            <h2 className="font-display text-ink text-3xl font-medium sm:text-4xl">
              Choose how to reach us
            </h2>
            <p className="text-ink-muted mt-3">Every channel is monitored by our team.</p>
          </div>

          <div className="mt-10 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
            {channels.map((channel) => {
              const Icon = channel.icon;

              return (
                <Card
                  key={channel.title}
                  className="border-border-soft bg-surface shadow-soft hover:border-accent-500 flex h-full flex-col transition-colors"
                >
                  <CardContent className="flex flex-1 flex-col p-6">
                    <span className="bg-accent-50 text-accent-700 flex size-11 items-center justify-center rounded-xl">
                      <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <h3 className="font-display text-ink mt-5 text-lg font-medium">
                      {channel.title}
                    </h3>
                    <p className="text-ink-muted mt-2 text-sm leading-6">{channel.body}</p>
                    <a
                      href={channel.href}
                      {...(channel.external
                        ? { target: '_blank', rel: 'noopener noreferrer' }
                        : {})}
                      className="text-accent-700 hover:text-accent-600 mt-4 inline-flex items-center gap-1.5 text-sm font-semibold"
                    >
                      {channel.action} <ArrowRight className="size-4" aria-hidden="true" />
                    </a>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        </div>
      </section>

      {/* 3. Message form + sidebar */}
      <section className="border-border-soft bg-surface border-y px-6 py-16 lg:px-10 lg:py-20">
        <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-[1.35fr_0.65fr]">
          <Card className="border-border-soft bg-ivory">
            <CardContent className="p-6 sm:p-8">
              <h2 className="font-display text-ink text-2xl font-medium">Send us a message</h2>
              <p className="text-ink-muted mt-2 text-sm">
                Fill in a few details and we&rsquo;ll get back to you on WhatsApp or a call.
              </p>

              {submitted ? (
                <div
                  className="border-accent-300 bg-accent-50 text-ink mt-6 rounded-lg border p-5 text-sm"
                  role="status"
                >
                  Thanks for reaching out — we will be in touch shortly.
                </div>
              ) : null}

              <form onSubmit={submit} className="mt-6 space-y-5" noValidate>
                {/* Honeypot: visually and programmatically hidden, so no real user ever fills it. */}
                <div className="hidden" aria-hidden="true">
                  <label htmlFor="website">Website</label>
                  <input
                    id="website"
                    type="text"
                    tabIndex={-1}
                    autoComplete="off"
                    value={data.website}
                    onChange={(event) => setData('website', event.target.value)}
                  />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                  <div>
                    <Label htmlFor="name">
                      Your name <span className="text-accent-700">*</span>
                    </Label>
                    <Input
                      id="name"
                      name="name"
                      required
                      autoComplete="name"
                      placeholder="Ayesha Khan"
                      value={data.name}
                      onChange={(event) => setData('name', event.target.value)}
                      aria-invalid={Boolean(errors.name)}
                      aria-describedby={errors.name ? 'name-error' : undefined}
                      className="mt-1.5"
                    />
                    {errors.name && (
                      <p id="name-error" className="mt-1.5 text-sm text-red-600">
                        {errors.name}
                      </p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="phone">
                      Phone / WhatsApp <span className="text-accent-700">*</span>
                    </Label>
                    <Input
                      id="phone"
                      name="phone"
                      type="tel"
                      required
                      autoComplete="tel"
                      placeholder="0300 1234567"
                      value={data.phone}
                      onChange={(event) => setData('phone', event.target.value)}
                      aria-invalid={Boolean(errors.phone)}
                      aria-describedby={errors.phone ? 'phone-error' : undefined}
                      className="mt-1.5"
                    />
                    {errors.phone && (
                      <p id="phone-error" className="mt-1.5 text-sm text-red-600">
                        {errors.phone}
                      </p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="email">Email (optional)</Label>
                    <Input
                      id="email"
                      name="email"
                      type="email"
                      autoComplete="email"
                      placeholder="you@example.com"
                      value={data.email}
                      onChange={(event) => setData('email', event.target.value)}
                      aria-invalid={Boolean(errors.email)}
                      aria-describedby={errors.email ? 'email-error' : undefined}
                      className="mt-1.5"
                    />
                    {errors.email && (
                      <p id="email-error" className="mt-1.5 text-sm text-red-600">
                        {errors.email}
                      </p>
                    )}
                  </div>

                  <div>
                    <Label htmlFor="subject">Subject (optional)</Label>
                    <Input
                      id="subject"
                      name="subject"
                      placeholder="e.g. Bridal enquiry"
                      value={data.subject}
                      onChange={(event) => setData('subject', event.target.value)}
                      aria-invalid={Boolean(errors.subject)}
                      className="mt-1.5"
                    />
                  </div>
                </div>

                <div>
                  <Label htmlFor="message">
                    How can we help? <span className="text-accent-700">*</span>
                  </Label>
                  <textarea
                    id="message"
                    name="message"
                    required
                    rows={5}
                    maxLength={2000}
                    placeholder="Tell us about the service, date, or anything else you would like us to know."
                    value={data.message}
                    onChange={(event) => setData('message', event.target.value)}
                    aria-invalid={Boolean(errors.message)}
                    aria-describedby={errors.message ? 'message-error' : undefined}
                    className="border-border-soft bg-surface text-ink placeholder:text-ink-muted/70 focus-visible:ring-accent-500 mt-1.5 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
                  />
                  {errors.message && (
                    <p id="message-error" className="mt-1.5 text-sm text-red-600">
                      {errors.message}
                    </p>
                  )}
                </div>

                <div className="flex flex-wrap gap-3">
                  <Button type="submit" variant="accent" disabled={processing}>
                    <Send /> {processing ? 'Sending…' : 'Send Message'}
                  </Button>
                  <Button asChild type="button" variant="outline">
                    <a href={whatsappHref} target="_blank" rel="noopener noreferrer">
                      <MessageCircle /> WhatsApp Instead
                    </a>
                  </Button>
                </div>
              </form>
            </CardContent>
          </Card>

          <div className="space-y-5">
            {/* Accent-styled "prefer to chat" card — theme accent, not the reference's coral. */}
            <Card className="border-accent-300 bg-accent-50">
              <CardContent className="p-6">
                <div className="text-accent-700 flex items-center gap-2">
                  <MessageCircle className="size-5" aria-hidden="true" />
                  <h2 className="font-display text-ink text-lg font-medium">Prefer to chat?</h2>
                </div>
                <p className="text-ink-muted mt-3 text-sm leading-6">
                  Message us on WhatsApp for the fastest reply — usually within minutes during
                  opening hours.
                </p>
                <Button asChild variant="accent" className="mt-4 w-full">
                  <a href={whatsappHref} target="_blank" rel="noopener noreferrer">
                    Open WhatsApp
                  </a>
                </Button>
              </CardContent>
            </Card>

            <Card className="border-border-soft bg-ivory">
              <CardContent className="p-6">
                <div className="text-accent-700 flex items-center gap-2">
                  <Clock3 className="size-5" aria-hidden="true" />
                  <h2 className="font-display text-ink text-lg font-medium">Opening hours</h2>
                </div>
                <div className="text-ink-muted mt-3 space-y-1.5 text-sm">
                  {hourLines.length > 0 ? (
                    hourLines.map((line) => (
                      <p key={line.days} className="flex justify-between gap-3">
                        <span className="text-ink font-medium">{line.days}</span>
                        <span>{line.hours}</span>
                      </p>
                    ))
                  ) : (
                    <p>Please contact us for our current opening hours.</p>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border-soft bg-ivory overflow-hidden">
              <CardContent className="p-6">
                <div className="text-accent-700 flex items-center gap-2">
                  <MapPin className="size-5" aria-hidden="true" />
                  <h2 className="font-display text-ink text-lg font-medium">Our flagship salon</h2>
                </div>
                {business.address && (
                  <p className="text-ink-muted mt-3 text-sm leading-6">{business.address}</p>
                )}
                {directionsHref && (
                  <a
                    href={directionsHref}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="text-accent-700 hover:text-accent-600 mt-3 inline-flex items-center gap-1.5 text-sm font-semibold"
                  >
                    Get directions <ArrowRight className="size-4" aria-hidden="true" />
                  </a>
                )}
              </CardContent>
              {/* Pinned on the salon's real surveyed coordinates rather than a geocoded address
                  string, which for "Main Ferozpur Road" resolves anywhere along kilometres of road.
                  Needs `frame-src https://www.google.com` in the CSP (SecurityHeaders). */}
              {mapEmbedSrc && (
                <iframe
                  title="Salon location map"
                  src={mapEmbedSrc}
                  width="100%"
                  height="200"
                  style={{ border: 0 }}
                  loading="lazy"
                  referrerPolicy="no-referrer-when-downgrade"
                />
              )}
            </Card>
          </div>
        </div>
      </section>

      {/* 4. Latest beauty tips & guides */}
      {latestPosts.length > 0 && (
        <section className="px-6 py-16 lg:px-10 lg:py-20">
          <div className="mx-auto max-w-6xl">
            <div className="flex flex-wrap items-end justify-between gap-4">
              <div>
                <SectionBadge>From our journal</SectionBadge>
                <h2 className="font-display text-ink mt-3 text-3xl font-medium">
                  Latest beauty tips &amp; guides
                </h2>
              </div>
              <Link
                href="/blog"
                className="text-accent-700 hover:text-accent-600 inline-flex items-center gap-1.5 text-sm font-semibold"
              >
                View all posts <ArrowRight className="size-4" aria-hidden="true" />
              </Link>
            </div>

            <div className="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
              {latestPosts.map((post) => (
                <Card
                  key={post.id}
                  className="border-border-soft bg-surface shadow-soft flex h-full flex-col overflow-hidden"
                >
                  <div className="bg-accent-50 h-[170px] shrink-0">
                    {post.cover_image_path && (
                      <img
                        src={post.cover_image_path}
                        alt={post.title}
                        loading="lazy"
                        decoding="async"
                        className="size-full object-cover"
                      />
                    )}
                  </div>
                  <CardContent className="flex flex-1 flex-col p-5">
                    {post.category && (
                      <p className="text-accent-700 text-xs font-semibold uppercase tracking-wider">
                        {post.category.name}
                      </p>
                    )}
                    <h3 className="font-display text-ink mt-2 text-lg font-medium leading-snug">
                      <Link href={`/blog/${post.slug}`} className="hover:text-accent-700">
                        {post.title}
                      </Link>
                    </h3>
                    {post.excerpt && (
                      <p className="text-ink-muted mt-2 line-clamp-3 text-sm leading-6">
                        {post.excerpt}
                      </p>
                    )}
                    <p className="text-ink-muted mt-auto pt-3 text-xs">
                      {post.readingTimeMinutes} min read
                    </p>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* 5. Stay connected */}
      <section className="border-border-soft bg-surface border-t px-6 py-16 lg:px-10 lg:py-20">
        <div className="mx-auto max-w-3xl text-center">
          <h2 className="font-display text-ink text-3xl font-medium sm:text-4xl">
            Stay connected with Looks Smart
          </h2>
          <p className="text-ink-muted mt-3">
            Follow us for daily transformations, new treatments, and exclusive offers.
          </p>

          <div className="mt-8 flex flex-wrap justify-center gap-3">
            {business.instagram && (
              <a
                href={business.instagram}
                target="_blank"
                rel="noopener noreferrer"
                className="border-border-soft bg-ivory text-ink hover:border-accent-500 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition-colors"
              >
                <InstagramIcon className="text-accent-700 size-4" aria-hidden="true" /> Instagram
              </a>
            )}
            {business.facebook && (
              <a
                href={business.facebook}
                target="_blank"
                rel="noopener noreferrer"
                className="border-border-soft bg-ivory text-ink hover:border-accent-500 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition-colors"
              >
                <FacebookIcon className="text-accent-700 size-4" aria-hidden="true" /> Facebook
              </a>
            )}
            <a
              href={whatsappHref}
              target="_blank"
              rel="noopener noreferrer"
              className="border-border-soft bg-ivory text-ink hover:border-accent-500 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition-colors"
            >
              <MessageCircle className="text-accent-700 size-4" aria-hidden="true" /> WhatsApp
            </a>
            {business.email && (
              <a
                href={`mailto:${business.email}`}
                className="border-border-soft bg-ivory text-ink hover:border-accent-500 inline-flex items-center gap-2 rounded-full border px-5 py-2.5 text-sm font-medium transition-colors"
              >
                <Mail className="text-accent-700 size-4" aria-hidden="true" /> Support inbox
              </a>
            )}
          </div>
        </div>
      </section>
    </>
  );
}

Contact.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
