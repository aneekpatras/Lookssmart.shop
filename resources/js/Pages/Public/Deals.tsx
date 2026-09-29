import * as React from 'react';

import { CategoryPillBar } from '@/Components/CategoryPillBar';
import { DealCard } from '@/Components/DealCard';
import { DealCarousel } from '@/Components/DealCarousel';
import { DealDetailsModal, type DealShowcase } from '@/Components/DealDetailsModal';
import { SeoHead } from '@/Components/SeoHead';
import PublicLayout from '@/Layouts/PublicLayout';

interface DealSection {
  tag: string;
  deals: DealShowcase[];
}

interface DealsProps {
  sections: DealSection[];
  availableTags: string[];
  whatsapp: { display_phone: string; chat_url: string };
}

const TOP_ANCHOR = 'deals-top';

function tagAnchorId(tag: string): string {
  return `deals-section-${tag.toLowerCase().replace(/\s+/g, '-')}`;
}

/**
 * Rebuilt from "one tag filters the rest away" to stacked sections (every tag always rendered) with
 * a sticky, scrollspy-highlighted pill bar that scrolls to a section instead of hiding the others
 * (ad hoc task 24) — matches the same pattern now used on Services and Book.
 */
export default function Deals({ sections, availableTags, whatsapp }: DealsProps) {
  const [detailsDeal, setDetailsDeal] = React.useState<DealShowcase | null>(null);

  const pills = React.useMemo(
    () => availableTags.map((tag) => ({ id: tagAnchorId(tag), label: tag })),
    [availableTags],
  );

  return (
    <>
      <SeoHead
        title="Deals & Offers"
        description="Limited-time special offers and curated beauty packages tailored for you."
      />

      <section className="bg-ink px-6 py-16 text-ivory lg:px-10">
        <div className="mx-auto max-w-6xl">
          <p className="text-accent-300 text-sm font-semibold uppercase tracking-[0.18em]">
            Special offers
          </p>
          <h1 className="mt-3 text-5xl font-medium sm:text-6xl">Exclusive Deals &amp; Packages</h1>
          <p className="mt-5 max-w-xl text-ivory/70">
            Limited-time special offers and curated beauty packages tailored for you.
          </p>
        </div>
      </section>

      <section id={TOP_ANCHOR} className="scroll-mt-24 px-6 py-12 lg:px-10">
        <div className="mx-auto max-w-6xl">
          {pills.length > 0 && (
            <CategoryPillBar pills={pills} topAnchorId={TOP_ANCHOR} ariaLabel="Jump to deal category" />
          )}

          {sections.length === 0 && (
            <div className="py-20 text-center">
              <p className="text-ink-muted mt-4">No active deals right now — check back soon.</p>
            </div>
          )}

          <div className="mt-10 space-y-14">
            {sections.map((section) => (
              <div key={section.tag} id={tagAnchorId(section.tag)} className="scroll-mt-40">
                <h2 className="font-display text-2xl font-medium">{section.tag}</h2>
                <div className="mt-5">
                  <DealCarousel itemCount={section.deals.length} ariaLabel={`${section.tag} carousel`}>
                    {[section.deals, section.deals.length > 1 ? section.deals : []].map(
                      (pass, passIndex) =>
                        pass.map((deal) => (
                          <div key={`${passIndex}-${deal.id}`} aria-hidden={passIndex === 1 ? true : undefined}>
                            <DealCard deal={deal} onViewDetails={setDetailsDeal} />
                          </div>
                        )),
                    )}
                  </DealCarousel>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {detailsDeal && (
        <DealDetailsModal
          deal={detailsDeal}
          open={detailsDeal !== null}
          onOpenChange={(open) => {
            if (!open) setDetailsDeal(null);
          }}
          whatsappUrl={whatsapp.chat_url}
        />
      )}
    </>
  );
}

Deals.layout = (page: React.ReactNode) => <PublicLayout solidHeader>{page}</PublicLayout>;
