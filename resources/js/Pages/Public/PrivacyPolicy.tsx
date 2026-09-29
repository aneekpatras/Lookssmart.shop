import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import * as React from 'react';

import { SeoHead } from '@/Components/SeoHead';
import PublicLayout from '@/Layouts/PublicLayout';

interface PrivacyPolicyProps {
  business: {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
  };
  effectiveDate: string;
}

/**
 * The salon's privacy policy. Contact details in section 7 come from the same admin-editable
 * Settings the rest of the site reads, so a change of phone number or address can never leave a
 * stale address sitting in a legal document. Every claim here describes what this application
 * genuinely does — the third-party list matches the integrations actually wired up (see
 * DEPLOYMENT.md), and the cookie section describes the real session/CSRF cookies Laravel sets
 * rather than a generic template list.
 */
export default function PrivacyPolicy({ business, effectiveDate }: PrivacyPolicyProps) {
  return (
    <>
      <SeoHead
        title="Privacy Policy"
        description={`How ${business.name} collects, uses, and protects your personal information.`}
      />

      <div className="bg-ivory min-h-screen px-6 py-16">
        <div className="mx-auto max-w-3xl">
          <Link
            href="/"
            className="text-ink-muted hover:text-accent-600 inline-flex items-center gap-2 text-sm transition-colors"
          >
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            Back to Home
          </Link>

          <h1 className="font-display text-ink mt-6 text-3xl font-medium sm:text-4xl">
            Privacy Policy
          </h1>
          <p className="text-ink-muted mt-2 text-sm">Effective Date: {effectiveDate}</p>

          <div className="mt-10 space-y-10">
            <Section title="1. Information We Collect">
              <p>
                When you book an appointment, contact us, or create an account, we collect the
                details you choose to give us: your name, phone number, email address, and the
                services you are interested in. If you leave a review or send us a message, we
                store its contents alongside your booking history.
              </p>
              <p>
                We also record basic technical information automatically — your IP address, browser
                type, and the pages you visit — which is used to keep the site secure and working
                correctly.
              </p>
            </Section>

            <Section title="2. How We Use Your Data">
              <p>We use the information we hold to:</p>
              <ul className="text-ink-muted list-disc space-y-1.5 pl-5">
                <li>confirm, reschedule, and remind you about your appointments;</li>
                <li>keep a record of the services you have had, so your stylist can pick up where they left off;</li>
                <li>reply to enquiries you send us;</li>
                <li>send offers or salon news, but only if you have opted in — and you can opt out at any time;</li>
                <li>detect and prevent fraudulent or abusive use of the booking system.</li>
              </ul>
              <p>
                We do not sell your personal information, and we do not share it for advertising
                purposes.
              </p>
            </Section>

            <Section title="3. Data Protection">
              <p>
                Your data is transmitted over an encrypted connection and stored on access-controlled
                servers. Passwords are hashed and never stored in a readable form; sensitive
                credentials are encrypted at rest. Access to customer records inside the salon is
                limited to staff whose role genuinely requires it, and administrative accounts are
                protected with two-factor authentication.
              </p>
              <p>
                We keep your booking history for as long as you remain a client, and remove or
                anonymise it on request unless we are required to retain it for accounting purposes.
              </p>
            </Section>

            <Section title="4. Third-Party Services">
              <p>
                We rely on a small number of trusted providers to run the salon, and they only
                receive the minimum information needed to do their job:
              </p>
              <ul className="text-ink-muted list-disc space-y-1.5 pl-5">
                <li>
                  <strong className="text-ink font-medium">Email and SMS delivery</strong> — to send
                  your booking confirmations and reminders.
                </li>
                <li>
                  <strong className="text-ink font-medium">Google Maps</strong> — to show our
                  location on the contact page.
                </li>
                <li>
                  <strong className="text-ink font-medium">Error and performance monitoring</strong>{' '}
                  — to alert us when something on the site breaks. Personal details are filtered out
                  before these reports are sent.
                </li>
                <li>
                  <strong className="text-ink font-medium">Website analytics</strong> — where
                  enabled, to understand which pages people find useful.
                </li>
              </ul>
              <p>
                If we ever accept online payments, card details are handled entirely by the payment
                provider and never stored on our own systems.
              </p>
            </Section>

            <Section title="5. Cookies">
              <p>
                We use a small number of cookies that are necessary for the site to function: one
                keeps you signed in and remembers your place in the booking process, and another
                protects forms against cross-site request forgery. These cannot be switched off
                without breaking the booking system.
              </p>
              <p>
                If analytics is enabled, it may set additional cookies to count visits. You can
                block or delete cookies in your browser settings, though some parts of the site may
                then stop working as intended.
              </p>
            </Section>

            <Section title="6. Your Rights">
              <p>You may, at any time, ask us to:</p>
              <ul className="text-ink-muted list-disc space-y-1.5 pl-5">
                <li>tell you what personal information we hold about you;</li>
                <li>provide a copy of it;</li>
                <li>correct anything that is wrong or out of date;</li>
                <li>delete your account and personal details;</li>
                <li>stop sending you marketing messages.</li>
              </ul>
              <p>
                Just contact us using the details below and we will respond as quickly as we can.
              </p>
            </Section>

            <Section title="7. Contact Us">
              <p>
                If you have a question about this policy or about how your information is handled,
                please get in touch:
              </p>
              <ul className="text-ink-muted space-y-1.5">
                <li>
                  <strong className="text-ink font-medium">{business.name}</strong>
                </li>
                {business.address && <li>{business.address}</li>}
                {business.phone && (
                  <li>
                    Phone:{' '}
                    <a
                      href={`tel:${business.phone.replace(/[^\d+]/g, '')}`}
                      className="hover:text-accent-600 transition-colors"
                    >
                      {business.phone}
                    </a>
                  </li>
                )}
                {business.email && (
                  <li>
                    Email:{' '}
                    <a
                      href={`mailto:${business.email}`}
                      className="hover:text-accent-600 break-all transition-colors"
                    >
                      {business.email}
                    </a>
                  </li>
                )}
              </ul>
            </Section>
          </div>

          <div className="border-border-soft mt-12 border-t pt-8">
            <Link
              href="/"
              className="text-ink-muted hover:text-accent-600 inline-flex items-center gap-2 text-sm transition-colors"
            >
              <ArrowLeft className="h-4 w-4" aria-hidden="true" />
              Back to Home
            </Link>
          </div>
        </div>
      </div>
    </>
  );
}

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section>
      <h2 className="font-display text-ink text-xl font-medium">{title}</h2>
      <div className="text-ink-muted mt-3 space-y-3 text-sm leading-relaxed">{children}</div>
    </section>
  );
}

PrivacyPolicy.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
