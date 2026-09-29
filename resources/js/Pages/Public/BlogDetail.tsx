import { Link } from '@inertiajs/react';
import {
  ArrowRight,
  Calendar,
  Clock3,
  Link as LinkIcon,
  MapPin,
  MessageCircle,
  User,
} from 'lucide-react';
import * as React from 'react';

import { SeoHead } from '@/Components/SeoHead';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import PublicLayout from '@/Layouts/PublicLayout';

interface PostSummary {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  cover_image_path: string | null;
  published_at: string;
  author: { id: number | null; name: string };
  category: { id: number; name: string; slug: string } | null;
  readingTimeMinutes: number;
}

interface PostDetail {
  id: number;
  title: string;
  slug: string;
  excerpt: string | null;
  body: string;
  cover_image_path: string | null;
  published_at: string;
  author: { id: number | null; name: string };
  category: { id: number; name: string; slug: string } | null;
  tags: { id: number; name: string }[];
}

interface BlogDetailProps {
  post: PostDetail;
  readingTimeMinutes: number;
  jsonLdSchema: Record<string, unknown>;
  relatedPosts: PostSummary[];
  whatsapp: { display_phone: string; chat_url: string };
  location: { address: string | null };
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString('en-US', { day: 'numeric', month: 'long', year: 'numeric' });
}

/** Author byline reads "By Looks Smart Beauty Team" for the salon's own house account. */
function bylineFor(name: string): string {
  return name === 'Admin' ? 'Looks Smart Beauty Team' : name;
}

export default function BlogDetail({
  post,
  readingTimeMinutes,
  jsonLdSchema,
  relatedPosts,
  whatsapp,
  location,
}: BlogDetailProps) {
  const [copied, setCopied] = React.useState(false);
  const shareUrl = typeof window !== 'undefined' ? window.location.href : '';

  async function copyLink() {
    try {
      await navigator.clipboard.writeText(shareUrl);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // Clipboard access can be denied by the browser; the link is still visible in the address
      // bar, so failing silently here is acceptable rather than surfacing a scary error.
    }
  }

  return (
    <>
      <SeoHead
        title={post.title}
        description={post.excerpt ?? `Read ${post.title} on the Looks Smart Beauty Salon journal.`}
        image={post.cover_image_path}
        jsonLd={jsonLdSchema}
      />

      {/* Full-width hero banner */}
      <section className="relative">
        <div className="bg-accent-50 h-[280px] w-full overflow-hidden sm:h-[380px]">
          {post.cover_image_path && (
            <img
              src={post.cover_image_path}
              alt={post.title}
              className="size-full object-cover"
              decoding="async"
              fetchPriority="high"
            />
          )}
        </div>
        <div
          className="from-ink/80 via-ink/40 absolute inset-0 bg-gradient-to-t to-transparent"
          aria-hidden="true"
        />
        <div className="absolute inset-x-0 bottom-0 px-6 pb-8 lg:px-10">
          <div className="mx-auto max-w-4xl">
            {post.category && (
              <span className="bg-accent-500 text-ivory inline-flex w-fit items-center rounded-full px-3 py-1 text-xs font-semibold uppercase tracking-wider">
                {post.category.name}
              </span>
            )}
            <h1 className="font-display text-ivory mt-3 text-3xl font-medium leading-tight sm:text-4xl lg:text-5xl">
              {post.title}
            </h1>
          </div>
        </div>
      </section>

      {/* Article meta: date, author, share */}
      <section className="border-border-soft bg-surface border-b px-6 py-4 lg:px-10">
        <div className="text-ink-muted mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3 text-sm">
          <div className="flex flex-wrap items-center gap-4">
            <span className="inline-flex items-center gap-1.5">
              <Calendar className="size-4" aria-hidden="true" /> {formatDate(post.published_at)}
            </span>
            <span className="inline-flex items-center gap-1.5">
              <User className="size-4" aria-hidden="true" /> By {bylineFor(post.author.name)}
            </span>
            <span className="inline-flex items-center gap-1.5">
              <Clock3 className="size-4" aria-hidden="true" /> {readingTimeMinutes} min read
            </span>
          </div>
          <div className="flex items-center gap-2">
            <a
              href={`https://twitter.com/intent/tweet?url=${encodeURIComponent(shareUrl)}&text=${encodeURIComponent(post.title)}`}
              target="_blank"
              rel="noopener noreferrer"
              className="border-border-soft hover:border-accent-500 rounded-full border p-2"
              aria-label="Share on Twitter"
            >
              <span className="block size-4 text-center text-xs font-bold leading-4">𝕏</span>
            </a>
            <a
              href={`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(shareUrl)}`}
              target="_blank"
              rel="noopener noreferrer"
              className="border-border-soft hover:border-accent-500 rounded-full border p-2"
              aria-label="Share on Facebook"
            >
              <span className="block size-4 text-center text-xs font-bold leading-4">f</span>
            </a>
            <a
              href={`https://www.linkedin.com/sharing/share-offsite/?url=${encodeURIComponent(shareUrl)}`}
              target="_blank"
              rel="noopener noreferrer"
              className="border-border-soft hover:border-accent-500 rounded-full border p-2"
              aria-label="Share on LinkedIn"
            >
              <span className="block size-4 text-center text-xs font-bold leading-4">in</span>
            </a>
            <button
              type="button"
              onClick={copyLink}
              className="border-border-soft hover:border-accent-500 rounded-full border p-2"
              aria-label="Copy link"
            >
              <LinkIcon className="size-4" aria-hidden="true" />
            </button>
            {copied && <span className="text-accent-700 text-xs">Copied!</span>}
          </div>
        </div>
      </section>

      {/* Content + sticky sidebar */}
      <section className="px-6 py-12 lg:px-10">
        <div className="mx-auto grid max-w-6xl gap-10 lg:grid-cols-[1fr_300px]">
          <div>
            {/* `.article-body` is hand-styled in app.css rather than a typography plugin — see
                that file for why. Sanitized server-side (HtmlSanitizerService, 'blog' profile)
                before it ever reaches here, and again on save, so this is safe to render as HTML. */}
            <div className="article-body" dangerouslySetInnerHTML={{ __html: post.body }} />

            {post.tags.length > 0 && (
              <div className="border-border-soft mt-10 flex flex-wrap gap-2 border-t pt-6">
                {post.tags.map((tag) => (
                  <span
                    key={tag.id}
                    className="border-border-soft text-ink-muted rounded-full border px-3 py-1 text-xs"
                  >
                    #{tag.name}
                  </span>
                ))}
              </div>
            )}

            {/* Bottom conversion banner */}
            <div className="bg-ink text-ivory mt-12 rounded-2xl px-8 py-10 text-center">
              <h2 className="font-display text-2xl font-medium sm:text-3xl">
                Ready to Experience This at Looks Smart?
              </h2>
              <div className="mt-6 flex flex-wrap justify-center gap-3">
                <Button asChild size="lg" variant="accent">
                  <Link href="/book">Book Now</Link>
                </Button>
                <a
                  href={whatsapp.chat_url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-2 rounded-md bg-[#25D366] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1DA851]"
                >
                  <MessageCircle className="size-4" aria-hidden="true" /> WhatsApp Us
                </a>
              </div>
            </div>

            {/* Related articles */}
            {relatedPosts.length > 0 && (
              <div className="mt-14">
                <h2 className="font-display text-ink text-2xl font-medium">Related articles</h2>
                <div className="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                  {relatedPosts.map((related) => (
                    <Card
                      key={related.id}
                      className="border-border-soft bg-surface shadow-soft overflow-hidden"
                    >
                      <div className="bg-accent-50 h-[150px]">
                        {related.cover_image_path && (
                          <img
                            src={related.cover_image_path}
                            alt={related.title}
                            loading="lazy"
                            decoding="async"
                            className="size-full object-cover"
                          />
                        )}
                      </div>
                      <CardContent className="p-4">
                        <h3 className="text-ink text-base leading-snug font-medium">
                          <Link href={`/blog/${related.slug}`} className="hover:text-accent-700">
                            {related.title}
                          </Link>
                        </h3>
                      </CardContent>
                    </Card>
                  ))}
                </div>
              </div>
            )}
          </div>

          {/* Sticky sidebar: quick booking + location + WhatsApp */}
          <aside className="h-fit space-y-5 lg:sticky lg:top-28">
            <Card className="border-accent-300 bg-accent-50">
              <CardContent className="p-5">
                <h2 className="font-display text-ink text-lg font-medium">Book a treatment</h2>
                <p className="text-ink-muted mt-2 text-sm leading-6">
                  Loved what you read? Reserve your appointment in seconds.
                </p>
                <Button asChild variant="accent" className="mt-4 w-full">
                  <Link href="/book">
                    Book Now <ArrowRight className="size-4" aria-hidden="true" />
                  </Link>
                </Button>
              </CardContent>
            </Card>

            {location.address && (
              <Card className="border-border-soft bg-surface">
                <CardContent className="p-5">
                  <div className="text-accent-700 flex items-center gap-2">
                    <MapPin className="size-4" aria-hidden="true" />
                    <h2 className="font-display text-ink text-sm font-semibold uppercase tracking-wider">
                      Our location
                    </h2>
                  </div>
                  <p className="text-ink-muted mt-3 text-sm leading-6">{location.address}</p>
                </CardContent>
              </Card>
            )}

            <a
              href={whatsapp.chat_url}
              target="_blank"
              rel="noopener noreferrer"
              className="flex items-center justify-center gap-2 rounded-lg bg-[#25D366] px-5 py-3 text-sm font-semibold text-white transition-colors hover:bg-[#1DA851]"
            >
              <MessageCircle className="size-4" aria-hidden="true" /> {whatsapp.display_phone}
            </a>
          </aside>
        </div>
      </section>
    </>
  );
}

BlogDetail.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
