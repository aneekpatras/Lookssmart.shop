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
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

interface FaqEntry {
  question: string;
  answer: string;
}

interface ServiceFormPageProps {
  service?: {
    id: number;
    service_category_id: number;
    sku: string | null;
    name: string;
    slug: string;
    description: string | null;
    duration_min: number;
    buffer_min: number | null;
    base_price: string;
    is_featured: boolean;
    is_active: boolean;
    sort: number | null;
    seo_title: string | null;
    seo_description: string | null;
    faq: FaqEntry[] | null;
    staff_ids: number[];
    stock_image_url: string | null;
    image_url: string | null;
  };
  categories: { id: number; name: string }[];
  staff: { id: number; name: string | null }[];
}

export default function ServiceForm({ service, categories, staff }: ServiceFormPageProps) {
  const isEdit = !!service;
  const { data, setData, post, processing, errors, isDirty } = useForm({
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
    post(url, { forceFormData: true });
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
    <>
      <Head title={isEdit ? `Edit ${service.name}` : 'New service'} />
      <div className="space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">
            {isEdit ? `Edit ${service.name}` : 'New service'}
          </h1>
        </div>

        <ResourceForm onSubmit={submit} isDirty={isDirty} className="max-w-2xl">
          <FormField label="Category" htmlFor="service-category" error={errors.service_category_id}>
            <Select
              value={String(data.service_category_id)}
              onValueChange={(value) => setData('service_category_id', Number(value))}
            >
              <SelectTrigger id="service-category">
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

          <FormField label="Name" htmlFor="service-name" error={errors.name}>
            <Input
              id="service-name"
              value={data.name}
              onChange={(e) => setData('name', e.target.value)}
              required
            />
          </FormField>

          <FormField label="SKU" htmlFor="service-sku" error={errors.sku} hint="Used to match rows during Excel import.">
            <Input id="service-sku" value={data.sku} onChange={(e) => setData('sku', e.target.value)} />
          </FormField>

          <FormField label="Description" htmlFor="service-description" error={errors.description}>
            <Textarea
              id="service-description"
              rows={4}
              value={data.description}
              onChange={(e) => setData('description', e.target.value)}
            />
          </FormField>

          <div className="grid grid-cols-2 gap-4">
            <FormField label="Duration (min)" htmlFor="service-duration" error={errors.duration_min}>
              <Input
                id="service-duration"
                type="number"
                min={1}
                value={data.duration_min}
                onChange={(e) => setData('duration_min', Number(e.target.value))}
                required
              />
            </FormField>
            <FormField label="Buffer (min)" htmlFor="service-buffer" error={errors.buffer_min}>
              <Input
                id="service-buffer"
                type="number"
                min={0}
                value={data.buffer_min ?? 0}
                onChange={(e) => setData('buffer_min', Number(e.target.value))}
              />
            </FormField>
          </div>

          <FormField label="Base price" htmlFor="service-price" error={errors.base_price}>
            <Input
              id="service-price"
              type="number"
              step="0.01"
              min={0}
              value={data.base_price}
              onChange={(e) => setData('base_price', e.target.value)}
              required
            />
          </FormField>

          <ImageUrlOrUploadField
            idPrefix="service"
            label="Image"
            previewUrl={imagePreview}
            fileError={errors.image}
            urlValue={data.stock_image_url}
            urlError={errors.stock_image_url}
            onFileChange={onImageFileChange}
            onUrlChange={onImageUrlChange}
          />

          <div className="flex gap-6">
            <label htmlFor="service-active" className="flex items-center gap-2 text-sm">
              <Checkbox
                id="service-active"
                checked={data.is_active}
                onCheckedChange={(checked) => setData('is_active', checked === true)}
              />
              Active
            </label>
            <label htmlFor="service-featured" className="flex items-center gap-2 text-sm">
              <Checkbox
                id="service-featured"
                checked={data.is_featured}
                onCheckedChange={(checked) => setData('is_featured', checked === true)}
              />
              Featured
            </label>
          </div>

          <FormField label="Staff who perform this service" htmlFor="service-staff">
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
      </div>
    </>
  );
}

ServiceForm.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
