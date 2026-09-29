import { Link, usePage } from '@inertiajs/react';
import { AnimatePresence, motion, useReducedMotion } from 'framer-motion';
import { Mail, MapPin, Menu, Phone, X } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Toaster } from '@/Components/ui/toaster';
import { FacebookIcon, InstagramIcon } from '@/Components/SocialIcons';
import { HeaderCartButton } from '@/Components/HeaderCartButton';
import { HeaderUserMenu } from '@/Components/HeaderUserMenu';
import { NavigationProgressOverlay } from '@/Components/NavigationProgressOverlay';
import { SmoothScroll } from '@/Components/SmoothScroll';

/**
 * The globally shared `site` block (HandleInertiaRequests::share) — contact details, social
 * profiles and a collapsed opening-hours summary, all sourced from admin-editable Settings and the
 * real `business_hours` rows. Declared locally, matching SeoHead.tsx's pattern for shared props.
 *
 * Named `site` rather than `business` on purpose: Contact and PrivacyPolicy each pass their own
 * page-level `business` prop, and Inertia's shallow prop merge would shadow this object entirely.
 */
interface SharedProps {
  [key: string]: unknown;
  site: {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    facebook: string | null;
    instagram: string | null;
    hours: { days: string; hours: string }[];
  };
  auth: { user: { name: string; email: string } | null };
}

const NAV_LINKS = [
  { label: 'Home', href: '/' },
  { label: 'About', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Deals', href: '/deals' },
  { label: 'Gallery', href: '/gallery' },
  { label: 'Blog', href: '/blog' },
  { label: 'Contact', href: '/contact' },
];

function FloatingNav({ solidHeader }: { solidHeader: boolean }) {
  const [scrolled, setScrolled] = React.useState(false);
  const [mobileOpen, setMobileOpen] = React.useState(false);
  const page = usePage<SharedProps>();
  const { url } = page;
  const { auth } = page.props;

  React.useEffect(() => {
    function onScroll() {
      setScrolled(window.scrollY > 16);
    }
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Card-dense catalog pages (Services/Deals/Book) opt into a fully solid nav background instead of
  // the decorative `glass` (translucent + blurred) look every other page keeps — over a hero image
  // or a short page, `glass` reads as intentional; over a long, constantly-scrolling grid of cards
  // passing directly underneath a `fixed` header, the same translucency lets card text visibly bleed
  // through it (a real, screenshotted bug, ad hoc task 26 fix — not merely a hypothetical one).
  const navSurfaceClass = solidHeader
    ? 'bg-ivory border-border-soft border shadow-soft'
    : 'glass';

  return (
    <header
      className={`fixed inset-x-0 top-0 z-40 flex justify-center px-4 pt-4 ${solidHeader ? 'bg-ivory' : ''}`}
    >
      <nav
        className={`${navSurfaceClass} motion-safe-transition flex w-full max-w-5xl items-center justify-between rounded-full px-5 transition-[padding,box-shadow] duration-300 ${
          scrolled ? 'py-2' : 'py-3'
        }`}
      >
        <Link href="/" className="font-display text-ink text-lg font-medium">
          Looks Smart
        </Link>

        <ul className="hidden items-center gap-6 md:flex">
          {NAV_LINKS.map((link) => (
            <li key={link.href}>
              <Link
                href={link.href}
                className={`motion-safe-transition hover:text-accent-600 text-sm transition-colors ${
                  url === link.href ? 'text-accent-600' : 'text-ink-muted'
                }`}
              >
                {link.label}
              </Link>
            </li>
          ))}
        </ul>

        {/* Cart + account render EXACTLY ONCE (not once per breakpoint) — each carries its own
            internal open/closed state (a Sheet drawer, a dropdown/auth modal), and mounting two
            separate copies simultaneously (one hidden via `md:hidden`, one via `hidden md:flex`)
            caused a genuine render crash, not just a mismatched selector in a test (ad hoc task 27
            fix). "Book Now" and the hamburger toggle are the only pieces that actually differ
            between breakpoints, so THEY are what gets shown/hidden with `md:` utilities — the cart
            and account icons stay visible on both, giving mobile the always-one-tap access the task
            asked for "for free", without duplicating any stateful component. */}
        <div className="flex items-center gap-1.5 sm:gap-3">
          <HeaderCartButton />
          <Button asChild size="sm" variant="accent" className="hidden md:inline-flex">
            <Link href="/book">Book Now</Link>
          </Button>
          <HeaderUserMenu user={auth.user} />
          <button
            type="button"
            className="text-ink rounded-md p-2 md:hidden"
            aria-label={mobileOpen ? 'Close menu' : 'Open menu'}
            aria-expanded={mobileOpen}
            onClick={() => setMobileOpen((open) => !open)}
          >
            {mobileOpen ? <X className="size-5" /> : <Menu className="size-5" />}
          </button>
        </div>
      </nav>

      {mobileOpen ? (
        <div
          className={`${navSurfaceClass} absolute inset-x-4 top-[4.5rem] z-40 flex flex-col gap-1 rounded-2xl p-4 md:hidden`}
        >
          {NAV_LINKS.map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="text-ink hover:bg-accent-50 rounded-md px-3 py-2 text-sm"
              onClick={() => setMobileOpen(false)}
            >
              {link.label}
            </Link>
          ))}
        </div>
      ) : null}
    </header>
  );
}

const FOOTER_LINKS = [
  { label: 'Home', href: '/' },
  { label: 'About Us', href: '/about' },
  { label: 'Services', href: '/services' },
  { label: 'Gallery', href: '/gallery' },
  { label: 'Contact', href: '/contact' },
  { label: 'Privacy Policy', href: '/privacy-policy' },
];

/**
 * Every value here comes from the globally shared `site` prop
 * (HandleInertiaRequests::share), which reads the admin-editable Settings and the real
 * `business_hours` rows. Nothing is hardcoded: the footer used to carry its own literal hours
 * string, which could silently disagree with what the Contact page showed from the database.
 * Each block is conditional, so an unconfigured setting renders as absent rather than as a
 * fabricated placeholder.
 */
function Footer() {
  const { site } = usePage<SharedProps>().props;
  const phoneHref = site.phone ? `tel:${site.phone.replace(/[^\d+]/g, '')}` : undefined;

  return (
    <footer className="border-border-soft bg-surface border-t px-6 py-12">
      <div className="mx-auto grid max-w-5xl gap-8 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <p className="font-display text-ink text-lg font-medium">{site.name}</p>
          <p className="text-ink-muted mt-2 text-sm">
            Precision hair styling, signature makeup artistry, and personal consultation — in the
            heart of Lahore.
          </p>
          {(site.facebook || site.instagram) && (
            <div className="mt-4 flex items-center gap-3">
              {site.facebook && (
                <a
                  href={site.facebook}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Looks Smart Beauty Salon on Facebook"
                  className="text-ink-muted hover:text-accent-600 transition-colors"
                >
                  <FacebookIcon className="h-5 w-5" aria-hidden="true" />
                </a>
              )}
              {site.instagram && (
                <a
                  href={site.instagram}
                  target="_blank"
                  rel="noopener noreferrer"
                  aria-label="Looks Smart Beauty Salon on Instagram"
                  className="text-ink-muted hover:text-accent-600 transition-colors"
                >
                  <InstagramIcon className="h-5 w-5" aria-hidden="true" />
                </a>
              )}
            </div>
          )}
        </div>

        <div className="text-ink-muted text-sm">
          <p className="text-ink font-medium">Explore</p>
          <ul className="mt-2 space-y-1.5">
            {FOOTER_LINKS.map((link) => (
              <li key={link.href}>
                <Link href={link.href} className="hover:text-accent-600 transition-colors">
                  {link.label}
                </Link>
              </li>
            ))}
          </ul>
        </div>

        {site.hours.length > 0 && (
          <div className="text-ink-muted text-sm">
            <p className="text-ink font-medium">Opening Hours</p>
            <ul className="mt-2 space-y-1.5">
              {site.hours.map((entry) => (
                <li key={entry.days}>
                  <span className="block">{entry.days}</span>
                  <span className="block">{entry.hours}</span>
                </li>
              ))}
            </ul>
          </div>
        )}

        <div className="text-ink-muted text-sm">
          <p className="text-ink font-medium">Contact</p>
          <ul className="mt-2 space-y-2">
            {site.address && (
              <li className="flex gap-2">
                <MapPin className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <span>{site.address}</span>
              </li>
            )}
            {site.phone && (
              <li className="flex gap-2">
                <Phone className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <a href={phoneHref} className="hover:text-accent-600 transition-colors">
                  {site.phone}
                </a>
              </li>
            )}
            {site.email && (
              <li className="flex gap-2">
                <Mail className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                <a
                  href={`mailto:${site.email}`}
                  className="hover:text-accent-600 break-all transition-colors"
                >
                  {site.email}
                </a>
              </li>
            )}
          </ul>
        </div>
      </div>
      <p className="text-ink-muted mx-auto mt-8 max-w-5xl text-xs">
        © 2026 {site.name}. All rights reserved.
      </p>
    </footer>
  );
}

export default function PublicLayout({
  children,
  solidHeader = false,
}: {
  children: React.ReactNode;
  /** Card-dense catalog pages (Services/Deals/Book) pass this to render the floating nav on a solid
   * background rather than the default translucent `glass` look — see `FloatingNav` for why. */
  solidHeader?: boolean;
}) {
  const { url } = usePage();
  const prefersReducedMotion = useReducedMotion();

  return (
    <SmoothScroll>
      <div className="flex min-h-screen flex-col">
        <a
          href="#main-content"
          className="bg-ink text-ivory focus:not-sr-only sr-only fixed top-4 left-4 z-50 rounded-md px-4 py-2 text-sm font-medium"
        >
          Skip to main content
        </a>
        <FloatingNav solidHeader={solidHeader} />
        <Toaster />
        <NavigationProgressOverlay />
        <main id="main-content" className="flex-1 pt-24">
          <AnimatePresence mode="wait" initial={false}>
            <motion.div
              key={url}
              initial={prefersReducedMotion ? undefined : { opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={prefersReducedMotion ? undefined : { opacity: 0, y: -8 }}
              transition={{ duration: 0.35, ease: [0.16, 1, 0.3, 1] }}
            >
              {children}
            </motion.div>
          </AnimatePresence>
        </main>
        <Footer />
      </div>
    </SmoothScroll>
  );
}
