import { Head, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

import { ImageUrlOrUploadField } from '@/Components/admin/ImageUrlOrUploadField';
import { FormField, ResourceForm } from '@/Components/admin/ResourceForm';
import { Button } from '@/Components/ui/button';
import { Checkbox } from '@/Components/ui/checkbox';
import { Input } from '@/Components/ui/input';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import AdminLayout from '@/Layouts/AdminLayout';
import { formatCurrency } from '@/lib/currency';

interface DealFormPageProps {
  deal?: {
    id: number;
    title: string;
    slug: string;
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
  };
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

export default function DealForm({ deal, services, categories, categoryTags }: DealFormPageProps) {
  const isEdit = !!deal;
  const { data, setData, post, processing, errors, isDirty } = useForm({
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
  const [previewServiceId, setPreviewServiceId] = React.useState<number | null>(
    services[0]?.id ?? null,
  );

  function submit(e: React.FormEvent) {
    e.preventDefault();
    // Always POST, with `_method` spoofing a PUT on edit — PHP never parses a multipart/form-data
    // body on a real PUT request, so a genuine `put()` here would silently drop every field
    // (image included) on save. forceFormData keeps the request shape identical whether or not
    // this particular save includes a new image.
    const url = isEdit ? `/admin/deals/${deal.id}` : '/admin/deals';
    post(url, { forceFormData: true });
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

  function addIncludedService() {
    setData('included_services', [...data.included_services, '']);
  }

  function removeIncludedService(index: number) {
    const next = data.included_services.filter((_, i) => i !== index);
    setData('included_services', next.length ? next : ['']);
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

  const previewService = services.find((s) => s.id === previewServiceId);
  const basePrice = previewService ? Number(previewService.base_price) : 0;
  const numericValue = Number(data.value) || 0;
  let finalPrice = basePrice;
  if (data.type === 'percent') {
    finalPrice = basePrice * (1 - numericValue / 100);
  } else if (data.type === 'fixed') {
    finalPrice = basePrice - numericValue;
  } else {
    finalPrice = numericValue;
  }
  finalPrice = Math.max(0, finalPrice);

  const originalPrice = Number(data.original_price) || 0;
  const dealPrice = Number(data.deal_price) || 0;
  const savingsPercent =
    originalPrice > 0 && data.deal_price !== '' ? Math.round(((originalPrice - dealPrice) / originalPrice) * 100) : null;

  return (
    <>
      <Head title={isEdit ? `Edit ${deal.title}` : 'New deal'} />
      <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
        <div>
          <h1 className="font-display text-ink mb-6 text-2xl font-medium">
            {isEdit ? `Edit ${deal.title}` : 'New deal'}
          </h1>

          <ResourceForm onSubmit={submit} isDirty={isDirty} className="max-w-2xl">
            <FormField label="Title" htmlFor="deal-title" error={errors.title}>
              <Input
                id="deal-title"
                value={data.title}
                onChange={(e) => setData('title', e.target.value)}
                required
              />
            </FormField>

            <FormField
              label="Subtitle / badge"
              htmlFor="deal-subtitle"
              error={errors.subtitle}
              hint='e.g. "Bridal Day Package" or "High Frequency Hair Voucher"'
            >
              <Input
                id="deal-subtitle"
                value={data.subtitle}
                onChange={(e) => setData('subtitle', e.target.value)}
              />
            </FormField>

            <FormField
              label="Category tag"
              htmlFor="deal-category-tag"
              error={errors.category_tag}
              hint="Which filter tab this deal appears under on the public Deals page."
            >
              <Select
                value={data.category_tag || undefined}
                onValueChange={(value) => setData('category_tag', value)}
              >
                <SelectTrigger id="deal-category-tag">
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
              <FormField label="Type" htmlFor="deal-type" error={errors.type}>
                <Select
                  value={data.type}
                  onValueChange={(value) => setData('type', value as typeof data.type)}
                >
                  <SelectTrigger id="deal-type">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="percent">Percent off</SelectItem>
                    <SelectItem value="fixed">Fixed amount off</SelectItem>
                    <SelectItem value="bundle">Bundle price</SelectItem>
                  </SelectContent>
                </Select>
              </FormField>
              <FormField
                label={data.type === 'percent' ? 'Percent' : 'Amount (Rs.)'}
                htmlFor="deal-value"
                error={errors.value}
                hint="The real discount the booking engine applies — must match the price breakdown below."
              >
                <Input
                  id="deal-value"
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
              <FormField
                label="Original (cut) price — Rs."
                htmlFor="deal-original-price"
                error={errors.original_price}
                hint="Shown with a strikethrough on the card. Leave blank to hide the price box entirely."
              >
                <Input
                  id="deal-original-price"
                  type="number"
                  step="0.01"
                  min={0}
                  value={data.original_price}
                  onChange={(e) => setData('original_price', e.target.value)}
                />
              </FormField>
              <FormField
                label="Deal price — Rs."
                htmlFor="deal-deal-price"
                error={errors.deal_price}
                hint={savingsPercent !== null ? `${savingsPercent}% off, based on the two prices above.` : undefined}
              >
                <Input
                  id="deal-deal-price"
                  type="number"
                  step="0.01"
                  min={0}
                  value={data.deal_price}
                  onChange={(e) => setData('deal_price', e.target.value)}
                />
              </FormField>
            </div>

            <FormField
              label="Included services"
              htmlFor="deal-included-services"
              error={errors.included_services}
              hint="The checklist shown on the card and in the details modal. Free text — does not have to match catalog service names exactly."
            >
              <div className="space-y-2">
                {data.included_services.map((item, index) => (
                  <div key={index} className="flex items-center gap-2">
                    <Input
                      value={item}
                      onChange={(e) => updateIncludedService(index, e.target.value)}
                      placeholder="e.g. Bridal hair styling"
                    />
                    <Button
                      type="button"
                      variant="ghost"
                      size="icon"
                      aria-label="Remove line"
                      onClick={() => removeIncludedService(index)}
                    >
                      <Trash2 className="size-4" />
                    </Button>
                  </div>
                ))}
                <Button type="button" variant="outline" size="sm" onClick={addIncludedService}>
                  <Plus className="size-4" /> Add line
                </Button>
              </div>
            </FormField>

            <FormField
              label="Full description"
              htmlFor="deal-description"
              error={errors.description}
              hint="Shown in the details modal, below the price and checklist."
            >
              <textarea
                id="deal-description"
                rows={4}
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
                className="border-border-soft bg-surface text-ink placeholder:text-ink-muted/70 focus-visible:ring-accent-500 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
              />
            </FormField>

            <FormField
              label="Terms & conditions"
              htmlFor="deal-terms"
              error={errors.terms}
              hint="Shown at the bottom of the details modal."
            >
              <textarea
                id="deal-terms"
                rows={3}
                value={data.terms}
                onChange={(e) => setData('terms', e.target.value)}
                className="border-border-soft bg-surface text-ink placeholder:text-ink-muted/70 focus-visible:ring-accent-500 w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none"
              />
            </FormField>

            <ImageUrlOrUploadField
              idPrefix="deal"
              label="Feature image"
              previewUrl={imagePreview}
              fileError={errors.image}
              urlValue={data.stock_image_url}
              urlError={errors.stock_image_url}
              onFileChange={onImageFileChange}
              onUrlChange={onImageUrlChange}
            />

            <FormField
              label="Coupon code"
              htmlFor="deal-code"
              error={errors.code}
              hint="Optional for an auto-apply-only deal — but a public showcase card's Claim Offer button links straight to booking with this code, so give it one if you want Claim Offer to genuinely apply the discount. Matched case-insensitively."
            >
              <Input id="deal-code" value={data.code} onChange={(e) => setData('code', e.target.value)} />
            </FormField>

            <div className="grid grid-cols-2 gap-4">
              <FormField label="Starts at" htmlFor="deal-starts" error={errors.starts_at}>
                <Input
                  id="deal-starts"
                  type="datetime-local"
                  value={data.starts_at}
                  onChange={(e) => setData('starts_at', e.target.value)}
                  required
                />
              </FormField>
              <FormField label="Ends at" htmlFor="deal-ends" error={errors.ends_at}>
                <Input
                  id="deal-ends"
                  type="datetime-local"
                  value={data.ends_at}
                  onChange={(e) => setData('ends_at', e.target.value)}
                  required
                />
              </FormField>
            </div>

            <div className="grid grid-cols-3 gap-4">
              <FormField label="Usage limit" htmlFor="deal-usage-limit" error={errors.usage_limit} hint="Total, all users">
                <Input
                  id="deal-usage-limit"
                  type="number"
                  min={1}
                  value={data.usage_limit}
                  onChange={(e) => setData('usage_limit', e.target.value)}
                />
              </FormField>
              <FormField label="Per-user limit" htmlFor="deal-per-user-limit" error={errors.per_user_limit}>
                <Input
                  id="deal-per-user-limit"
                  type="number"
                  min={1}
                  value={data.per_user_limit}
                  onChange={(e) => setData('per_user_limit', e.target.value)}
                />
              </FormField>
              <FormField label="Min. spend (Rs.)" htmlFor="deal-min-amount" error={errors.min_amount}>
                <Input
                  id="deal-min-amount"
                  type="number"
                  step="0.01"
                  min={0}
                  value={data.min_amount}
                  onChange={(e) => setData('min_amount', e.target.value)}
                />
              </FormField>
            </div>

            <div className="flex flex-wrap gap-6">
              <label htmlFor="deal-active" className="flex items-center gap-2 text-sm">
                <Checkbox
                  id="deal-active"
                  checked={data.is_active}
                  onCheckedChange={(checked) => setData('is_active', checked === true)}
                />
                Active
              </label>
              <label htmlFor="deal-top-deal" className="flex items-center gap-2 text-sm">
                <Checkbox
                  id="deal-top-deal"
                  checked={data.is_top_deal}
                  onCheckedChange={(checked) => setData('is_top_deal', checked === true)}
                />
                Pin to Top Deals
              </label>
              <label htmlFor="deal-stackable" className="flex items-center gap-2 text-sm">
                <Checkbox
                  id="deal-stackable"
                  checked={data.is_stackable}
                  onCheckedChange={(checked) => setData('is_stackable', checked === true)}
                />
                Stackable
              </label>
              <label htmlFor="deal-auto-apply" className="flex items-center gap-2 text-sm">
                <Checkbox
                  id="deal-auto-apply"
                  checked={data.is_auto_apply}
                  onCheckedChange={(checked) => setData('is_auto_apply', checked === true)}
                />
                Auto-apply
              </label>
            </div>

            <FormField label="Applicable services" htmlFor="deal-services">
              <div className="grid grid-cols-2 gap-2">
                {services.map((service) => (
                  <label key={service.id} className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={data.service_ids.includes(service.id)}
                      onCheckedChange={() => toggleService(service.id)}
                    />
                    {service.name}
                  </label>
                ))}
              </div>
            </FormField>

            <FormField label="Applicable categories" htmlFor="deal-categories">
              <div className="grid grid-cols-2 gap-2">
                {categories.map((category) => (
                  <label key={category.id} className="flex items-center gap-2 text-sm">
                    <Checkbox
                      checked={data.category_ids.includes(category.id)}
                      onCheckedChange={() => toggleCategory(category.id)}
                    />
                    {category.name}
                  </label>
                ))}
              </div>
            </FormField>

            <Button type="submit" disabled={processing}>
              {isEdit ? 'Save changes' : 'Create deal'}
            </Button>
          </ResourceForm>
        </div>

        <div className="bg-sand/40 h-fit space-y-3 rounded-lg border p-4">
          <p className="text-ink text-sm font-medium">Live final-price preview</p>
          <Select
            value={previewServiceId ? String(previewServiceId) : undefined}
            onValueChange={(value) => setPreviewServiceId(Number(value))}
          >
            <SelectTrigger>
              <SelectValue placeholder="Pick a service" />
            </SelectTrigger>
            <SelectContent>
              {services.map((service) => (
                <SelectItem key={service.id} value={String(service.id)}>
                  {service.name}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          {previewService ? (
            <div className="space-y-1 text-sm">
              <p className="text-ink-muted">
                Base price: <span className="text-ink">{formatCurrency(basePrice)}</span>
              </p>
              <p className="text-ink-muted">
                Final price (from Type/Value):{' '}
                <span className="text-ink font-semibold">{formatCurrency(finalPrice)}</span>
              </p>
              <p className="text-ink-muted text-xs">
                This is what the booking engine actually charges — keep it in sync with the
                Original/Deal price shown on the public card above.
              </p>
            </div>
          ) : (
            <p className="text-ink-muted text-sm">No services to preview against yet.</p>
          )}
        </div>
      </div>
    </>
  );
}

DealForm.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
