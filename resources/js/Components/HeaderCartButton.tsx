import { Link } from '@inertiajs/react';
import { ShoppingBag, Sparkles, Trash2 } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/Components/ui/sheet';
import {
  CART_CHANGED_EVENT,
  cartBookingUrl,
  type CartItem,
  getCartItems,
  removeFromCart,
} from '@/lib/cart';
import { formatCurrency } from '@/lib/currency';

/**
 * Header cart icon + floating item-count badge, now opening a real slide-over drawer (ad hoc task
 * 24) rather than navigating straight to `/book`. Lives inside `PublicLayout`, which mounts once and
 * persists across Inertia navigations, so it can't just read the cart on mount and forget it — every
 * add-to-cart action anywhere on the site dispatches `CART_CHANGED_EVENT` after writing to
 * `sessionStorage` specifically so this component reacts to it live.
 */
export function HeaderCartButton() {
  const [items, setItems] = React.useState<CartItem[]>([]);
  const [open, setOpen] = React.useState(false);

  React.useEffect(() => {
    function refresh() {
      setItems(getCartItems());
    }
    refresh();
    window.addEventListener(CART_CHANGED_EVENT, refresh);
    return () => window.removeEventListener(CART_CHANGED_EVENT, refresh);
  }, []);

  const subtotal = items.reduce((sum, item) => sum + Number(item.price), 0);
  const totalSavings = items.reduce(
    (sum, item) => sum + (item.original_price ? Number(item.original_price) - Number(item.price) : 0),
    0,
  );

  /**
   * Groups every item the SAME deal added back into one visual row — matching what the shopper
   * actually clicked ("Engagement Ready Package", not 3 separate services) — while `items` itself
   * (what `/book` reads via `getCartServiceIds()`) keeps each real service id untouched, since the
   * booking wizard needs those individually for duration/slot scheduling. A service added on its
   * own (no `deal_id`) stays its own row exactly as before.
   */
  const rows = React.useMemo(() => {
    interface Group {
      key: string;
      name: string;
      price: number;
      originalPriceSum: number;
      hasDiscount: boolean;
      image_url: string | null;
      ids: number[];
    }

    const groups = new Map<string, Group>();

    for (const item of items) {
      const key = item.deal_id ? `deal-${item.deal_id}` : `item-${item.id}`;
      // Every item contributes its own original price when discounted, else its own actual price —
      // so the group's original-price sum only differs from its price sum by real savings, never a
      // fabricated one for an undiscounted item sharing the group.
      const itemOriginal = item.original_price !== undefined ? Number(item.original_price) : Number(item.price);
      const existing = groups.get(key);

      if (existing) {
        existing.price += Number(item.price);
        existing.originalPriceSum += itemOriginal;
        existing.hasDiscount = existing.hasDiscount || item.original_price !== undefined;
        existing.ids.push(item.id);
        continue;
      }

      groups.set(key, {
        key,
        name: item.deal_label ?? item.name,
        price: Number(item.price),
        originalPriceSum: itemOriginal,
        hasDiscount: item.original_price !== undefined,
        image_url: item.image_url,
        ids: [item.id],
      });
    }

    return Array.from(groups.values()).map((group) => ({
      key: group.key,
      name: group.name,
      price: group.price,
      original_price: group.hasDiscount ? group.originalPriceSum : undefined,
      image_url: group.image_url,
      ids: group.ids,
    }));
  }, [items]);

  function removeRow(ids: number[]) {
    ids.forEach((id) => removeFromCart(id));
  }

  return (
    <Sheet open={open} onOpenChange={setOpen}>
      <SheetTrigger asChild>
        <button
          type="button"
          aria-label={`View cart, ${rows.length} ${rows.length === 1 ? 'item' : 'items'} selected`}
          className="text-ink hover:text-accent-600 relative rounded-full p-2"
        >
          <ShoppingBag className="size-5" aria-hidden="true" />
          {rows.length > 0 && (
            <span
              aria-hidden="true"
              className="bg-accent-500 text-ivory absolute -top-0.5 -right-0.5 flex size-4.5 items-center justify-center rounded-full text-[10px] font-semibold"
            >
              {rows.length > 9 ? '9+' : rows.length}
            </span>
          )}
        </button>
      </SheetTrigger>
      <SheetContent side="right" className="w-full sm:max-w-md lg:max-w-[38vw]">
        <SheetHeader>
          <SheetTitle>Your Cart</SheetTitle>
        </SheetHeader>

        {rows.length === 0 ? (
          <div className="mt-6 flex flex-1 flex-col">
            <p className="text-ink-muted text-sm">
              Nothing added yet — browse Services or Deals and add something to get started.
            </p>
            <div className="mt-4 grid grid-cols-2 gap-2">
              <Button asChild variant="outline" onClick={() => setOpen(false)}>
                <Link href="/services">Explore Services</Link>
              </Button>
              <Button asChild variant="outline" onClick={() => setOpen(false)}>
                <Link href="/deals">View Deals</Link>
              </Button>
            </div>
          </div>
        ) : (
          <div className="flex flex-1 flex-col overflow-hidden">
            <div className="flex-1 space-y-3 overflow-y-auto pr-1">
              {rows.map((row) => {
                const isDiscounted = row.original_price !== undefined && row.original_price > row.price;
                return (
                  <div
                    key={row.key}
                    className="border-border-soft flex items-center gap-3 rounded-lg border p-3"
                  >
                    <div className="bg-accent-50 flex size-14 shrink-0 items-center justify-center overflow-hidden rounded-md">
                      {row.image_url ? (
                        <img src={row.image_url} alt="" className="size-full object-cover" />
                      ) : (
                        <Sparkles className="text-accent-600 size-5" aria-hidden="true" />
                      )}
                    </div>
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{row.name}</p>
                      <p className="text-sm">
                        {isDiscounted && (
                          <span className="text-ink-muted mr-2 line-through">
                            {formatCurrency(String(row.original_price))}
                          </span>
                        )}
                        <span className={isDiscounted ? 'text-accent-700 font-medium' : 'text-ink-muted'}>
                          {formatCurrency(String(row.price))}
                        </span>
                      </p>
                    </div>
                    <button
                      type="button"
                      aria-label={`Remove ${row.name} from cart`}
                      onClick={() => removeRow(row.ids)}
                      className="text-ink-muted hover:text-red-600 shrink-0 rounded-md p-1.5"
                    >
                      <Trash2 className="size-4" aria-hidden="true" />
                    </button>
                  </div>
                );
              })}
            </div>

            <div className="border-border-soft mt-4 border-t pt-4">
              <p className="text-ink flex justify-between font-semibold">
                <span>Subtotal</span>
                <span>{formatCurrency(String(subtotal))}</span>
              </p>
              {totalSavings > 0 && (
                <p className="text-accent-700 mt-1 flex justify-between text-sm font-medium">
                  <span>You Save</span>
                  <span>{formatCurrency(String(totalSavings))}</span>
                </p>
              )}
              <Button asChild variant="accent" className="mt-4 w-full" onClick={() => setOpen(false)}>
                <Link href={cartBookingUrl()}>Continue to Booking</Link>
              </Button>
              <Link
                href="/services"
                onClick={() => setOpen(false)}
                className="text-ink-muted hover:text-accent-700 mt-3 block text-center text-sm"
              >
                Browse More Services
              </Link>
            </div>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}
