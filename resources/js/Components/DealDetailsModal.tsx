import { Link } from '@inertiajs/react';
import { ArrowRight, Check, MessageCircle } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { formatCurrency } from '@/lib/currency';

export interface DealShowcase {
  id: number;
  slug: string;
  title: string;
  subtitle: string | null;
  category_tag: string | null;
  is_top_deal: boolean;
  original_price: string | null;
  deal_price: string | null;
  savings_percent: number | null;
  included_services: string[];
  description: string | null;
  terms: string | null;
  image_url: string | null;
  ends_at: string | null;
  claim_url: string;
  bundled_services: { id: number; name: string; price: string; image_url: string | null }[];
}

interface DealDetailsModalProps {
  deal: DealShowcase;
  open: boolean;
  onOpenChange: (open: boolean) => void;
  whatsappUrl: string;
}

/**
 * The interactive "Details" popup — high-res image, full checklist, description, price breakdown
 * and terms, plus real Claim Offer / WhatsApp actions. Built on the existing `Dialog` primitive
 * (the same Radix-based component every admin details popup already uses), widened past its
 * default `max-w-lg` to fit an image and a full price breakdown without cramming.
 */
export function DealDetailsModal({ deal, open, onOpenChange, whatsappUrl }: DealDetailsModalProps) {
  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        {deal.image_url && (
          <div className="bg-accent-50 -mx-6 -mt-6 mb-2 h-56 overflow-hidden rounded-t-xl sm:h-64">
            <img
              src={deal.image_url}
              alt={deal.title}
              className="size-full object-cover"
              loading="lazy"
              decoding="async"
            />
          </div>
        )}

        <DialogHeader>
          {deal.category_tag && (
            <span className="bg-accent-50 text-accent-700 inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider">
              {deal.category_tag}
            </span>
          )}
          <DialogTitle className="font-display text-2xl font-medium">{deal.title}</DialogTitle>
          {deal.subtitle && <DialogDescription>{deal.subtitle}</DialogDescription>}
        </DialogHeader>

        {deal.description && (
          <p className="text-ink-muted text-sm leading-6">{deal.description}</p>
        )}

        {deal.included_services.length > 0 && (
          <div>
            <p className="text-ink text-sm font-medium">What&rsquo;s included</p>
            <ul className="mt-2 space-y-1.5">
              {deal.included_services.map((item) => (
                <li key={item} className="text-ink-muted flex gap-2 text-sm leading-6">
                  <Check className="text-accent-600 mt-0.5 size-4 shrink-0" aria-hidden="true" />
                  {item}
                </li>
              ))}
            </ul>
          </div>
        )}

        {deal.deal_price && (
          <div className="border-border-soft bg-ivory rounded-lg border p-4">
            <div className="flex items-end justify-between gap-4">
              <div>
                {deal.original_price && (
                  <p className="text-ink-muted text-sm line-through">
                    {formatCurrency(deal.original_price)}
                  </p>
                )}
                <p className="text-ink font-display text-2xl font-medium">
                  {formatCurrency(deal.deal_price)}
                </p>
              </div>
              {deal.savings_percent !== null && deal.savings_percent > 0 && (
                <span className="bg-accent-500 text-ivory rounded-full px-3 py-1 text-xs font-semibold">
                  {deal.savings_percent}% OFF
                </span>
              )}
            </div>
          </div>
        )}

        {deal.terms && (
          <div>
            <p className="text-ink text-sm font-medium">Terms &amp; conditions</p>
            <p className="text-ink-muted mt-1.5 text-xs leading-5">{deal.terms}</p>
          </div>
        )}

        <div className="flex flex-col gap-2 sm:flex-row">
          <Button asChild variant="accent" className="flex-1">
            <Link href={deal.claim_url} prefetch>
              Claim Offer Now <ArrowRight className="size-4" aria-hidden="true" />
            </Link>
          </Button>
          <Button asChild variant="outline" className="flex-1">
            <a href={whatsappUrl} target="_blank" rel="noopener noreferrer">
              <MessageCircle className="size-4" aria-hidden="true" /> Book on WhatsApp
            </a>
          </Button>
        </div>
      </DialogContent>
    </Dialog>
  );
}
