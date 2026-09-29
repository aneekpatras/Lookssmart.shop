import { Link } from '@inertiajs/react';
import { Clock3, Search, Sparkles } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { CategoryPillBar } from '@/Components/CategoryPillBar';
import { SeoHead } from '@/Components/SeoHead';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import PublicLayout from '@/Layouts/PublicLayout';
import { addToCart } from '@/lib/cart';
import { formatCurrency } from '@/lib/currency';

interface ServiceCard {
  id: number;
  name: string;
  slug: string;
  category: string | null;
  category_id: number;
  duration_min: number;
  price: string;
  image_url: string | null;
}
interface Category {
  id: number;
  name: string;
}
interface ServicesProps {
  services: ServiceCard[];
  categories: Category[];
  filters: { search: string | null; category: number | null };
}

const TOP_ANCHOR = 'services-top';

/** Mirrors ServiceDetail's formatter — "3 hours" reads better than "180 min" on a card. */
function formatDuration(minutes: number): string {
  if (minutes < 60) return `${minutes} min`;
  const hours = Math.floor(minutes / 60);
  const rest = minutes % 60;
  return rest === 0 ? `${hours} hr` : `${hours} hr ${rest} min`;
}

function ServiceCardTile({ service }: { service: ServiceCard }) {
  function handleAddToCart() {
    addToCart({ kind: 'service', label: service.name, service }, toast);
  }

  return (
    <Card className="border-border-soft bg-surface shadow-soft flex flex-col overflow-hidden">
      <div className="bg-accent-50 h-[190px] shrink-0">
        {service.image_url ? (
          <img
            src={service.image_url}
            alt={service.name}
            loading="lazy"
            decoding="async"
            className="size-full object-cover"
          />
        ) : (
          <div className="text-accent-700 flex size-full items-center justify-center">
            <Sparkles className="size-8" />
          </div>
        )}
      </div>
      <CardContent className="flex flex-1 flex-col p-4">
        <p className="text-accent-700 text-xs font-semibold uppercase tracking-wider">
          {service.category}
        </p>
        <h2 className="mt-1.5 text-lg font-medium leading-snug">{service.name}</h2>
        <div className="text-ink-muted mt-auto flex items-center justify-between pt-3 text-sm">
          <span className="inline-flex items-center gap-1">
            <Clock3 className="size-4" /> {formatDuration(service.duration_min)}
          </span>
          <span className="text-ink font-semibold">{formatCurrency(service.price)}</span>
        </div>
        <div className="mt-3 grid grid-cols-2 gap-2">
          <Button asChild variant="outline" size="sm">
            <Link href={`/services/${service.slug}`}>Details</Link>
          </Button>
          <Button type="button" variant="accent" size="sm" onClick={handleAddToCart}>
            + Add to Cart
          </Button>
        </div>
      </CardContent>
    </Card>
  );
}

/**
 * Rebuilt from a single filtered grid (server round trip per category/search click) into stacked
 * per-category sections with a sticky, scrollspy-highlighted pill bar and instant client-side search
 * (ad hoc task 24) — every active service is already present in `services` on load (the backend
 * never paginates it), so there is no reason to round-trip the server for either filter anymore.
 * `?search=`/`?category=` deep links still work exactly as before (the backend is unchanged) — they
 * just narrow which services arrive in the first place, same net effect as the old filtered view.
 */
export default function Services({ services, categories, filters }: ServicesProps) {
  const [search, setSearch] = React.useState(filters.search ?? '');

  const visibleServices = React.useMemo(() => {
    const query = search.trim().toLowerCase();
    if (!query) return services;
    return services.filter((service) => service.name.toLowerCase().includes(query));
  }, [services, search]);

  const sections = React.useMemo(
    () =>
      categories
        .map((category) => ({
          category,
          items: visibleServices.filter((service) => service.category_id === category.id),
        }))
        .filter((section) => section.items.length > 0),
    [categories, visibleServices],
  );

  const pills = React.useMemo(
    () => sections.map((section) => ({ id: `services-category-${section.category.id}`, label: section.category.name })),
    [sections],
  );

  return (
    <>
      <SeoHead title="Services" description="Explore hair, skin, and nail services at Looks Smart Beauty Salon." />
      <section className="bg-ink px-6 py-16 text-ivory lg:px-10">
        <div className="mx-auto max-w-6xl">
          <p className="text-accent-300 text-sm font-semibold uppercase tracking-[0.18em]">The menu</p>
          <h1 className="mt-3 text-5xl font-medium sm:text-6xl">Services for your next chapter.</h1>
          <p className="mt-5 max-w-xl text-ivory/70">Choose a ritual, then let us take care of the details.</p>
        </div>
      </section>

      <section id={TOP_ANCHOR} className="scroll-mt-24 px-6 py-12 lg:px-10">
        <div className="mx-auto max-w-6xl">
          <div className="relative w-full max-w-md">
            <Search className="text-ink-muted absolute left-3 top-1/2 size-4 -translate-y-1/2" aria-hidden="true" />
            <Input
              aria-label="Search services"
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder="Search services, packages, or treatments..."
              className="pl-9"
            />
          </div>

          {pills.length > 0 && (
            <CategoryPillBar
              pills={pills}
              topAnchorId={TOP_ANCHOR}
              ariaLabel="Jump to service category"
              className="mt-6"
            />
          )}

          <div className="mt-10 space-y-14">
            {sections.map(({ category, items }) => (
              <div key={category.id} id={`services-category-${category.id}`} className="scroll-mt-40">
                <h2 className="font-display text-2xl font-medium">{category.name}</h2>
                <div className="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                  {items.map((service) => (
                    <ServiceCardTile key={service.id} service={service} />
                  ))}
                </div>
              </div>
            ))}
          </div>

          {sections.length === 0 && (
            <div className="py-20 text-center">
              <Sparkles className="text-accent-700 mx-auto size-8" />
              <p className="text-ink-muted mt-4">No services match that search.</p>
            </div>
          )}
        </div>
      </section>
    </>
  );
}

Services.layout = (page: React.ReactNode) => <PublicLayout solidHeader>{page}</PublicLayout>;
