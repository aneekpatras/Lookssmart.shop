import { Link } from '@inertiajs/react';
import { ArrowRight, Check, Clock3, Info, ShieldCheck, Star } from 'lucide-react';
import * as React from 'react';

import { SeoHead } from '@/Components/SeoHead';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatCurrency } from '@/lib/currency';

interface Service {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  category: string | null;
  duration_min: number;
  price: string;
  rating: number | null;
  review_count: number;
  image_url: string | null;
}

interface Review {
  id: number;
  rating: number;
  body: string;
  customer_name: string;
}

interface Guide {
  procedure: { title: string; detail: string }[];
  benefits: string[];
  suitability: string;
  aftercare: string[];
}

interface ServiceDetailProps {
  service: Service;
  guide: Guide;
  relatedServices: Service[];
  testimonials: Review[];
  jsonLdSchema: Record<string, unknown>;
}

/**
 * Formats a raw minute count the way a client reads it — "3 hours", "1 hr 30 min" — rather than
 * "180 minutes", which stops meaning anything past about an hour.
 */
function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`;
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  const hourLabel = `${hours} ${hours === 1 ? 'hour' : 'hours'}`;
  return rest === 0 ? hourLabel : `${hourLabel} ${rest} min`;
}

export default function ServiceDetail({
  service,
  guide,
  relatedServices,
  testimonials,
  jsonLdSchema,
}: ServiceDetailProps) {
  const bookHref = `/book?service=${service.id}`;

  return (
    <>
      <SeoHead
        title={service.name}
        description={service.description ?? `Book ${service.name} at Looks Smart Beauty Salon.`}
        image={service.image_url}
        jsonLd={jsonLdSchema}
      />

      {/* Banner */}
      <section className="relative">
        <div className="bg-accent-50 h-[280px] w-full overflow-hidden sm:h-[360px]">
          {service.image_url ? (
            <img
              src={service.image_url}
              alt={service.name}
              className="size-full object-cover"
              decoding="async"
              fetchPriority="high"
            />
          ) : (
            <div className="text-accent-700 flex size-full items-center justify-center">
              <Star className="size-12" aria-hidden="true" />
            </div>
          )}
        </div>
        <div className="from-ink/80 via-ink/40 absolute inset-0 bg-gradient-to-t to-transparent" />
        <div className="absolute inset-x-0 bottom-0 px-6 pb-8 lg:px-10">
          <div className="mx-auto max-w-6xl">
            <p className="text-accent-300 text-sm font-semibold uppercase tracking-[0.18em]">
              {service.category}
            </p>
            <h1 className="text-ivory mt-2 text-4xl font-medium sm:text-5xl">{service.name}</h1>
          </div>
        </div>
      </section>

      {/* Overview + booking summary */}
      <section className="px-6 py-12 lg:px-10">
        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1.4fr_.6fr] lg:items-start">
          <div>
            <h2 className="text-2xl">Overview</h2>
            <p className="text-ink-muted mt-4 leading-7">{service.description}</p>

            {guide.benefits.length > 0 && (
              <div className="mt-10">
                <h2 className="text-2xl">Benefits</h2>
                <ul className="mt-4 grid gap-3 sm:grid-cols-2">
                  {guide.benefits.map((benefit) => (
                    <li key={benefit} className="text-ink-muted flex gap-3 text-sm leading-6">
                      <Check className="text-accent-600 mt-0.5 size-4 shrink-0" aria-hidden="true" />
                      {benefit}
                    </li>
                  ))}
                </ul>
              </div>
            )}
          </div>

          <Card className="border-border-soft bg-surface shadow-soft lg:sticky lg:top-28">
            <CardContent className="p-6">
              <p className="text-accent-700 text-xs font-semibold uppercase tracking-wider">
                This appointment
              </p>
              <p className="text-ink mt-3 text-3xl font-semibold">{formatCurrency(service.price)}</p>
              <p className="text-ink-muted mt-2 inline-flex items-center gap-2 text-sm">
                <Clock3 className="text-accent-700 size-4" aria-hidden="true" />
                {formatDuration(service.duration_min)} in the chair
              </p>
              {service.review_count > 0 && (
                <p
                  className="text-accent-700 mt-3 text-sm"
                  aria-label={`${service.rating} out of 5 stars from ${service.review_count} reviews`}
                >
                  <Star className="mr-1 inline size-4 fill-current" aria-hidden="true" />
                  {service.rating} ({service.review_count})
                </p>
              )}
              <Button asChild size="lg" variant="accent" className="mt-6 w-full">
                <Link href={bookHref}>
                  Book this service <ArrowRight />
                </Link>
              </Button>
              <p className="text-ink-muted mt-3 text-xs">
                Prices in PKR. Your slot is held while you complete the booking.
              </p>
            </CardContent>
          </Card>
        </div>
      </section>

      {/* Treatment procedure */}
      {guide.procedure.length > 0 && (
        <section className="bg-surface px-6 py-16 lg:px-10">
          <div className="mx-auto max-w-4xl">
            <h2 className="text-3xl">What happens in the appointment</h2>
            <ol className="border-accent-200 mt-8 space-y-8 border-l pl-8">
              {guide.procedure.map((step, index) => (
                <li key={step.title} className="relative">
                  <span
                    className="bg-accent-600 text-ivory ring-accent-100 absolute -left-[2.6rem] flex size-7 items-center justify-center rounded-full text-xs font-semibold ring-4"
                    aria-hidden="true"
                  >
                    {index + 1}
                  </span>
                  <h3 className="text-ink text-lg font-medium">{step.title}</h3>
                  <p className="text-ink-muted mt-1 text-sm leading-6">{step.detail}</p>
                </li>
              ))}
            </ol>
          </div>
        </section>
      )}

      {/* Suitability + aftercare */}
      <section className="px-6 py-16 lg:px-10">
        <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-2">
          <Card className="border-border-soft bg-surface">
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <Info className="text-accent-700 size-5" aria-hidden="true" />
                <h2 className="text-xl">Is this right for me?</h2>
              </div>
              <p className="text-ink-muted mt-4 text-sm leading-7">{guide.suitability}</p>
            </CardContent>
          </Card>

          <Card className="border-border-soft bg-surface">
            <CardContent className="p-6">
              <div className="flex items-center gap-3">
                <ShieldCheck className="text-accent-700 size-5" aria-hidden="true" />
                <h2 className="text-xl">Aftercare</h2>
              </div>
              <ul className="mt-4 space-y-2.5">
                {guide.aftercare.map((tip) => (
                  <li key={tip} className="text-ink-muted flex gap-3 text-sm leading-6">
                    <span
                      className="bg-accent-500 mt-2 size-1.5 shrink-0 rounded-full"
                      aria-hidden="true"
                    />
                    {tip}
                  </li>
                ))}
              </ul>
            </CardContent>
          </Card>
        </div>
      </section>

      {/* Testimonials */}
      {testimonials.length > 0 && (
        <section className="bg-surface px-6 py-16 lg:px-10">
          <div className="mx-auto max-w-6xl">
            <h2 className="text-3xl">What clients say</h2>
            <div className="mt-7 grid gap-4 md:grid-cols-3">
              {testimonials.map((review) => (
                <Card key={review.id}>
                  <CardContent className="p-5">
                    <p className="text-accent-700" aria-label={`${review.rating} out of 5 stars`}>
                      {'★'.repeat(review.rating)}
                      {'☆'.repeat(5 - review.rating)}
                    </p>
                    <p className="mt-3 leading-7">&ldquo;{review.body}&rdquo;</p>
                    <p className="text-ink-muted mt-4 text-sm">{review.customer_name}</p>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Related services — previously fetched by the controller but never rendered. */}
      {relatedServices.length > 0 && (
        <section className="px-6 py-16 lg:px-10">
          <div className="mx-auto max-w-6xl">
            <h2 className="text-3xl">You might also like</h2>
            <div className="mt-7 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
              {relatedServices.map((related) => (
                <Card
                  key={related.id}
                  className="border-border-soft bg-surface shadow-soft overflow-hidden"
                >
                  <div className="bg-accent-50 h-[160px]">
                    {related.image_url && (
                      <img
                        src={related.image_url}
                        alt={related.name}
                        loading="lazy"
                        decoding="async"
                        className="size-full object-cover"
                      />
                    )}
                  </div>
                  <CardContent className="p-4">
                    <h3 className="text-lg leading-tight">{related.name}</h3>
                    <div className="text-ink-muted mt-3 flex items-center justify-between text-sm">
                      <span className="inline-flex items-center gap-1">
                        <Clock3 className="size-4" aria-hidden="true" />{' '}
                        {formatDuration(related.duration_min)}
                      </span>
                      <span className="text-ink font-semibold">
                        {formatCurrency(related.price)}
                      </span>
                    </div>
                    <Button asChild variant="outline" className="mt-4 w-full">
                      <Link href={`/services/${related.slug}`}>View details</Link>
                    </Button>
                  </CardContent>
                </Card>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* Closing CTA */}
      <section className="bg-ink text-ivory px-6 py-16 lg:px-10">
        <div className="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 sm:flex-row sm:items-center">
          <div>
            <h2 className="text-3xl font-medium">Ready to book {service.name}?</h2>
            <p className="text-ivory/70 mt-2 text-sm">
              {formatDuration(service.duration_min)} · {formatCurrency(service.price)}
            </p>
          </div>
          <Button asChild size="lg" variant="accent">
            <Link href={bookHref}>
              Book this service <ArrowRight />
            </Link>
          </Button>
        </div>
      </section>
    </>
  );
}

ServiceDetail.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
