import { Head, Link, useForm } from '@inertiajs/react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import AdminLayout from '@/Layouts/AdminLayout';

interface SlideDetail {
  id: number;
  heading: string | null;
  subheading: string | null;
  cta_text: string | null;
  cta_url: string | null;
  text_position: string;
  image_url: string | null;
  mobile_image_url: string | null;
  sort: number;
  is_active: boolean;
}

interface SlideFormPageProps {
  slide?: SlideDetail;
}

export default function SlideForm({ slide }: SlideFormPageProps) {
  const isEdit = !!slide;

  const { data, setData, post, processing, errors } = useForm({
    heading: slide?.heading ?? '',
    subheading: slide?.subheading ?? '',
    cta_text: slide?.cta_text ?? '',
    cta_url: slide?.cta_url ?? '',
    text_position: slide?.text_position ?? 'center',
    image: null as File | null,
    mobile_image: null as File | null,
    sort: slide?.sort ?? 0,
    is_active: slide?.is_active ?? true,
    _method: isEdit ? 'put' : 'post',
  });

  function submit(event: React.FormEvent) {
    event.preventDefault();
    const url = isEdit ? `/admin/slider/${slide.id}` : '/admin/slider';
    post(url, { forceFormData: true });
  }

  return (
    <>
      <Head title={isEdit ? 'Edit Slide' : 'New Slide'} />
      <div className="mx-auto max-w-2xl space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">
            {isEdit ? 'Edit slide' : 'New slide'}
          </h1>
          <p className="text-ink-muted text-sm">Homepage hero banner slide.</p>
        </div>

        <Card>
          <CardContent className="p-6">
            <form onSubmit={submit} className="space-y-5">
              <div className="space-y-1.5">
                <Label htmlFor="heading">Title</Label>
                <Input
                  id="heading"
                  value={data.heading}
                  onChange={(e) => setData('heading', e.target.value)}
                  aria-invalid={!!errors.heading}
                />
                {errors.heading && <p className="text-sm text-red-600">{errors.heading}</p>}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="subheading">Subtitle</Label>
                <Input
                  id="subheading"
                  value={data.subheading}
                  onChange={(e) => setData('subheading', e.target.value)}
                  aria-invalid={!!errors.subheading}
                />
                {errors.subheading && <p className="text-sm text-red-600">{errors.subheading}</p>}
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div className="space-y-1.5">
                  <Label htmlFor="cta_text">CTA text</Label>
                  <Input
                    id="cta_text"
                    value={data.cta_text}
                    onChange={(e) => setData('cta_text', e.target.value)}
                    aria-invalid={!!errors.cta_text}
                  />
                  {errors.cta_text && <p className="text-sm text-red-600">{errors.cta_text}</p>}
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="cta_url">CTA URL</Label>
                  <Input
                    id="cta_url"
                    value={data.cta_url}
                    onChange={(e) => setData('cta_url', e.target.value)}
                    placeholder="/book"
                    aria-invalid={!!errors.cta_url}
                  />
                  {errors.cta_url && <p className="text-sm text-red-600">{errors.cta_url}</p>}
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="text_position">Text position</Label>
                <Select
                  value={data.text_position}
                  onValueChange={(value) => setData('text_position', value)}
                >
                  <SelectTrigger id="text_position">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="left">Left</SelectItem>
                    <SelectItem value="center">Center</SelectItem>
                    <SelectItem value="right">Right</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="image">Background image {!isEdit && '(required)'}</Label>
                {slide?.image_url && (
                  <img
                    src={slide.image_url}
                    alt=""
                    className="mb-2 h-32 w-full rounded object-cover"
                  />
                )}
                <Input
                  id="image"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  onChange={(e) => setData('image', e.target.files?.[0] ?? null)}
                  aria-invalid={!!errors.image}
                />
                {errors.image && <p className="text-sm text-red-600">{errors.image}</p>}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="mobile_image">Mobile image (optional)</Label>
                {slide?.mobile_image_url && (
                  <img
                    src={slide.mobile_image_url}
                    alt=""
                    className="mb-2 h-24 w-full rounded object-cover"
                  />
                )}
                <Input
                  id="mobile_image"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  onChange={(e) => setData('mobile_image', e.target.files?.[0] ?? null)}
                  aria-invalid={!!errors.mobile_image}
                />
                {errors.mobile_image && (
                  <p className="text-sm text-red-600">{errors.mobile_image}</p>
                )}
              </div>

              <label htmlFor="is_active" className="flex items-center gap-2">
                <Switch
                  id="is_active"
                  checked={data.is_active}
                  onCheckedChange={(checked) => setData('is_active', checked === true)}
                />
                <span className="text-sm">Active</span>
              </label>

              <div className="flex items-center gap-3 pt-2">
                <Button type="submit" disabled={processing}>
                  {isEdit ? 'Save changes' : 'Create slide'}
                </Button>
                <Button asChild variant="outline">
                  <Link href="/admin/slider">Cancel</Link>
                </Button>
              </div>
            </form>
          </CardContent>
        </Card>
      </div>
    </>
  );
}

SlideForm.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
