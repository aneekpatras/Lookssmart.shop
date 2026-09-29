import { Link } from '@inertiajs/react';
import {
  ArrowRight,
  Clock3,
  Heart,
  Mail,
  MapPin,
  MessageCircle,
  Phone,
  Scissors,
  ShieldCheck,
  Sparkles,
} from 'lucide-react';
import * as React from 'react';

import { SeoHead } from '@/Components/SeoHead';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import PublicLayout from '@/Layouts/PublicLayout';

interface QuickStat {
  value: string;
  label: string;
}

interface Pillar {
  icon: string;
  title: string;
  description: string;
}

interface Founder {
  name: string;
  role: string;
  photo_url: string | null;
  quote: string;
  bio: string;
  narrative: string;
  highlights: string[];
}

interface BusinessHourRow {
  weekday: number;
  open_time: string | null;
  close_time: string | null;
  is_closed: boolean;
}

interface Contact {
  address: string | null;
  phone: string | null;
  email: string | null;
  whatsapp_url: string;
  hours: BusinessHourRow[];
}

interface AboutPageProps {
  quickStats: QuickStat[];
  pillars: Pillar[];
  founder: Founder;
  contact: Contact;
}

/**
 * Icon names arrive as strings from the controller (editorial content belongs there) and are mapped
 * to components here, since a component cannot be serialised through an Inertia prop. Unknown names
 * fall back to `Sparkles` rather than blanking a card.
 */
const PILLAR_ICONS: Record<string, React.ComponentType<{ className?: string }>> = {
  heart: Heart,
  shield: ShieldCheck,
  scissors: Scissors,
  sparkles: Sparkles,
};

const WEEKDAY_NAMES = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

/** MySQL returns TIME as `H:i:s`, SQLite as whatever was written — parse both. */
function formatTime(time: string | null): string {
  if (!time) return '';
  const [hours, minutes] = time.split(':');
  const date = new Date();
  date.setHours(Number(hours), Number(minutes ?? 0));

  return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
}

/**
 * Collapses the seven `business_hours` rows into the fewest honest lines — one entry when every
 * open day shares a window, otherwise a line per distinct window, so an irregular week is never
 * flattened into a single misleading claim. Mirrors the footer's server-side summary.
 */
function summariseHours(hours: BusinessHourRow[]): { days: string; hours: string }[] {
  if (hours.length === 0) return [];

  // The table is Sunday-indexed; salons read Monday-first.
  const ordered = [...hours].sort((a, b) => ((a.weekday + 6) % 7) - ((b.weekday + 6) % 7));
  const groups: { start: string; end: string; window: string }[] = [];

  for (const hour of ordered) {
    // `weekday` is constrained to 0-6 by the schema, but the index signature is still
    // possibly-undefined under strict TS — skip rather than label a row we cannot name.
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

/** Small shared eyebrow label, used above every section heading. */
function SectionBadge({ children }: { children: React.ReactNode }) {
  return (
    <p className="text-accent-700 text-xs font-semibold uppercase tracking-[0.22em]">{children}</p>
  );
}

export default function About({ quickStats, pillars, founder, contact }: AboutPageProps) {
  const hourLines = summariseHours(contact.hours);
  const phoneHref = contact.phone ? `tel:${contact.phone.replace(/[^\d+]/g, '')}` : undefined;

  return (
    <>
      <SeoHead
        title="About Us"
        description="Crafting premium beauty, hair, and skin experiences in Lahore — meet the team and philosophy behind Looks Smart Beauty Salon."
      />

      {/* 1. Hero header & quick stats */}
      <section className="bg-ivory px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto max-w-6xl">
          <SectionBadge>Our heritage</SectionBadge>
          <h1 className="font-display text-ink mt-4 max-w-3xl text-4xl font-medium leading-[1.08] sm:text-5xl lg:text-6xl">
            About Looks Smart Beauty Salon
          </h1>
          <p className="text-ink-muted mt-6 max-w-2xl text-lg leading-8">
            Crafting premium beauty, hair, and skin experiences in Lahore. We combine modern
            aesthetic techniques with personalized care to bring out your natural elegance.
          </p>
          <div className="mt-9 flex flex-wrap gap-3">
            <Button asChild size="lg" variant="accent">
              <Link href="/book">
                Book Appointment <ArrowRight />
              </Link>
            </Button>
            <Button asChild size="lg" variant="outline">
              <Link href="/services">Our Services</Link>
            </Button>
          </div>

          <dl className="border-border-soft mt-14 grid grid-cols-1 gap-px overflow-hidden rounded-xl border md:grid-cols-2 lg:grid-cols-4">
            {quickStats.map((stat) => (
              <div key={stat.label} className="bg-surface p-6">
                <dd className="font-display text-ink text-3xl font-medium">{stat.value}</dd>
                <dt className="text-ink-muted mt-2 text-sm">{stat.label}</dt>
              </div>
            ))}
          </dl>
        </div>
      </section>

      {/* 2. Brand mission & philosophy */}
      <section className="border-border-soft bg-surface border-y px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
          <div>
            <SectionBadge>Our mission</SectionBadge>
            <h2 className="font-display text-ink mt-4 text-3xl font-medium leading-tight sm:text-4xl">
              More than a salon — a modern beauty sanctuary.
            </h2>
          </div>
          <div className="text-ink-muted space-y-5 leading-8">
            <p>
              Hygiene is not a feature we advertise, it is the floor we operate on. Tools are
              sterilised between every client, kits are single-use wherever skin is involved, and
              the treatment rooms are cleaned to a clinical standard rather than a cosmetic one.
            </p>
            <p>
              We buy professional-grade products and use them as the manufacturer intended — no
              decanting, no diluting, no substituting a cheaper line halfway through a course. It
              costs more and it shows in the finish, particularly on chemical services where the
              difference is measured in months rather than days.
            </p>
            <p>
              That same exactness runs through hair, skin and bridal styling alike. Every
              appointment starts with a consultation, because the right treatment is the one chosen
              for your hair and your skin — not the one that happened to be booked.
            </p>
          </div>
        </div>
      </section>

      {/* 3. Core philosophy — 4-pillar approach */}
      <section className="px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="text-center">
            <SectionBadge>Our approach</SectionBadge>
            <h2 className="font-display text-ink mt-4 text-3xl font-medium sm:text-4xl">
              The Looks Smart Standard
            </h2>
          </div>

          <div className="mt-12 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-4">
            {pillars.map((pillar) => {
              const Icon = PILLAR_ICONS[pillar.icon] ?? Sparkles;

              return (
                <Card
                  key={pillar.title}
                  className="border-border-soft bg-surface shadow-soft flex h-full flex-col"
                >
                  <CardContent className="flex flex-1 flex-col p-6">
                    <span className="bg-accent-50 text-accent-700 flex size-11 items-center justify-center rounded-xl">
                      <Icon className="size-5" aria-hidden="true" />
                    </span>
                    <h3 className="font-display text-ink mt-5 text-xl font-medium">
                      {pillar.title}
                    </h3>
                    <p className="text-ink-muted mt-2 text-sm leading-6">{pillar.description}</p>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        </div>
      </section>

      {/* 4. Brand story */}
      <section className="border-border-soft bg-surface border-y px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[0.9fr_1.1fr] lg:gap-16">
          <div>
            <SectionBadge>Our story</SectionBadge>
            <h2 className="font-display text-ink mt-4 text-3xl font-medium leading-tight sm:text-4xl">
              Why we are called Looks Smart
            </h2>
          </div>
          <div className="text-ink-muted space-y-5 leading-8">
            <p>
              The name was chosen for what it asks of us rather than what it promises. Looking
              smart is not about a trend or a filter — it is the quiet confidence of walking out
              knowing the work was done properly, and that it will still hold tomorrow morning.
            </p>
            <p>
              We opened on Main Ferozpur Road with one chair and a short service list, and grew only
              as fast as we could train people to our standard. That is why the salon still runs
              four stations rather than twelve, and why the appointment book is deliberately kept
              unhurried.
            </p>
            <p>
              What keeps us here is the transformation itself — the moment a bride sees the finished
              look, or a client who had given up on their hair watches it fall properly for the
              first time in years. Most of our clients came from someone who had that moment and
              told a friend.
            </p>
          </div>
        </div>
      </section>

      {/* 5. Founder & leadership */}
      <section className="px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="text-center">
            <SectionBadge>Our leadership</SectionBadge>
            <h2 className="font-display text-ink mt-4 text-3xl font-medium sm:text-4xl">
              Meet our Founder
            </h2>
          </div>

          <Card className="border-border-soft bg-surface shadow-soft mt-12 overflow-hidden">
            <div className="grid gap-0 md:grid-cols-[minmax(260px,0.8fr)_1.2fr]">
              <div className="bg-accent-50 flex items-center justify-center p-10">
                {founder.photo_url ? (
                  <img
                    src={founder.photo_url}
                    alt={`${founder.name}, ${founder.role}`}
                    className="aspect-[3/4] w-full rounded-xl object-cover"
                    loading="lazy"
                    decoding="async"
                  />
                ) : (
                  // Monogram, not a stock portrait: captioning someone else's photograph with the
                  // founder's name would misrepresent a real person (§10 #42).
                  <div className="flex aspect-[3/4] w-full items-center justify-center rounded-xl bg-gradient-to-br from-accent-100 to-accent-300">
                    <span className="font-display text-accent-700/70 text-7xl" aria-hidden="true">
                      {founder.name.charAt(0)}
                    </span>
                  </div>
                )}
              </div>

              <CardContent className="p-8 lg:p-10">
                <h3 className="font-display text-ink text-2xl font-medium">{founder.name}</h3>
                <p className="text-accent-700 mt-1 text-sm font-semibold uppercase tracking-wider">
                  {founder.role}
                </p>

                <blockquote className="border-accent-500 text-ink mt-6 border-l-2 pl-5 text-lg italic leading-8">
                  &ldquo;{founder.quote}&rdquo;
                </blockquote>

                <p className="text-ink-muted mt-6 leading-7">{founder.bio}</p>
                <p className="text-ink-muted mt-4 leading-7">{founder.narrative}</p>

                <ul className="mt-6 space-y-2">
                  {founder.highlights.map((highlight) => (
                    <li key={highlight} className="text-ink-muted flex gap-3 text-sm leading-6">
                      <span
                        className="bg-accent-500 mt-2 size-1.5 shrink-0 rounded-full"
                        aria-hidden="true"
                      />
                      {highlight}
                    </li>
                  ))}
                </ul>
              </CardContent>
            </div>
          </Card>
        </div>
      </section>

      {/* 6. Location & contact */}
      <section className="border-border-soft bg-surface border-y px-6 py-16 lg:px-10 lg:py-24">
        <div className="mx-auto max-w-6xl">
          <div className="text-center">
            <SectionBadge>Visit us</SectionBadge>
            <h2 className="font-display text-ink mt-4 text-3xl font-medium sm:text-4xl">
              Find Looks Smart Salon
            </h2>
          </div>

          <div className="mt-12 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
            <Card className="border-border-soft bg-ivory flex h-full flex-col">
              <CardContent className="flex flex-1 flex-col p-6">
                <span className="bg-accent-50 text-accent-700 flex size-11 items-center justify-center rounded-xl">
                  <MapPin className="size-5" aria-hidden="true" />
                </span>
                <h3 className="font-display text-ink mt-5 text-lg font-medium">Location</h3>
                {contact.address && (
                  <p className="text-ink-muted mt-2 text-sm leading-6">{contact.address}</p>
                )}
              </CardContent>
            </Card>

            <Card className="border-border-soft bg-ivory flex h-full flex-col">
              <CardContent className="flex flex-1 flex-col p-6">
                <span className="bg-accent-50 text-accent-700 flex size-11 items-center justify-center rounded-xl">
                  <Phone className="size-5" aria-hidden="true" />
                </span>
                <h3 className="font-display text-ink mt-5 text-lg font-medium">Call &amp; WhatsApp</h3>
                <div className="mt-2 space-y-1.5 text-sm">
                  {contact.phone && (
                    <p>
                      <a href={phoneHref} className="text-ink-muted hover:text-accent-600">
                        {contact.phone}
                      </a>
                    </p>
                  )}
                  <p>
                    <a
                      href={contact.whatsapp_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="text-ink-muted hover:text-accent-600 inline-flex items-center gap-1.5"
                    >
                      <MessageCircle className="size-4" aria-hidden="true" /> Chat on WhatsApp
                    </a>
                  </p>
                  {contact.email && (
                    <p>
                      <a
                        href={`mailto:${contact.email}`}
                        className="text-ink-muted hover:text-accent-600 inline-flex items-center gap-1.5 break-all"
                      >
                        <Mail className="size-4 shrink-0" aria-hidden="true" /> {contact.email}
                      </a>
                    </p>
                  )}
                </div>
              </CardContent>
            </Card>

            <Card className="border-border-soft bg-ivory flex h-full flex-col">
              <CardContent className="flex flex-1 flex-col p-6">
                <span className="bg-accent-50 text-accent-700 flex size-11 items-center justify-center rounded-xl">
                  <Clock3 className="size-5" aria-hidden="true" />
                </span>
                <h3 className="font-display text-ink mt-5 text-lg font-medium">Working Hours</h3>
                <div className="text-ink-muted mt-2 space-y-1.5 text-sm leading-6">
                  {hourLines.length > 0 ? (
                    hourLines.map((line) => (
                      <p key={line.days}>
                        <span className="text-ink font-medium">{line.days}</span>
                        <br />
                        {line.hours}
                      </p>
                    ))
                  ) : (
                    <p>Please contact us for our current opening hours.</p>
                  )}
                </div>
              </CardContent>
            </Card>
          </div>
        </div>
      </section>

      {/* 7. Bottom CTA banner */}
      <section className="px-6 py-16 lg:px-10 lg:py-24">
        <div className="bg-ink text-ivory mx-auto max-w-6xl rounded-2xl px-8 py-14 text-center lg:px-16">
          <h2 className="font-display text-3xl font-medium sm:text-4xl">
            Experience the Looks Smart difference.
          </h2>
          <p className="text-ivory/70 mx-auto mt-4 max-w-xl">
            Book your appointment today and indulge in luxury care.
          </p>
          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Button asChild size="lg" variant="accent">
              <Link href="/book">Book Now</Link>
            </Button>
            <Button
              asChild
              size="lg"
              variant="outline"
              className="border-ivory/40 text-ivory hover:bg-ivory/10 bg-transparent"
            >
              <Link href="/services">
                View All Services <ArrowRight />
              </Link>
            </Button>
          </div>
        </div>
      </section>
    </>
  );
}

About.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
