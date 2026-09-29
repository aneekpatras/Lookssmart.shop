/**
 * Shared cart — the booking wizard's own service selection, persisted in `sessionStorage` so it
 * survives navigating between Home/Deals/Services and `/book` (ad hoc task 22), upgraded to carry
 * real display data per item (name/price/thumbnail) rather than bare ids (ad hoc task 24) so the
 * redesigned "Your Selection" sidebar and the header cart drawer can render a real line-item list
 * without an extra round trip. `window.dispatchEvent` on every write is what lets the header badge/
 * drawer — sibling components that never re-render on their own — react instantly to an add that
 * happened in a completely different part of the page.
 */
const CART_STORAGE_KEY = 'ls_booking_cart_items';
export const CART_CHANGED_EVENT = 'ls-cart-changed';

export interface CartItem {
  id: number;
  name: string;
  /** The actual (possibly deal-discounted) price this item contributes to the cart total. */
  price: string;
  /** Only set when this item came from a deal bundle and its price above is genuinely discounted
   * from this reference value — lets the drawer show a real strikethrough + "You Save" figure
   * without fabricating one for a plain, undiscounted service add (ad hoc task 25). */
  original_price?: string;
  image_url: string | null;
  /** Set only when this item came from a deal bundle — the deal's own id/title, so the cart drawer
   * can group every service the SAME deal added back into one visual "Deal" row (matching how the
   * item was actually added) instead of showing N separate service lines. The underlying storage
   * still keeps each real service id (booking needs those for duration/slot scheduling — grouping
   * only ever changes how the drawer DISPLAYS them, never what `/book` actually receives). */
  deal_id?: number;
  deal_label?: string;
}

export function getCartItems(): CartItem[] {
  if (typeof window === 'undefined') return [];
  try {
    const parsed = JSON.parse(window.sessionStorage.getItem(CART_STORAGE_KEY) ?? '[]') as unknown;
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(
      (item): item is CartItem =>
        item && typeof item === 'object' && Number.isInteger(item.id) && typeof item.name === 'string',
    );
  } catch {
    return [];
  }
}

export function getCartServiceIds(): number[] {
  return getCartItems().map((item) => item.id);
}

/** Replaces the whole cart (used by `Book.tsx`, which owns additions AND removals) and notifies
 * listeners — the header badge/drawer — that it changed. */
export function setCartItems(items: CartItem[]): void {
  const deduped = Array.from(new Map(items.map((item) => [item.id, item])).values());
  window.sessionStorage.setItem(CART_STORAGE_KEY, JSON.stringify(deduped));
  window.dispatchEvent(new Event(CART_CHANGED_EVENT));
}

/** Merges the given items into the cart (never removes an existing one) and notifies listeners.
 * Synchronous end to end (`getCartItems()` reads `sessionStorage` directly, no `await` in between
 * the read and the `setCartItems()` write) — a single JS task, so two "Add to Cart" clicks (even a
 * double-click firing both handlers back to back) can never interleave into a lost-update race; the
 * second call always reads the first call's already-written result. */
export function addItemsToCart(items: CartItem[]): void {
  if (items.length === 0) return;
  setCartItems([...getCartItems(), ...items]);
}

/**
 * Single, deterministic entry point every "Add to Cart" control on the site now shares (Services,
 * Home's service grid, Home's/Deals' deal cards) — ad hoc task 29 refactor. Before this, 4 call
 * sites each independently re-implemented "build cart item(s), call `addItemsToCart`, show a toast",
 * which worked correctly (verified live: no ID collisions, no duplicate/missing items under rapid
 * double-click and interleaved multi-add stress) but left 4 places that could silently drift out of
 * sync with each other over time. `kind: 'service'` always writes the service's OWN real id;
 * `kind: 'deal'` always writes its REAL bundled services' ids via `dealBundleToCartItems` — a deal's
 * own id is never a valid `CartItem.id` and is never referenced here, structurally closing off the
 * deal-id/service-id collision this was written to guard against.
 */
export type AddToCartInput =
  | { kind: 'service'; label: string; service: { id: number; name: string; price: string; image_url: string | null } }
  | {
      kind: 'deal';
      dealId: number;
      label: string;
      bundledServices: { id: number; name: string; price: string; image_url: string | null }[];
      originalPrice: string | null;
      dealPrice: string | null;
    };

export function addToCart(
  input: AddToCartInput,
  toast: { success: (message: string) => void; error: (message: string) => void },
): void {
  if (input.kind === 'service') {
    addItemsToCart([
      {
        id: input.service.id,
        name: input.service.name,
        price: input.service.price,
        image_url: input.service.image_url,
      },
    ]);
    toast.success(`"${input.service.name}" added to cart`);
    return;
  }

  if (input.bundledServices.length === 0) {
    toast.error('This deal has no bookable services attached yet.');
    return;
  }
  addItemsToCart(dealBundleToCartItems(input.bundledServices, input.originalPrice, input.dealPrice, input.dealId, input.label));
  toast.success(`"${input.label}" added to cart`);
}

export function removeFromCart(id: number): void {
  setCartItems(getCartItems().filter((item) => item.id !== id));
}

export function clearCart(): void {
  setCartItems([]);
}

/** `/book?service=1&service=2` from whatever is currently in the cart, or plain `/book` if empty. */
export function cartBookingUrl(): string {
  const ids = getCartServiceIds();
  return ids.length ? `/book?${ids.map((id) => `service=${id}`).join('&')}` : '/book';
}

/**
 * Turns a deal's real bundled services into cart items carrying an honest per-item discount —
 * each service's own catalog price becomes `original_price`, scaled down by the deal's real
 * `deal_price / original_price` ratio to produce `price`, so the sum of every item's `price` still
 * equals the deal's real advertised total (not just each item individually "looking" discounted).
 * Falls back to the plain, undiscounted service price when the deal has no real price breakdown to
 * scale from (`original_price`/`deal_price` are both optional on `Deal`).
 */
export function dealBundleToCartItems(
  bundledServices: { id: number; name: string; price: string; image_url: string | null }[],
  originalPrice: string | null,
  dealPrice: string | null,
  dealId: number,
  dealLabel: string,
): CartItem[] {
  const original = originalPrice ? Number(originalPrice) : null;
  const discounted = dealPrice ? Number(dealPrice) : null;
  const scale = original && original > 0 && discounted !== null ? discounted / original : null;

  return bundledServices.map((service) => ({
    id: service.id,
    name: service.name,
    price: scale !== null ? (Number(service.price) * scale).toFixed(2) : service.price,
    original_price: scale !== null ? service.price : undefined,
    image_url: service.image_url,
    deal_id: dealId,
    deal_label: dealLabel,
  }));
}
