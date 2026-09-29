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
import { Textarea } from '@/Components/ui/textarea';

interface FaqEntry {
  question: string;
  answer: string;
}

export interface ServiceDialogRow {
  id: number;
  service_category_id: number;
  sku: string | null;
  name: string;
  description: string | null;
  duration_min: number;
  buffer_min: number | null;
  base_price: string;
  is_featured: boolean;
  is_active: boolean;
  faq: FaqEntry[] | null;
  staff_ids: number[];
  stock_image_url: string | null;
  image_url: string | null;
}

interface ServiceFormDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** `null` = "New service" mode. Give the dialog a `key` (e.g. the row id, or `'new'`) from the
   * caller so it remounts — and its useForm() re-initializes — when switching which row is being
   * edited, rather than reusing stale form state across a different target. */
  service: ServiceDialogRow | null;
  categories: { id: number; name: string }[];
  staff: { id: number; name: string | null }[];
}

/**
 * The list page's quick-edit modal — same fields, same validation, same image
 * upload-or-URL precedence as the full-page ServiceForm.tsx (which stays reachable directly by
 * URL as a fallback), just without leaving the Services list. Kept in sync with ServiceForm.tsx by
 * hand since Inertia forms can't easily share a `useForm()` instance across two mount points; both
 * post to the exact same StoreServiceRequest/UpdateServiceRequest-backed routes, so a field added
 * to one and not the other fails loudly (a 422 on the missing field) rather than silently.
 */
export function ServiceFormDialog({ open, onOpenChange, service, categories, staff }: ServiceFormDialogProps) {
  const isEdit = !!service;
  const { data, setData, post, processing, errors, isDirty, reset } = useForm({
    service_category_id: service?.service_category_id ?? categories[0]?.id ?? '',
    sku: service?.sku ?? '',
    name: service?.name ?? '',
    description: service?.description ?? '',
    duration_min: service?.duration_min ?? 30,
    buffer_min: service?.buffer_min ?? 0,
    base_price: service?.base_price ?? '',
    is_featured: service?.is_featured ?? false,
    is_active: service?.is_active ?? true,
    faq: service?.faq ?? ([] as FaqEntry[]),
    staff_ids: service?.staff_ids ?? ([] as number[]),
    image: null as File | null,
    stock_image_url: service?.stock_image_url ?? '',
    _method: isEdit ? 'put' : 'post',
  });

  const [imagePreview, setImagePreview] = React.useState<string | null>(service?.image_url ?? null);

  function submit(e: React.FormEvent) {
    e.preventDefault();
    const url = isEdit ? `/admin/services/${service.id}` : '/admin/services';
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
    setImagePreview(file ? URL.createObjectURL(file) : (data.stock_image_url || service?.image_url) || null);
  }

  function onImageUrlChange(value: string) {
    setData('stock_image_url', value);
    if (!data.image) {
      setImagePreview(value || null);
    }
  }

  function toggleStaff(id: number) {
    setData(
      'staff_ids',
      data.staff_ids.includes(id)
        ? data.staff_ids.filter((s) => s !== id)
        : [...data.staff_ids, id],
    );
  }

  function updateFaq(index: number, field: keyof FaqEntry, value: string) {
    const next = data.faq.map((entry, i) => (i === index ? { ...entry, [field]: value } : entry));
    setData('faq', next);
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{isEdit ? `Edit ${service.name}` : 'New service'}</DialogTitle>
        </DialogHeader>

        <ResourceForm onSubmit={submit} isDirty={isDirty}>
          <FormField label="Category" htmlFor="qs-category" error={errors.service_category_id}>
            <Select
              value={String(data.service_category_id)}
              onValueChange={(value) => setData('service_category_id', Number(value))}
            >
              <SelectTrigger id="qs-category">
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
          </FormField>

          <FormField label="Name" htmlFor="qs-name" error={errors.name}>
            <Input id="qs-name" value={data.name} onChange={(e) => setData('name', e.target.value)} required />
          </FormField>

          <FormField label="SKU" htmlFor="qs-sku" error={errors.sku} hint="Used to match rows during Excel import.">
            <Input id="qs-sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
          </FormField>

          <FormField label="Description" htmlFor="qs-description" error={errors.description}>
            <Textarea
              id="qs-description"
              rows={3}
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
            />
          </FormField>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Duration (min)" htmlFor="qs-duration" error={errors.duration_min}>
              <Input
                id="qs-duration"
                type="number"
                min={1}
                value={data.duration_min}
                onChange={(e) => setData('duration_min', Number(e.target.value))}
                required
              />
            </FormField>
            <FormField label="Buffer (min)" htmlFor="qs-buffer" error={errors.buffer_min}>
              <Input
                id="qs-buffer"
                type="number"
                min={0}
                value={data.buffer_min ?? 0}
                onChange={(e) => setData('buffer_min', Number(e.target.value))}
              />
            </FormField>
          </div>

          <FormField label="Base price" htmlFor="qs-price" error={errors.base_price}>
            <Input
              id="qs-price"
              type="number"
              step="0.01"
              min={0}
              value={data.base_price}
              onChange={(e) => setData('base_price', e.target.value)}
              required
            />
          </FormField>

          <ImageUrlOrUploadField
            idPrefix="qs"
            label="Image"
            previewUrl={imagePreview}
            fileError={errors.image}
            urlValue={data.stock_image_url}
            urlError={errors.stock_image_url}
            onFileChange={onImageFileChange}
            onUrlChange={onImageUrlChange}
          />

          <div className="flex gap-6">
            <label htmlFor="qs-active" className="flex items-center gap-2 text-sm">
              <Checkbox
                id="qs-active"
                checked={data.is_active}
                onCheckedChange={(checked) => setData('is_active', checked === true)}
              />
              Active
            </label>
            <label htmlFor="qs-featured" className="flex items-center gap-2 text-sm">
              <Checkbox
                id="qs-featured"
                checked={data.is_featured}
                onCheckedChange={(checked) => setData('is_featured', checked === true)}
              />
              Featured
            </label>
          </div>

          <FormField label="Staff who perform this service" htmlFor="qs-staff">
            <div className="grid grid-cols-2 gap-2">
              {staff.map((member) => (
                <label key={member.id} className="flex items-center gap-2 text-sm">
                  <Checkbox
                    checked={data.staff_ids.includes(member.id)}
                    onCheckedChange={() => toggleStaff(member.id)}
                  />
                  {member.name ?? `Staff #${member.id}`}
                </label>
              ))}
            </div>
          </FormField>

          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <p className="text-ink text-sm font-medium">FAQ</p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => setData('faq', [...data.faq, { question: '', answer: '' }])}
              >
                <Plus className="size-4" />
                Add question
              </Button>
            </div>
            {data.faq.map((entry, index) => (
              <div key={index} className="flex gap-2">
                <div className="flex-1 space-y-2">
                  <Input
                    placeholder="Question"
                    value={entry.question}
                    onChange={(e) => updateFaq(index, 'question', e.target.value)}
                  />
                  <Textarea
                    placeholder="Answer"
                    rows={2}
                    value={entry.answer}
                    onChange={(e) => updateFaq(index, 'answer', e.target.value)}
                  />
                </div>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  aria-label="Remove question"
                  onClick={() => setData('faq', data.faq.filter((_, i) => i !== index))}
                >
                  <Trash2 className="size-4" />
                </Button>
              </div>
            ))}
          </div>

          <Button type="submit" disabled={processing}>
            {isEdit ? 'Save changes' : 'Create service'}
          </Button>
        </ResourceForm>
      </DialogContent>
    </Dialog>
  );
}
