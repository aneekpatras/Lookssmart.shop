import { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

import { ImageUrlOrUploadField } from '@/Components/admin/ImageUrlOrUploadField';
import { FormField, ResourceForm } from '@/Components/admin/ResourceForm';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';

export interface DealDialogRow {
  id: number;
  title: string;
  subtitle: string | null;
  category_tag: string | null;
  type: 'percent' | 'fixed' | 'bundle';
  value: string;
  original_price: string | null;
  deal_price: string | null;
  included_services: string[] | null;
  description: string | null;
  terms: string | null;
  stock_image_url: string | null;
  image_url: string | null;
  code: string | null;
  starts_at: string | null;
  ends_at: string | null;
  usage_limit: number | null;
  per_user_limit: number | null;
  min_amount: string | null;
  is_stackable: boolean;
  is_auto_apply: boolean;
  is_active: boolean;
  is_top_deal: boolean;
  service_ids: number[];
  category_ids: number[];
}

interface DealFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** `null` = "New deal" mode. Give the dialog a `key` from the caller (row id, or `'new'`) so it
   * remounts — and its useForm() re-initializes — when the target row changes. */
  deal: DealDialogRow | null;
  services: { id: number; name: string; base_price: string }[];
  categories: { id: number; name: string }[];
  categoryTags: string[];
}

function toDatetimeLocal(value: string | null | undefined) {
  if (!value) {
    return '';
  }

  return value.replace(' ', 'T').slice(0, 16);
}

/**
 * The list page's quick-edit modal — same fields, same validation, same PUT-via-spoofed-POST and
 * image upload-or-URL precedence as the full-page DealForm.tsx (which stays reachable directly by
 * URL as a fallback), just without leaving the Deals list and without the "live final-price
 * preview" side panel (a nice-to-have the full page keeps; not essential for data entry). Kept in
 * sync with DealForm.tsx by hand — see the equivalent note on ServiceFormDialog.tsx.
 */
export function DealFormDialog({ open, onOpenChange, deal, services, categories, categoryTags }: DealFormDialogProps) {
  const isEdit = !!deal;
  const { data, setData, post, processing, errors, isDirty, reset } = useForm({
    title: deal?.title ?? '',
    subtitle: deal?.subtitle ?? '',
    category_tag: deal?.category_tag ?? '',
    type: deal?.type ?? 'percent',
    value: deal?.value ?? '',
    original_price: deal?.original_price ?? '',
    deal_price: deal?.deal_price ?? '',
    included_services: deal?.included_services?.length ? deal.included_services : [''],
    description: deal?.description ?? '',
    terms: deal?.terms ?? '',
    image: null as File | null,
    stock_image_url: deal?.stock_image_url ?? '',
    code: deal?.code ?? '',
    starts_at: toDatetimeLocal(deal?.starts_at),
    ends_at: toDatetimeLocal(deal?.ends_at),
    usage_limit: deal?.usage_limit ?? '',
    per_user_limit: deal?.per_user_limit ?? '',
    min_amount: deal?.min_amount ?? '',
    is_stackable: deal?.is_stackable ?? false,
    is_auto_apply: deal?.is_auto_apply ?? false,
    is_active: deal?.is_active ?? true,
    is_top_deal: deal?.is_top_deal ?? false,
    service_ids: deal?.service_ids ?? ([] as number[]),
    category_ids: deal?.category_ids ?? ([] as number[]),
    _method: isEdit ? 'put' : 'post',
  });

  const [imagePreview, setImagePreview] = React.useState<string | null>(deal?.image_url ?? null);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const url = isEdit ? `/admin/deals/${deal.id}` : '/admin/deals';
    post(url, {
      forceFormData: true,
      preserveScroll: true,
      onSuccess: () => {
        onOpenChange(false);
        reset();
      },
    });
  }

  function onImageFileChange(file: File | null) {
    setData('image', file);
    setImagePreview(file ? URL.createObjectURL(file) : (data.stock_image_url || deal?.image_url) || null);
  }

  function onImageUrlChange(value: string) {
    setData('stock_image_url', value);
    if (!data.image) {
      setImagePreview(value || null);
    }
  }

  function toggleService(id: number) {
    setData(
      'service_ids',
      data.service_ids.includes(id) ? data.service_ids.filter((s) => s !== id) : [...data.service_ids, id],
    );
  }

  function toggleCategory(id: number) {
    setData(
      'category_ids',
      data.category_ids.includes(id)
        ? data.category_ids.filter((c) => c !== id)
        : [...data.category_ids, id],
    );
  }

  function updateIncludedService(index: number, value: string) {
    const next = [...data.included_services];
    next[index] = value;
    setData('included_services', next);
  }

  function removeIncludedService(index: number) {
    const next = data.included_services.filter((_, i) => i !== index);
    setData('included_services', next.length ? next : ['']);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? `Edit ${deal.title}` : 'New deal'}</DialogTitle>
        </DialogHeader>

        <ResourceForm onSubmit={submit} isDirty={isDirty}>
          <FormField label="Title" htmlFor="qd-title" error={errors.title}>
            <Input id="qd-title" value={data.title} onChange={(e) => setData('title', e.target.value)} required />
          </FormField>

          <FormField label="Subtitle / badge" htmlFor="qd-subtitle" error={errors.subtitle}>
            <Input id="qd-subtitle" value={data.subtitle} onChange={(e) => setData('subtitle', e.target.value)} />
          </FormField>

          <FormField label="Category tag" htmlFor="qd-category-tag" error={errors.category_tag}>
            <Select value={data.category_tag || undefined} onValueChange={(value) => setData('category_tag', value)}>
              <SelectTrigger id="qd-category-tag">
                <SelectValue placeholder="No tag — hidden from the public page" />
              </SelectTrigger>
              <SelectContent>
                {categoryTags.map((tag) => (
                  <SelectItem key={tag} value={tag}>
                    {tag}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </FormField>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Type" htmlFor="qd-type" error={errors.type}>
              <Select value={data.type} onValueChange={(value) => setData('type', value as typeof data.type)}>
                <SelectTrigger id="qd-type">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="percent">Percent off</SelectItem>
                  <SelectItem value="fixed">Fixed amount off</SelectItem>
                  <SelectItem value="bundle">Bundle price</SelectItem>
                </SelectContent>
              </Select>
            </FormField>
            <FormField label={data.type === 'percent' ? 'Percent' : 'Amount (Rs.)'} htmlFor="qd-value" error={errors.value}>
              <Input
                id="qd-value"
                type="number"
                step="0.01"
                min={0}
                value={data.value}
                onChange={(e) => setData('value', e.target.value)}
                required
              />
            </FormField>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Original (cut) price — Rs." htmlFor="qd-original-price" error={errors.original_price}>
              <Input
                id="qd-original-price"
                type="number"
                step="0.01"
                min={0}
                value={data.original_price}
                onChange={(e) => setData('original_price', e.target.value)}
              />
            </FormField>
            <FormField label="Deal price — Rs." htmlFor="qd-deal-price" error={errors.deal_price}>
              <Input
                id="qd-deal-price"
                type="number"
                step="0.01"
                min={0}
                value={data.deal_price}
                onChange={(e) => setData('deal_price', e.target.value)}
              />
            </FormField>
          </div>

          <FormField label="Included services" htmlFor="qd-included-services" error={errors.included_services}>
            <div className="space-y-2">
              {data.included_services.map((item, index) => (
                <div key={index} className="flex items-center gap-2">
                  <Input
                    value={item}
                    onChange={(e) => updateIncludedService(index, e.target.value)}
                    placeholder="e.g. Bridal hair styling"
                  />
                  <Button type="button" variant="ghost" size="icon" aria-label="Remove line" onClick={() => removeIncludedService(index)}>
                    <Trash2 className="size-4" />
                  </Button>
                </div>
              ))}
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setData('included_services', [...data.included_services, ''])}
              >
                <Plus className="size-4" /> Add line
              </Button>
            </div>
          </FormField>

          <FormField label="Full description" htmlFor="qd-description" error={errors.description}>
            <textarea
              id="qd-description"
              rows={3}
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
              className="border-border-soft bg-surface text-ink placeholder:text-ink-muted/70 focus-visible:ring-accent-500 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            />
          </FormField>

          <FormField label="Terms & conditions" htmlFor="qd-terms" error={errors.terms}>
            <textarea
              id="qd-terms"
              rows={2}
              value={data.terms}
              onChange={(e) => setData('terms', e.target.value)}
              className="border-border-soft bg-surface text-ink placeholder:text-ink-muted/70 focus-visible:ring-accent-500 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
            />
          </FormField>

          <ImageUrlOrUploadField
            idPrefix="qd"
            label="Feature image"
            previewUrl={imagePreview}
            fileError={errors.image}
            urlValue={data.stock_image_url}
            urlError={errors.stock_image_url}
            onFileChange={onImageFileChange}
            onUrlChange={onImageUrlChange}
          />

          <FormField label="Coupon code" htmlFor="qd-code" error={errors.code}>
            <Input id="qd-code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
          </FormField>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Starts at" htmlFor="qd-starts" error={errors.starts_at}>
              <Input
                id="qd-starts"
                type="datetime-local"
                value={data.starts_at}
                onChange={(e) => setData('starts_at', e.target.value)}
                required
              />
            </FormField>
            <FormField label="Ends at" htmlFor="qd-ends" error={errors.ends_at}>
              <Input
                id="qd-ends"
                type="datetime-local"
                value={data.ends_at}
                onChange={(e) => setData('ends_at', e.target.value)}
                required
              />
            </FormField>
          </div>

          <div className="grid grid-cols-3 gap-4">
            <FormField label="Usage limit" htmlFor="qd-usage-limit" error={errors.usage_limit}>
              <Input
                id="qd-usage-limit"
                type="number"
                min={1}
                value={data.usage_limit}
                onChange={(e) => setData('usage_limit', e.target.value)}
              />
            </FormField>
            <FormField label="Per-user limit" htmlFor="qd-per-user-limit" error={errors.per_user_limit}>
              <Input
                id="qd-per-user-limit"
                type="number"
                min={1}
                value={data.per_user_limit}
                onChange={(e) => setData('per_user_limit', e.target.value)}
              />
            </FormField>
            <FormField label="Min. spend (Rs.)" htmlFor="qd-min-amount" error={errors.min_amount}>
              <Input
                id="qd-min-amount"
                type="number"
                step="0.01"
                min={0}
                value={data.min_amount}
                onChange={(e) => setData('min_amount', e.target.value)}
              />
            </FormField>
          </div>

          <div className="flex flex-wrap gap-6">
            <label htmlFor="qd-active" className="flex items-center gap-2 text-sm">
              <Checkbox id="qd-active" checked={data.is_active} onCheckedChange={(checked) => setData('is_active', checked === true)} />
              Active
            </label>
            <label htmlFor="qd-top-deal" className="flex items-center gap-2 text-sm">
              <Checkbox id="qd-top-deal" checked={data.is_top_deal} onCheckedChange={(checked) => setData('is_top_deal', checked === true)} />
              Pin to Top Deals
            </label>
            <label htmlFor="qd-stackable" className="flex items-center gap-2 text-sm">
              <Checkbox id="qd-stackable" checked={data.is_stackable} onCheckedChange={(checked) => setData('is_stackable', checked === true)} />
              Stackable
            </label>
            <label htmlFor="qd-auto-apply" className="flex items-center gap-2 text-sm">
              <Checkbox id="qd-auto-apply" checked={data.is_auto_apply} onCheckedChange={(checked) => setData('is_auto_apply', checked === true)} />
              Auto-apply
            </label>
          </div>

          <FormField label="Applicable services" htmlFor="qd-services">
            <div className="grid grid-cols-2 gap-2">
              {services.map((service) => (
                <label key={service.id} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={data.service_ids.includes(service.id)} onCheckedChange={() => toggleService(service.id)} />
                  {service.name}
                </label>
              ))}
            </div>
          </FormField>

          <FormField label="Applicable categories" htmlFor="qd-categories">
            <div className="grid grid-cols-2 gap-2">
              {categories.map((category) => (
                <label key={category.id} className="flex items-center gap-2 text-sm">
                  <Checkbox checked={data.category_ids.includes(category.id)} onCheckedChange={() => toggleCategory(category.id)} />
                  {category.name}
                </label>
              ))}
            </div>
          </FormField>

          <Button type="submit" disabled={processing}>
            {isEdit ? 'Save changes' : 'Create deal'}
          </Button>
        </ResourceForm>
      </DialogContent>
    </Dialog>
  );
}
