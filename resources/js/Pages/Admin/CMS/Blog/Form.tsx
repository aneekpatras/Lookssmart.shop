import { Head, Link, useForm } from '@inertiajs/react';
import * as React from 'react';

import { TipTapEditor } from '@/Components/admin/TipTapEditor';
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
import { Textarea } from '@/Components/ui/textarea';
import AdminLayout from '@/Layouts/AdminLayout';

interface CategoryOption {
  id: number;
  name: string;
}

interface PostDetail {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  body: string;
  post_category_id: number | null;
  seo_title: string | null;
  seo_description: string | null;
  status: 'draft' | 'scheduled' | 'published';
  scheduled_at: string | null;
  cover_image_url: string | null;
  tags: string;
}

interface BlogFormPageProps {
  post?: PostDetail;
  categories: CategoryOption[];
}

export default function BlogForm({ post, categories }: BlogFormPageProps) {
  const isEdit = !!post;

  const { data, setData, post: submit, processing, errors } = useForm({
    title: post?.title ?? '',
    excerpt: post?.excerpt ?? '',
    body: post?.body ?? '',
    post_category_id: post?.post_category_id ? String(post.post_category_id) : '',
    tags: post?.tags ?? '',
    seo_title: post?.seo_title ?? '',
    seo_description: post?.seo_description ?? '',
    status: post?.status ?? 'draft',
    scheduled_at: post?.scheduled_at ?? '',
    cover_image: null as File | null,
    _method: isEdit ? 'put' : 'post',
  });

  function handleSubmit(event: React.FormEvent) {
    event.preventDefault();
    const url = isEdit ? `/admin/blog/${post.id}` : '/admin/blog';
    submit(url, { forceFormData: true });
  }

  const previewTitle = data.seo_title || data.title || 'Untitled post';
  const previewDescription = data.seo_description || data.excerpt || 'No description yet.';

  return (
    <>
      <Head title={isEdit ? 'Edit Post' : 'New Post'} />
      <div className="mx-auto max-w-4xl space-y-6">
        <div>
          <h1 className="font-display text-ink text-2xl font-medium">
            {isEdit ? 'Edit post' : 'New post'}
          </h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-6">
          <Card>
            <CardContent className="space-y-5 p-6">
              <div className="space-y-1.5">
                <Label htmlFor="title">Title</Label>
                <Input
                  id="title"
                  value={data.title}
                  onChange={(e) => setData('title', e.target.value)}
                  aria-invalid={!!errors.title}
                  required
                />
                {errors.title && <p className="text-sm text-red-600">{errors.title}</p>}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="excerpt">Excerpt</Label>
                <Textarea
                  id="excerpt"
                  rows={2}
                  value={data.excerpt}
                  onChange={(e) => setData('excerpt', e.target.value)}
                  aria-invalid={!!errors.excerpt}
                />
                {errors.excerpt && <p className="text-sm text-red-600">{errors.excerpt}</p>}
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="body">Content</Label>
                <TipTapEditor value={data.body} onChange={(html) => setData('body', html)} />
                {errors.body && <p className="text-sm text-red-600">{errors.body}</p>}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-5 p-6">
              <h2 className="font-display text-ink text-lg font-medium">Organization</h2>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="post_category_id">Category</Label>
                  <Select
                    value={data.post_category_id || undefined}
                    onValueChange={(value) => setData('post_category_id', value)}
                  >
                    <SelectTrigger id="post_category_id">
                      <SelectValue placeholder="No category" />
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
                  <Label htmlFor="tags">Tags</Label>
                  <Input
                    id="tags"
                    placeholder="hair, color, tips"
                    value={data.tags}
                    onChange={(e) => setData('tags', e.target.value)}
                  />
                  <p className="text-ink-muted text-xs">Comma-separated. New tags are created automatically.</p>
                </div>
              </div>

              <div className="space-y-1.5">
                <Label htmlFor="cover_image">Cover image</Label>
                {post?.cover_image_url && (
                  <img
                    src={post.cover_image_url}
                    alt=""
                    className="mb-2 h-40 w-full rounded object-cover"
                  />
                )}
                <Input
                  id="cover_image"
                  type="file"
                  accept="image/jpeg,image/png,image/webp,image/gif"
                  onChange={(e) => setData('cover_image', e.target.files?.[0] ?? null)}
                  aria-invalid={!!errors.cover_image}
                />
                {errors.cover_image && <p className="text-sm text-red-600">{errors.cover_image}</p>}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-5 p-6">
              <h2 className="font-display text-ink text-lg font-medium">Publishing</h2>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div className="space-y-1.5">
                  <Label htmlFor="status">Status</Label>
                  <Select value={data.status} onValueChange={(value) => setData('status', value as typeof data.status)}>
                    <SelectTrigger id="status">
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="draft">Draft</SelectItem>
                      <SelectItem value="scheduled">Scheduled</SelectItem>
                      <SelectItem value="published">Published</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                {data.status === 'scheduled' && (
                  <div className="space-y-1.5">
                    <Label htmlFor="scheduled_at">Publish at</Label>
                    <Input
                      id="scheduled_at"
                      type="datetime-local"
                      value={data.scheduled_at}
                      onChange={(e) => setData('scheduled_at', e.target.value)}
                      aria-invalid={!!errors.scheduled_at}
                    />
                    {errors.scheduled_at && (
                      <p className="text-sm text-red-600">{errors.scheduled_at}</p>
                    )}
                  </div>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="space-y-5 p-6">
              <h2 className="font-display text-ink text-lg font-medium">SEO</h2>
              <div className="space-y-1.5">
                <Label htmlFor="seo_title">Meta title</Label>
                <Input
                  id="seo_title"
                  value={data.seo_title}
                  onChange={(e) => setData('seo_title', e.target.value)}
                  placeholder={data.title}
                />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="seo_description">Meta description</Label>
                <Textarea
                  id="seo_description"
                  rows={2}
                  value={data.seo_description}
                  onChange={(e) => setData('seo_description', e.target.value)}
                />
              </div>

              <div className="border-border-soft rounded-lg border p-4">
                <p className="text-ink-muted text-xs font-semibold uppercase tracking-wide">
                  Search preview
                </p>
                <p className="mt-2 truncate text-lg text-blue-700">{previewTitle}</p>
                <p className="text-sm text-emerald-700">yoursite.com/blog/{data.title ? data.title.toLowerCase().replace(/\s+/g, '-') : 'post-slug'}</p>
                <p className="text-ink-muted mt-1 line-clamp-2 text-sm">{previewDescription}</p>
              </div>
            </CardContent>
          </Card>

          <div className="flex items-center gap-3">
            <Button type="submit" disabled={processing}>
              {isEdit ? 'Save changes' : 'Create post'}
            </Button>
            <Button asChild variant="outline">
              <Link href="/admin/blog">Cancel</Link>
            </Button>
          </div>
        </form>
      </div>
    </>
  );
}

BlogForm.layout = (page: React.ReactNode) => <AdminLayout>{page}</AdminLayout>;
