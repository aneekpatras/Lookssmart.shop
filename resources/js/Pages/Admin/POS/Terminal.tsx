import { Head, Link } from '@inertiajs/react';
import axios, { isAxiosError } from 'axios';
import { Clock, Minus, Pause, Percent, Plus, Search, Trash2, User, UserPlus, X } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/Components/ui/popover';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';
import { ReceiptDialog } from './Receipt';

interface ServiceOption {
  id: number;
  name: string;
  sku: string | null;
  base_price: string;
}

interface CategoryOption {
  id: number;
  name: string;
  services: ServiceOption[];
}

interface DealOption {
  id: number;
  title: string;
  code: string | null;
  original_price: string | null;
  deal_price: string | null;
  services: ServiceOption[];
}

interface StaffOption {
  id: number;
  name: string | null;
}

interface CustomerOption {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  loyalty_points: number;
}

interface PrefillBooking {
  id: number;
  code: string;
  customer: CustomerOption | null;
  items: { service_id: number; name: string; sku: string | null; base_price: string }[];
}

interface ResumeSale {
  id: number;
  sale_number: string;
  notes: string | null;
  discount_percent: string | null;
  tax_rate_percent: string | null;
  customer: CustomerOption | null;
  booking_id: number | null;
  items: {
    service_id: number | null;
    deal_id: number | null;
    name: string;
    unit_price: string;
    quantity: number;
    discount: string;
  }[];
}

/** A cart line is EITHER a plain service OR a whole Deal package (Phase 12 sub-step 8) — never
 * both. A Deal line's price is already final (the package price), so it carries no editable
 * per-line discount input; `discountPercent` only ever applies to a plain service line. */
interface CartLine {
  key: string;
  service_id: number | null;
  deal_id: number | null;
  name: string;
  unit_price: number;
  quantity: number;
  discountPercent: string;
}

interface QuoteItem {
  service_id: number | null;
  deal_id: number | null;
  description: string;
  quantity: number;
  unit_price: string;
  discount: string;
  total: string;
  staff_id: number | null;
}

interface Quote {
  items: QuoteItem[];
  deal_id: number | null;
  code: string | null;
  subtotal: string;
  item_discount: string;
  coupon_discount: string;
  manual_discount: string;
  manual_discount_percent: string;
  discount: string;
  tax_rate: string;
  tax: string;
  total: string;
}

interface TerminalPageProps {
  categories: CategoryOption[];
  staff: StaffOption[];
  taxRate: string;
  prefillBooking: PrefillBooking | null;
  resumeSale: ResumeSale | null;
}

let lineKeySeq = 0;
function nextLineKey(): string {
  lineKeySeq += 1;
  return `line-${lineKeySeq}`;
}

function cartToItemsPayload(cart: CartLine[]) {
  return cart.map((line) => {
    if (line.deal_id) {
      return { deal_id: line.deal_id, quantity: line.quantity };
    }

    const lineGross = line.unit_price * line.quantity;
    const discountAmount = Math.round(lineGross * (Number(line.discountPercent || 0) / 100) * 100) / 100;

    return { service_id: line.service_id, quantity: line.quantity, discount: discountAmount };
  });
}

interface PendingPayment {
  method: string;
  amount: number;
  tendered_amount: number | null;
  reference: string | null;
}

export default function Terminal({ categories, taxRate, prefillBooking, resumeSale }: TerminalPageProps) {
  const [activeCategoryId, setActiveCategoryId] = React.useState<number | null>(null);
  const [showDeals, setShowDeals] = React.useState(false);
  const [search, setSearch] = React.useState('');
  const [searchResults, setSearchResults] = React.useState<ServiceOption[] | null>(null);
  const [dealResults, setDealResults] = React.useState<DealOption[]>([]);

  const [cart, setCart] = React.useState<CartLine[]>(() =>
    resumeSale
      ? resumeSale.items.map((item) => {
          const lineGross = Number(item.unit_price) * item.quantity;
          // The dollar `discount` a resumed sale stored is converted back to the % the input now
          // shows — a Deal line never had one to begin with (already-final package price).
          const discountPercent = item.deal_id || lineGross <= 0 ? '0' : String(Math.round((Number(item.discount) / lineGross) * 10000) / 100);

          return {
            key: nextLineKey(),
            service_id: item.service_id,
            deal_id: item.deal_id,
            name: item.name,
            unit_price: Number(item.unit_price),
            quantity: item.quantity,
            discountPercent,
          };
        })
      : (prefillBooking?.items ?? []).map((item) => ({
          key: nextLineKey(),
          service_id: item.service_id,
          deal_id: null,
          name: item.name,
          unit_price: Number(item.base_price),
          quantity: 1,
          discountPercent: '0',
        })),
  );

  const [customer, setCustomer] = React.useState<CustomerOption | null>(
    resumeSale?.customer ?? prefillBooking?.customer ?? null,
  );
  const [customerQuery, setCustomerQuery] = React.useState('');
  const [customerResults, setCustomerResults] = React.useState<CustomerOption[]>([]);
  const [customerPopoverOpen, setCustomerPopoverOpen] = React.useState(false);

  const [newCustomerOpen, setNewCustomerOpen] = React.useState(false);
  const [newCustomerName, setNewCustomerName] = React.useState('');
  const [newCustomerEmail, setNewCustomerEmail] = React.useState('');
  const [newCustomerPhone, setNewCustomerPhone] = React.useState('');
  const [creatingCustomer, setCreatingCustomer] = React.useState(false);

  const [bookingId, setBookingId] = React.useState<number | null>(resumeSale?.booking_id ?? prefillBooking?.id ?? null);
  const [bookingLabel] = React.useState<string | null>(prefillBooking ? `#${prefillBooking.code}` : null);
  const [resumeSaleId, setResumeSaleId] = React.useState<number | null>(resumeSale?.id ?? null);

  const [couponCode, setCouponCode] = React.useState('');
  const [discountPercent, setDiscountPercent] = React.useState(resumeSale?.discount_percent ?? '0');
  const [taxPercent, setTaxPercent] = React.useState(resumeSale?.tax_rate_percent ?? taxRate ?? '0');
  const [quote, setQuote] = React.useState<Quote | null>(null);
  const [pricingError, setPricingError] = React.useState<string | null>(null);
  const [pricing, setPricing] = React.useState(false);
  const [holding, setHolding] = React.useState(false);
  const [completing, setCompleting] = React.useState(false);

  const [payments, setPayments] = React.useState<PendingPayment[]>([]);
  const [method, setMethod] = React.useState<'cash' | 'card' | 'loyalty_points'>('cash');
  const [amount, setAmount] = React.useState('');
  const [reference, setReference] = React.useState('');
  const [tendered, setTendered] = React.useState('');

  const [completedSaleId, setCompletedSaleId] = React.useState<number | null>(null);

  React.useEffect(() => {
    const timeout = setTimeout(() => {
      if (showDeals || !search) {
        setSearchResults(null);
        return;
      }

      axios
        .get<{ services: ServiceOption[] }>('/admin/pos/search/services', { params: { q: search } })
        .then((response) => setSearchResults(response.data.services));
    }, 300);

    return () => clearTimeout(timeout);
  }, [showDeals, search]);

  React.useEffect(() => {
    if (!showDeals) {
      return;
    }

    const timeout = setTimeout(() => {
      axios
        .get<{ deals: DealOption[] }>('/admin/pos/search/deals', { params: { q: search } })
        .then((response) => setDealResults(response.data.deals));
    }, 300);

    return () => clearTimeout(timeout);
  }, [showDeals, search]);

  React.useEffect(() => {
    const timeout = setTimeout(() => {
      if (!customerQuery) {
        setCustomerResults([]);
        return;
      }

      axios
        .get<{ customers: CustomerOption[] }>('/admin/pos/search/customers', { params: { q: customerQuery } })
        .then((response) => setCustomerResults(response.data.customers));
    }, 300);

    return () => clearTimeout(timeout);
  }, [customerQuery]);

  React.useEffect(() => {
    const timeout = setTimeout(() => {
      if (cart.length === 0) {
        setQuote(null);
        setPricingError(null);
        setPayments([]);
        setAmount('');
        setTendered('');
        return;
      }

      setPricing(true);
      axios
        .post<Quote>('/admin/pos/apply-coupon', {
          items: cartToItemsPayload(cart),
          coupon_code: couponCode || null,
          customer_id: customer?.id ?? null,
          discount_percent: Number(discountPercent || 0),
          tax_rate_percent: Number(taxPercent || 0),
        })
        .then((response) => {
          setQuote(response.data);
          setPricingError(null);
          // A re-priced cart invalidates any partial-payment progress against the old total — reset
          // the payment builder here (where the new total is already in hand) rather than in a
          // separate effect reacting to `quote.total`.
          setPayments([]);
          setAmount(response.data.total);
          setTendered('');
        })
        .catch((error) => {
          setQuote(null);
          setPricingError(
            isAxiosError(error) ? (error.response?.data?.message ?? 'Could not price this cart.') : 'Could not price this cart.',
          );
        })
        .finally(() => setPricing(false));
    }, 400);

    return () => clearTimeout(timeout);
  }, [cart, couponCode, customer, discountPercent, taxPercent]);

  function addToCart(service: ServiceOption) {
    setCart((prev) => {
      const existing = prev.find((line) => line.service_id === service.id);
      if (existing) {
        return prev.map((line) => (line.key === existing.key ? { ...line, quantity: line.quantity + 1 } : line));
      }
      return [
        ...prev,
        {
          key: nextLineKey(),
          service_id: service.id,
          deal_id: null,
          name: service.name,
          unit_price: Number(service.base_price),
          quantity: 1,
          discountPercent: '0',
        },
      ];
    });
  }

  /**
   * A Deal is its own cart-line type (Phase 12 sub-step 8 — `sale_items.deal_id`), priced server-side
   * as ONE line at the deal's package price, not exploded into its bundled services. No coupon code
   * is set here anymore — the line already carries its own discount, and typing the deal's own code
   * on top of it would double-apply the same discount.
   */
  function addDeal(deal: DealOption) {
    setCart((prev) => {
      const existing = prev.find((line) => line.deal_id === deal.id);
      if (existing) {
        return prev.map((line) => (line.key === existing.key ? { ...line, quantity: line.quantity + 1 } : line));
      }
      return [
        ...prev,
        {
          key: nextLineKey(),
          service_id: null,
          deal_id: deal.id,
          name: deal.title,
          unit_price: Number(deal.original_price ?? deal.deal_price ?? 0),
          quantity: 1,
          discountPercent: '0',
        },
      ];
    });
    toast.success(`Added "${deal.title}" to cart.`);
  }

  function adjustQuantity(key: string, delta: number) {
    setCart((prev) =>
      prev
        .map((line) => (line.key === key ? { ...line, quantity: line.quantity + delta } : line))
        .filter((line) => line.quantity > 0),
    );
  }

  function updateDiscount(key: string, value: string) {
    setCart((prev) => prev.map((line) => (line.key === key ? { ...line, discountPercent: value } : line)));
  }

  function removeLine(key: string) {
    setCart((prev) => prev.filter((line) => line.key !== key));
  }

  function startNewSale() {
    setCart([]);
    setCustomer(null);
    setBookingId(null);
    setResumeSaleId(null);
    setCouponCode('');
    setDiscountPercent('0');
    setTaxPercent(taxRate ?? '0');
    setQuote(null);
    setPayments([]);
    setCompletedSaleId(null);
  }

  async function holdSale() {
    if (cart.length === 0) {
      toast.error('Add at least one item before holding this sale.');
      return;
    }

    setHolding(true);
    try {
      await axios.post('/admin/pos/hold', {
        items: cartToItemsPayload(cart),
        customer_id: customer?.id ?? null,
        booking_id: bookingId,
        resume_sale_id: resumeSaleId,
        discount_percent: Number(discountPercent || 0),
        tax_rate_percent: Number(taxPercent || 0),
      });
      toast.success('Sale held.');
      startNewSale();
    } catch (error) {
      const message = isAxiosError(error)
        ? (error.response?.data?.message ?? 'Could not hold this sale.')
        : 'Could not hold this sale.';
      toast.error(message);
    } finally {
      setHolding(false);
    }
  }

  async function createCustomer() {
    if (!newCustomerName) {
      toast.error('Name is required to add a new customer.');
      return;
    }

    setCreatingCustomer(true);
    try {
      const response = await axios.post<{ customer: CustomerOption }>('/admin/pos/customers', {
        name: newCustomerName,
        email: newCustomerEmail || null,
        phone: newCustomerPhone || null,
      });
      setCustomer(response.data.customer);
      setNewCustomerOpen(false);
      setNewCustomerName('');
      setNewCustomerEmail('');
      setNewCustomerPhone('');
      toast.success('Customer added.');
    } catch (error) {
      const message = isAxiosError(error)
        ? (error.response?.data?.message ?? 'Could not create this customer.')
        : 'Could not create this customer.';
      toast.error(message);
    } finally {
      setCreatingCustomer(false);
    }
  }

  const total = Number(quote?.total ?? 0);
  const paid = payments.reduce((sum, p) => sum + p.amount, 0);
  const remaining = Math.max(0, Math.round((total - paid) * 100) / 100);
  const change = method === 'cash' && tendered ? Math.max(0, Number(tendered) - Number(amount || 0)) : 0;
  const maxLoyaltyRupees = customer ? customer.loyalty_points / 100 : 0;

  function addPayment() {
    const value = Number(amount || 0);
    if (value <= 0) {
      toast.error('Enter a payment amount greater than zero.');
      return;
    }
    if (method === 'loyalty_points' && (!customer || value > maxLoyaltyRupees)) {
      toast.error('This customer does not have enough loyalty points for that amount.');
      return;
    }
    setPayments((prev) => [
      ...prev,
      {
        method,
        amount: Math.round(value * 100) / 100,
        tendered_amount: method === 'cash' && tendered ? Math.round(Number(tendered) * 100) / 100 : null,
        reference: reference || null,
      },
    ]);
    setReference('');
    setTendered('');
    const nextRemaining = Math.max(0, Math.round((remaining - value) * 100) / 100);
    setAmount(nextRemaining.toFixed(2));
  }

  function removePayment(index: number) {
    setPayments((prev) => prev.filter((_, i) => i !== index));
  }

  async function printAndComplete() {
    if (!quote || cart.length === 0) {
      return;
    }

    // The common single-method case: nothing added to the pending-payments list yet, and the typed
    // amount already covers the full total — treat it as one implicit payment rather than forcing an
    // extra "Add payment" click for every sale.
    let finalPayments = payments;
    if (finalPayments.length === 0 && remaining > 0.001) {
      const value = Number(amount || 0);
      if (Math.abs(value - remaining) > 0.01) {
        toast.error(`Enter the full amount (${formatCurrency(remaining)}) or use Add payment for a split.`);
        return;
      }
      if (method === 'loyalty_points' && (!customer || value > maxLoyaltyRupees)) {
        toast.error('This customer does not have enough loyalty points for that amount.');
        return;
      }
      finalPayments = [
        {
          method,
          amount: Math.round(value * 100) / 100,
          tendered_amount: method === 'cash' && tendered ? Math.round(Number(tendered) * 100) / 100 : null,
          reference: reference || null,
        },
      ];
    } else if (remaining > 0.001) {
      toast.error('Payments must add up to the full total before completing the sale.');
      return;
    }

    setCompleting(true);
    try {
      const response = await axios.post<{ sale: { id: number } }>('/admin/pos/checkout', {
        items: cartToItemsPayload(cart),
        customer_id: customer?.id ?? null,
        booking_id: bookingId,
        resume_sale_id: resumeSaleId,
        coupon_code: couponCode || null,
        discount_percent: Number(discountPercent || 0),
        tax_rate_percent: Number(taxPercent || 0),
        payments: finalPayments,
      });
      toast.success('Sale completed.');
      setCompletedSaleId(response.data.sale.id);
    } catch (error) {
      const message = isAxiosError(error)
        ? (error.response?.data?.message ?? 'Could not complete the sale.')
        : 'Could not complete the sale.';
      toast.error(message);
    } finally {
      setCompleting(false);
    }
  }

  const visibleServices = searchResults ?? (activeCategoryId ? categories.find((c) => c.id === activeCategoryId)?.services ?? [] : categories.flatMap((c) => c.services));

  const localSubtotal = cart.reduce((sum, line) => sum + line.unit_price * line.quantity, 0);

  return (
    <>
      <Head title="POS Terminal" />
      <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_380px]">
        <div className="space-y-4">
          <div className="flex items-start justify-between gap-4">
            <div>
              <h1 className="font-display text-ink text-2xl font-medium">Checkout</h1>
              <p className="text-ink-muted text-sm">
                {resumeSale ? `Resuming held sale ${resumeSale.sale_number}.` : 'Select services to build the sale.'}
              </p>
            </div>
            <Button type="button" variant="outline" size="sm" asChild>
              <Link href="/admin/pos/held-sales">
                <Clock className="size-4" />
                Held sales
              </Link>
            </Button>
          </div>

          <div className="relative">
            <Search className="text-ink-muted absolute top-3 left-3 size-4" />
            <Input
              className="pl-9"
              placeholder={showDeals ? 'Search deals by title or code…' : 'Search services by name or SKU…'}
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>

          {!searchResults && (
            <div className="flex flex-wrap gap-2">
              <Button
                type="button"
                size="sm"
                variant={!showDeals && activeCategoryId === null ? 'default' : 'outline'}
                onClick={() => {
                  setShowDeals(false);
                  setActiveCategoryId(null);
                }}
              >
                All
              </Button>
              {categories.map((category) => (
                <Button
                  key={category.id}
                  type="button"
                  size="sm"
                  variant={!showDeals && activeCategoryId === category.id ? 'default' : 'outline'}
                  onClick={() => {
                    setShowDeals(false);
                    setActiveCategoryId(category.id);
                  }}
                >
                  {category.name}
                </Button>
              ))}
              <Button
                type="button"
                size="sm"
                variant={showDeals ? 'default' : 'outline'}
                onClick={() => setShowDeals(true)}
              >
                <Percent className="size-4" />
                Deals
              </Button>
            </div>
          )}

          {showDeals ? (
            dealResults.length === 0 ? (
              <EmptyState title="No active deals found" description="Try a different search, or check the Deals admin page." />
            ) : (
              <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                {dealResults.map((deal) => (
                  <button
                    key={deal.id}
                    type="button"
                    onClick={() => addDeal(deal)}
                    className="border-border-soft hover:border-accent-500 hover:bg-accent-50/60 rounded-lg border p-3 text-left transition-colors"
                  >
                    <p className="text-ink text-sm font-medium">{deal.title}</p>
                    {deal.original_price && deal.deal_price ? (
                      <p className="text-xs">
                        <span className="text-ink-muted mr-1 line-through">{formatCurrency(Number(deal.original_price))}</span>
                        <span className="text-accent-600 font-medium">{formatCurrency(Number(deal.deal_price))}</span>
                      </p>
                    ) : (
                      <p className="text-ink-muted text-xs">{deal.services.length} service{deal.services.length === 1 ? '' : 's'}</p>
                    )}
                  </button>
                ))}
              </div>
            )
          ) : visibleServices.length === 0 ? (
            <EmptyState title="No services found" description="Try a different search or category." />
          ) : (
            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
              {visibleServices.map((service) => (
                <button
                  key={service.id}
                  type="button"
                  onClick={() => addToCart(service)}
                  className="border-border-soft hover:border-accent-500 hover:bg-accent-50/60 rounded-lg border p-3 text-left transition-colors"
                >
                  <p className="text-ink text-sm font-medium">{service.name}</p>
                  <p className="text-ink-muted text-xs">{formatCurrency(Number(service.base_price))}</p>
                </button>
              ))}
            </div>
          )}
        </div>

        <div className="space-y-4">
          <Card>
            <CardContent className="space-y-4 p-4">
              <div className="flex items-center justify-between">
                <h2 className="font-display text-ink text-base font-medium">Cart</h2>
                {cart.length > 0 && (
                  <Button type="button" size="sm" variant="ghost" onClick={() => setCart([])}>
                    Clear
                  </Button>
                )}
              </div>

              {bookingLabel && (
                <div className="border-accent-200 bg-accent-50/60 flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                  <span>Closing booking {bookingLabel}</span>
                  <button type="button" onClick={() => setBookingId(null)} aria-label="Unlink booking">
                    <X className="size-4" />
                  </button>
                </div>
              )}

              {cart.length === 0 ? (
                <p className="text-ink-muted text-sm">Cart is empty.</p>
              ) : (
                <div className="space-y-3">
                  {cart.map((line) => (
                    <div key={line.key} className="border-border-soft space-y-2 rounded-md border p-3">
                      <div className="flex items-start justify-between gap-2">
                        <p className="text-sm font-medium">
                          {line.name}
                          {line.deal_id && <span className="bg-accent-50 text-accent-700 ml-2 rounded px-1.5 py-0.5 text-xs">Deal</span>}
                        </p>
                        <button type="button" onClick={() => removeLine(line.key)} aria-label="Remove item">
                          <Trash2 className="text-ink-muted size-4" />
                        </button>
                      </div>
                      <div className="flex items-center gap-2">
                        <Button type="button" size="sm" variant="outline" className="size-8 min-w-0 px-0" onClick={() => adjustQuantity(line.key, -1)}>
                          <Minus className="size-3" />
                        </Button>
                        <span className="w-6 text-center text-sm">{line.quantity}</span>
                        <Button type="button" size="sm" variant="outline" className="size-8 min-w-0 px-0" onClick={() => adjustQuantity(line.key, 1)}>
                          <Plus className="size-3" />
                        </Button>
                        <span className="text-ink-muted ml-auto text-sm">{formatCurrency(line.unit_price * line.quantity)}</span>
                      </div>
                      {!line.deal_id && (
                        <Input
                          type="number"
                          min="0"
                          max="100"
                          step="0.01"
                          className="h-9 text-xs"
                          placeholder="Line discount %"
                          value={line.discountPercent}
                          onChange={(e) => updateDiscount(line.key, e.target.value)}
                        />
                      )}
                    </div>
                  ))}
                </div>
              )}

              <div className="space-y-1.5">
                <div className="flex items-center justify-between">
                  <Label htmlFor="customer-search">Customer</Label>
                  {!customer && (
                    <button
                      type="button"
                      className="text-accent-600 flex items-center gap-1 text-xs font-medium hover:underline"
                      onClick={() => setNewCustomerOpen((prev) => !prev)}
                    >
                      <UserPlus className="size-3" />
                      New Customer
                    </button>
                  )}
                </div>
                {customer ? (
                  <div className="border-border-soft flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                    <span>
                      {customer.name} <span className="text-ink-muted">({customer.loyalty_points} pts)</span>
                    </span>
                    <button type="button" onClick={() => setCustomer(null)} aria-label="Remove customer">
                      <X className="size-4" />
                    </button>
                  </div>
                ) : newCustomerOpen ? (
                  <div className="border-border-soft space-y-2 rounded-md border p-3">
                    <Input placeholder="Name" value={newCustomerName} onChange={(e) => setNewCustomerName(e.target.value)} />
                    <Input placeholder="Email (optional)" type="text" value={newCustomerEmail} onChange={(e) => setNewCustomerEmail(e.target.value)} />
                    <Input placeholder="Phone (optional)" value={newCustomerPhone} onChange={(e) => setNewCustomerPhone(e.target.value)} />
                    <div className="flex gap-2">
                      <Button type="button" size="sm" className="flex-1" disabled={creatingCustomer} onClick={createCustomer}>
                        Create
                      </Button>
                      <Button type="button" size="sm" variant="outline" onClick={() => setNewCustomerOpen(false)}>
                        Cancel
                      </Button>
                    </div>
                  </div>
                ) : (
                  <Popover open={customerPopoverOpen} onOpenChange={setCustomerPopoverOpen}>
                    <PopoverTrigger asChild>
                      <div className="relative">
                        <User className="text-ink-muted absolute top-3 left-3 size-4" />
                        <Input
                          id="customer-search"
                          className="pl-9"
                          placeholder="Search customers… (default: Walk-in Customer)"
                          value={customerQuery}
                          onChange={(e) => {
                            setCustomerQuery(e.target.value);
                            setCustomerPopoverOpen(true);
                          }}
                        />
                      </div>
                    </PopoverTrigger>
                    <PopoverContent className="w-80 p-1" align="start">
                      {customerResults.length === 0 ? (
                        <p className="text-ink-muted p-2 text-sm">No matches.</p>
                      ) : (
                        customerResults.map((result) => (
                          <button
                            key={result.id}
                            type="button"
                            className="hover:bg-accent-50 block w-full rounded-sm p-2 text-left text-sm"
                            onClick={() => {
                              setCustomer(result);
                              setCustomerQuery('');
                              setCustomerPopoverOpen(false);
                            }}
                          >
                            <span className="block font-medium">{result.name}</span>
                            <span className="text-ink-muted block text-xs">{result.email}</span>
                          </button>
                        ))
                      )}
                    </PopoverContent>
                  </Popover>
                )}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="coupon">Coupon code (optional)</Label>
                <Input
                  id="coupon"
                  value={couponCode}
                  onChange={(e) => setCouponCode(e.target.value)}
                  placeholder="e.g. SUMMER10"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div className="space-y-1.5">
                  <Label htmlFor="discount-percent">Discount %</Label>
                  <Input
                    id="discount-percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={discountPercent}
                    onChange={(e) => setDiscountPercent(e.target.value)}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="tax-percent">Tax %</Label>
                  <Input
                    id="tax-percent"
                    type="number"
                    min="0"
                    max="100"
                    step="0.01"
                    value={taxPercent}
                    onChange={(e) => setTaxPercent(e.target.value)}
                  />
                </div>
              </div>

              {pricingError && <p className="text-sm text-red-600">{pricingError}</p>}

              <div className="space-y-1 border-t pt-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-ink-muted">Subtotal</span>
                  <span>{formatCurrency(Number(quote?.subtotal ?? localSubtotal))}</span>
                </div>
                {quote && Number(quote.discount) > 0 && (
                  <div className="flex justify-between">
                    <span className="text-ink-muted">Discount{Number(quote.manual_discount_percent) > 0 ? ` (${quote.manual_discount_percent}%)` : ''}</span>
                    <span>-{formatCurrency(Number(quote.discount))}</span>
                  </div>
                )}
                <div className="flex justify-between">
                  <span className="text-ink-muted">Tax{quote && Number(quote.tax_rate) > 0 ? ` (${quote.tax_rate}%)` : ''}</span>
                  <span>{formatCurrency(Number(quote?.tax ?? 0))}</span>
                </div>
                <div className="flex justify-between text-base font-medium">
                  <span>Total</span>
                  <span>{formatCurrency(Number(quote?.total ?? localSubtotal))}</span>
                </div>
              </div>

              {payments.length > 0 && (
                <div className="space-y-1">
                  {payments.map((payment, index) => (
                    <div key={index} className="border-border-soft flex items-center justify-between rounded-md border px-3 py-2 text-sm">
                      <span className="capitalize">
                        {payment.method.replace('_', ' ')}
                        {payment.reference ? ` — ${payment.reference}` : ''}
                      </span>
                      <div className="flex items-center gap-2">
                        <span>{formatCurrency(payment.amount)}</span>
                        <button type="button" onClick={() => removePayment(index)} aria-label="Remove payment">
                          <X className="text-ink-muted size-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                  <div className="flex justify-between text-sm font-medium">
                    <span>Remaining</span>
                    <span className={remaining > 0 ? 'text-red-600' : 'text-emerald-600'}>{formatCurrency(remaining)}</span>
                  </div>
                </div>
              )}

              <div className="space-y-3 border-t pt-3">
                <div className="grid grid-cols-2 gap-3">
                  <div className="space-y-1.5">
                    <Label htmlFor="pay-method">Payment method</Label>
                    <Select value={method} onValueChange={(value) => setMethod(value as typeof method)}>
                      <SelectTrigger id="pay-method">
                        <SelectValue />
                      </SelectTrigger>
                      <SelectContent>
                        <SelectItem value="cash">Cash</SelectItem>
                        <SelectItem value="card">Card</SelectItem>
                        <SelectItem value="loyalty_points">Loyalty Points</SelectItem>
                      </SelectContent>
                    </Select>
                  </div>
                  <div className="space-y-1.5">
                    <Label htmlFor="pay-amount">Amount</Label>
                    <Input id="pay-amount" type="number" min="0.01" step="0.01" value={amount} onChange={(e) => setAmount(e.target.value)} />
                  </div>
                </div>

                {method === 'cash' && (
                  <div className="grid grid-cols-2 gap-3">
                    <div className="space-y-1.5">
                      <Label htmlFor="pay-tendered">Cash tendered</Label>
                      <Input id="pay-tendered" type="number" min="0" step="0.01" value={tendered} onChange={(e) => setTendered(e.target.value)} />
                    </div>
                    <div className="space-y-1.5">
                      <Label>Change due</Label>
                      <p className="flex h-11 items-center text-sm font-medium">{formatCurrency(change)}</p>
                    </div>
                  </div>
                )}

                {method === 'card' && (
                  <div className="space-y-1.5">
                    <Label htmlFor="pay-reference">Card reference / last 4</Label>
                    <Input id="pay-reference" value={reference} onChange={(e) => setReference(e.target.value)} placeholder="e.g. VISA •1234" />
                  </div>
                )}

                {method === 'loyalty_points' && (
                  <p className="text-ink-muted text-sm">
                    {customer ? `${customer.name} has ${customer.loyalty_points} pts (${formatCurrency(maxLoyaltyRupees)} available).` : 'Select a customer to redeem loyalty points.'}
                  </p>
                )}

                <Button type="button" variant="outline" onClick={addPayment} className="w-full">
                  Add payment (split)
                </Button>
              </div>

              <div className="flex gap-2">
                <Button
                  type="button"
                  variant="outline"
                  className="flex-1"
                  disabled={cart.length === 0 || holding}
                  onClick={holdSale}
                >
                  <Pause className="size-4" />
                  Hold
                </Button>
                <Button
                  type="button"
                  className="flex-1"
                  disabled={!quote || pricing || completing || cart.length === 0}
                  onClick={printAndComplete}
                >
                  Print & Complete
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>

      {completedSaleId && <ReceiptDialog saleId={completedSaleId} onClose={startNewSale} />}
    </>
  );
}

Terminal.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
