import { Head, router, useForm } from '@inertiajs/react';
import { MoreHorizontal, Percent, Plus } from 'lucide-react';
import * as React from 'react';

import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card } from '@/Components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/Components/ui/dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu';
import { EmptyState } from '@/Components/ui/empty-state';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface PriceRow {
  id: number;
  price_list: string;
  price: string;
  effective_from: string | null;
  effective_to: string | null;
}

interface ServiceRow {
  id: number;
  name: string;
  category: string | null;
  base_price: string;
  prices: PriceRow[];
}

interface PricingPageProps {
  services: {
    data: ServiceRow[];
    current_page: number;
    last_page: number;
  };
  filters: { search: string | null; category_id: number | null };
  categories: { id: number; name: string }[];
}

const PRICE_LISTS = ['standard', 'weekend', 'seasonal'];

function AddPriceDialog({ service }: { service: ServiceRow }) {
  const [open, setOpen] = React.useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    service_id: service.id,
    price_list: 'weekend',
    price: '',
    effective_from: '',
    effective_to: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/admin/pricing', {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button type="button" variant="outline" size="sm">
          <Plus className="size-4" />
          Add price
        </Button>
      </DialogTrigger>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Add a price override for {service.name}</DialogTitle>
            <DialogDescription>
              Overrides the base price for the given price list and date window.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="price-list">Price list</Label>
            <Select value={data.price_list} onValueChange={(value) => setData('price_list', value)}>
              <SelectTrigger id="price-list">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {PRICE_LISTS.map((list) => (
                  <SelectItem key={list} value={list}>
                    {list}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="price-value">Price</Label>
            <Input
              id="price-value"
              type="number"
              step="0.01"
              min={0}
              value={data.price}
              onChange={(e) => setData('price', e.target.value)}
              required
            />
            {errors.price ? <p className="text-sm text-red-600">{errors.price}</p> : null}
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="price-from">Effective from</Label>
              <Input
                id="price-from"
                type="date"
                value={data.effective_from}
                onChange={(e) => setData('effective_from', e.target.value)}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="price-to">Effective to</Label>
              <Input
                id="price-to"
                type="date"
                value={data.effective_to}
                onChange={(e) => setData('effective_to', e.target.value)}
              />
              {errors.effective_to ? <p className="text-sm text-red-600">{errors.effective_to}</p> : null}
            </div>
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              Add price
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function BulkAdjustDialog({ categories }: { categories: { id: number; name: string }[] }) {
  const [open, setOpen] = React.useState(false);
  const { data, setData, post, processing, errors, reset } = useForm({
    service_category_id: categories[0]?.id ?? '',
    percent: '',
    reason: '',
  });

  function submit(e: React.FormEvent) {
    e.preventDefault();
    post('/admin/pricing/bulk-adjust', {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
    });
  }

  return (
    <Dialog open={open} onOpenChange={setOpen}>
      <DialogTrigger asChild>
        <Button type="button">
          <Percent className="size-4" />
          Bulk adjust
        </Button>
      </DialogTrigger>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>Bulk-adjust base prices</DialogTitle>
            <DialogDescription>
              Changes every service&apos;s base price in a category by a percentage. Positive raises
              prices, negative lowers them. Every change is logged individually.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-1.5">
            <Label htmlFor="bulk-category">Category</Label>
            <Select
              value={String(data.service_category_id)}
              onValueChange={(value) => setData('service_category_id', Number(value))}
            >
              <SelectTrigger id="bulk-category">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {categories.map((category) => (
                  <SelectItem key={category.id} value={String(category.id)}>
                    {category.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="bulk-percent">Percent change</Label>
            <Input
              id="bulk-percent"
              type="number"
              step="0.1"
              placeholder="10 or -10"
              value={data.percent}
              onChange={(e) => setData('percent', e.target.value)}
              required
            />
            {errors.percent ? <p className="text-sm text-red-600">{errors.percent}</p> : null}
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="bulk-reason">Reason (optional)</Label>
            <Input
              id="bulk-reason"
              value={data.reason}
              onChange={(e) => setData('reason', e.target.value)}
            />
          </div>
          <DialogFooter>
            <Button type="submit" disabled={processing}>
              Apply to category
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}

function ServicePricingCard({ service }: { service: ServiceRow }) {
  return (
    <Card className="p-4">
      <div className="flex items-start justify-between gap-4">
        <div>
          <p className="text-ink font-medium">{service.name}</p>
          <p className="text-ink-muted text-sm">
            {service.category ?? '—'} · Base price {formatCurrency(service.base_price)}
          </p>
        </div>
        <AddPriceDialog service={service} />
      </div>
      {service.prices.length > 0 ? (
        <div className="mt-3 space-y-1">
          {service.prices.map((price) => (
            <div
              key={price.id}
              className="flex items-center justify-between gap-2 rounded border px-3 py-1.5 text-sm"
            >
              <div className="flex items-center gap-2">
                <Badge variant="outline">{price.price_list}</Badge>
                <span>{formatCurrency(price.price)}</span>
                {(price.effective_from || price.effective_to) && (
                  <span className="text-ink-muted">
                    {price.effective_from ?? '…'} – {price.effective_to ?? '…'}
                  </span>
                )}
              </div>
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button type="button" variant="ghost" size="icon" aria-label="Actions">
                    <MoreHorizontal className="size-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem
                    className="text-red-600 hover:bg-red-50 focus:bg-red-50"
                    onSelect={() => {
                      if (confirm('Remove this price override?')) {
                        router.delete(`/admin/pricing/${price.id}`);
                      }
                    }}
                  >
                    Remove
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            </div>
          ))}
        </div>
      ) : (
        <p className="text-ink-muted mt-3 text-sm">No overrides — always charges the base price.</p>
      )}
    </Card>
  );
}

export default function Pricing({ services, categories }: PricingPageProps) {
  return (
    <>
      <Head title="Pricing" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Pricing</h1>
            <p className="text-ink-muted text-sm">
              Base prices, weekend/seasonal overrides, and bulk category adjustments.
            </p>
          </div>
          <BulkAdjustDialog categories={categories} />
        </div>

        {services.data.length === 0 ? (
          <EmptyState title="No services yet" description="Create services first, then set pricing." />
        ) : (
          <div className="space-y-3">
            {services.data.map((service) => (
              <ServicePricingCard key={service.id} service={service} />
            ))}
          </div>
        )}
      </div>
    </>
  );
}

Pricing.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
