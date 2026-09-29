import { Head, Link, router } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react';
import * as React from 'react';

import { Button } from '@/Components/ui/button';
import { EmptyState } from '@/Components/ui/empty-state';
import { Switch } from '@/Components/ui/switch';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/Components/ui/table';
import AdminLayout from '@/Layouts/AdminLayout';

interface SlideRow {
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

interface SlidersIndexPageProps {
  slides: SlideRow[];
}

export default function SlidersIndex({ slides }: SlidersIndexPageProps) {
  function move(index: number, direction: -1 | 1) {
    const target = index + direction;
    if (target < 0 || target >= slides.length) return;

    const reordered = [...slides];
    const current = reordered[index];
    const swapWith = reordered[target];
    if (!current || !swapWith) return;
    reordered[index] = swapWith;
    reordered[target] = current;

    router.post(
      '/admin/slider/reorder',
      { order: reordered.map((slide) => slide.id) },
      { preserveScroll: true },
    );
  }

  function toggleActive(slide: SlideRow) {
    router.put(
      `/admin/slider/${slide.id}`,
      { is_active: !slide.is_active },
      { preserveScroll: true },
    );
  }

  function destroy(slide: SlideRow) {
    if (confirm(`Delete the "${slide.heading ?? 'untitled'}" slide?`)) {
      router.delete(`/admin/slider/${slide.id}`, { preserveScroll: true });
    }
  }

  return (
    <>
      <Head title="Hero Slider" />
      <div className="space-y-6">
        <div className="flex items-start justify-between gap-4">
          <div>
            <h1 className="font-display text-ink text-2xl font-medium">Hero Slider</h1>
            <p className="text-ink-muted text-sm">
              Manage the homepage banner slides shown to every visitor.
            </p>
          </div>
          <Button asChild>
            <Link href="/admin/slider/create">
              <Plus className="size-4" />
              New slide
            </Link>
          </Button>
        </div>

        {slides.length === 0 ? (
          <EmptyState
            title="No slides yet"
            description="Add your first homepage banner slide to get started."
          />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Order</TableHead>
                <TableHead>Preview</TableHead>
                <TableHead>Title</TableHead>
                <TableHead>CTA</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="w-24 text-right">Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {slides.map((slide, index) => (
                <TableRow key={slide.id}>
                  <TableCell>
                    <div className="flex items-center gap-1">
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={index === 0}
                        aria-label={`Move "${slide.heading ?? 'slide'}" up`}
                        onClick={() => move(index, -1)}
                      >
                        <ArrowUp className="size-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={index === slides.length - 1}
                        aria-label={`Move "${slide.heading ?? 'slide'}" down`}
                        onClick={() => move(index, 1)}
                      >
                        <ArrowDown className="size-4" />
                      </Button>
                    </div>
                  </TableCell>
                  <TableCell>
                    {slide.image_url ? (
                      <img
                        src={slide.image_url}
                        alt=""
                        className="h-12 w-20 rounded object-cover"
                      />
                    ) : (
                      <div className="bg-sand h-12 w-20 rounded" />
                    )}
                  </TableCell>
                  <TableCell>
                    <div className="font-medium">{slide.heading || 'Untitled'}</div>
                    {slide.subheading && (
                      <div className="text-ink-muted text-xs">{slide.subheading}</div>
                    )}
                  </TableCell>
                  <TableCell>
                    {slide.cta_text ? (
                      <span className="text-sm">
                        {slide.cta_text} &rarr; {slide.cta_url}
                      </span>
                    ) : (
                      <span className="text-ink-muted text-sm">&mdash;</span>
                    )}
                  </TableCell>
                  <TableCell>
                    <label className="flex items-center gap-2">
                      <Switch
                        checked={slide.is_active}
                        onCheckedChange={() => toggleActive(slide)}
                        aria-label={`Toggle "${slide.heading ?? 'slide'}" active`}
                      />
                      <span className="text-ink-muted text-xs">
                        {slide.is_active ? 'Active' : 'Inactive'}
                      </span>
                    </label>
                  </TableCell>
                  <TableCell className="text-right">
                    <div className="flex items-center justify-end gap-1">
                      <Button asChild variant="ghost" size="icon" aria-label={`Edit "${slide.heading ?? 'slide'}"`}>
                        <Link href={`/admin/slider/${slide.id}/edit`}>
                          <Pencil className="size-4" />
                        </Link>
                      </Button>
                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="text-red-600 hover:bg-red-50"
                        aria-label={`Delete "${slide.heading ?? 'slide'}"`}
                        onClick={() => destroy(slide)}
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </div>
    </>
  );
}

SlidersIndex.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
