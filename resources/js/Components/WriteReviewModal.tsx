import { usePage } from '@inertiajs/react';
import axios from 'axios';
import { ArrowRight, Send, Star } from 'lucide-react';
import * as React from 'react';
import { toast } from 'sonner';

import { Button } from '@/Components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';

import type { ReviewShowcase } from './ReviewsCarousel';

const SERVICE_CATEGORIES = [
  'Hair Treatments',
  'Facials & Skin Care',
  'Bridal & Makeup',
  'Laser Hair Removal',
] as const;

type ServiceCategory = (typeof SERVICE_CATEGORIES)[number];

const DEFAULT_REVIEWER_NAME = 'Verified Guest';

/**
 * 3 smart templates per category, written to read like genuine client phrasing rather than generic
 * marketing copy — a customer can click one as a starting point or ignore all of them and type their
 * own review from scratch (the textarea is never read-only).
 */
const TEMPLATES: Record<ServiceCategory, string[]> = {
  'Hair Treatments': [
    'Extremely impressed with the Keratin treatment! My hair feels silky, soft, and so healthy. Highly recommended!',
    'Got a haircut and blow dry here and the stylist really listened to what I wanted. Left feeling so much more confident.',
    'My hair botox treatment completely transformed my frizz. Weeks later it still looks smooth and healthy.',
  ],
  'Facials & Skin Care': [
    'My skin has never looked this fresh. The facial was relaxing and the results showed immediately.',
    'The therapist actually checked my skin type before choosing products instead of a generic routine. Loved the results.',
    'Booked a facial before an event and my skin looked flawless under makeup the next day. Will be back.',
  ],
  'Bridal & Makeup': [
    'My bridal makeup lasted the entire day through photos and dancing without a single touch-up. So grateful!',
    'The artist listened to my reference photos and gave me exactly the look I wanted for my engagement.',
    'Party makeup that photographed beautifully under event lighting. Got so many compliments all night.',
  ],
  'Laser Hair Removal': [
    'The technician explained every step and the sessions were far more comfortable than I expected.',
    'Real, visible results after just a few sessions. The staff take hygiene and aftercare seriously.',
    'Appointments always start on time and the team genuinely tracks your progress between sessions.',
  ],
};

/** The globally shared `auth` block — see HandleInertiaRequests::share(). */
interface AuthProps {
  [key: string]: unknown;
  auth: { user: { id: number; name: string } | null };
}

interface WriteReviewModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  /** Called with the real, server-created review so the caller can show it in the carousel
   * immediately — never called with fabricated/optimistic data before the server confirms it. */
  onReviewSaved: (review: ReviewShowcase) => void;
}

/**
 * "Share Your Experience with Looks Smart" — a 2-screen flow (the task's "Step 1"/"Step 2" are a
 * single scrollable step each).
 *
 * Purely a local, in-app submission now — no external Google redirect and no clipboard step. The
 * review (rating, text, service tag, name, submission date) is saved straight to this site's own
 * `reviews` table via `POST /reviews` and appended live to the homepage carousel. If a Google
 * write-review integration is wanted again later, that is a genuinely separate feature, not this
 * flow re-acquiring a side effect it was deliberately stripped of.
 */
export function WriteReviewModal({ open, onOpenChange, onReviewSaved }: WriteReviewModalProps) {
  const { auth } = usePage<AuthProps>().props;
  const [step, setStep] = React.useState<1 | 2>(1);
  const [rating, setRating] = React.useState(0);
  const [hoverRating, setHoverRating] = React.useState(0);
  const [category, setCategory] = React.useState<ServiceCategory | null>(null);
  const [comment, setComment] = React.useState('');
  const [name, setName] = React.useState('');
  const [submitting, setSubmitting] = React.useState(false);
  // Lazy initializer (the React-sanctioned way to run an impure call like `Date.now()` exactly
  // once rather than on every render) — the honeypot time-trap's "when did this form render"
  // timestamp, mirroring `SubmitLeadRequest`'s `rendered_at` contract on the contact form.
  const [renderedAt, setRenderedAt] = React.useState(() => Math.floor(Date.now() / 1000));

  function reset() {
    setStep(1);
    setRating(0);
    setHoverRating(0);
    setCategory(null);
    setComment('');
    setName('');
    setRenderedAt(Math.floor(Date.now() / 1000));
  }

  function handleOpenChange(next: boolean) {
    if (!next) reset();
    onOpenChange(next);
  }

  function applyTemplate(template: string) {
    setComment(template);
  }

  async function submit() {
    if (rating === 0 || comment.trim() === '' || submitting) return;

    setSubmitting(true);

    try {
      const response = await axios.post<{ ok: boolean; review: ReviewShowcase | null }>(
        '/reviews',
        {
          name: auth.user ? undefined : name.trim() || undefined,
          rating,
          category,
          body: comment.trim(),
          website: '', // honeypot — always blank for a real visitor
          rendered_at: renderedAt,
        },
      );

      if (response.data.review) {
        onReviewSaved(response.data.review);
      }

      toast.success('Thank you! Your review has been submitted successfully.');
      handleOpenChange(false);
    } catch {
      toast.error('Something went wrong saving your review — please try again.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Share Your Experience with Looks Smart</DialogTitle>
          <DialogDescription>
            {step === 1
              ? 'Rate your visit and tell us which treatment it was for.'
              : 'Pick a starting point below, or write your own from scratch.'}
          </DialogDescription>
        </DialogHeader>

        {step === 1 && (
          <div className="space-y-5">
            <div>
              <p className="text-ink text-sm font-medium">Your rating</p>
              <div
                className="mt-2 flex gap-1"
                role="group"
                aria-label="Rating out of 5 stars"
                onMouseLeave={() => setHoverRating(0)}
              >
                {[1, 2, 3, 4, 5].map((value) => (
                  <button
                    key={value}
                    type="button"
                    aria-pressed={rating === value}
                    aria-label={`${value} star${value === 1 ? '' : 's'}`}
                    onMouseEnter={() => setHoverRating(value)}
                    onClick={() => setRating(value)}
                    className="focus-visible:ring-accent-500 rounded p-0.5 focus-visible:ring-2 focus-visible:outline-none"
                  >
                    <Star
                      className={`size-8 transition-colors ${
                        value <= (hoverRating || rating)
                          ? 'text-accent-500 fill-current'
                          : 'text-border-soft'
                      }`}
                    />
                  </button>
                ))}
              </div>
            </div>

            <div>
              <p className="text-ink text-sm font-medium">Which service was this for?</p>
              <div className="mt-2 flex flex-wrap gap-2" role="group" aria-label="Service category">
                {SERVICE_CATEGORIES.map((option) => (
                  <button
                    key={option}
                    type="button"
                    aria-pressed={category === option}
                    onClick={() => setCategory(option)}
                    className={`rounded-full border px-3 py-1.5 text-sm transition-colors ${
                      category === option
                        ? 'border-accent-600 bg-accent-600 text-ivory'
                        : 'border-border-soft text-ink-muted hover:border-accent-500'
                    }`}
                  >
                    {option}
                  </button>
                ))}
              </div>
            </div>

            <Button
              type="button"
              variant="accent"
              className="w-full"
              disabled={rating === 0 || category === null}
              onClick={() => setStep(2)}
            >
              Continue <ArrowRight className="size-4" aria-hidden="true" />
            </Button>
          </div>
        )}

        {step === 2 && category && (
          <div className="space-y-5">
            <div>
              <p className="text-ink text-sm font-medium">Smart templates</p>
              <p className="text-ink-muted text-xs">Tap one to start, then edit it however you like.</p>
              <div className="mt-2 space-y-2">
                {TEMPLATES[category].map((template) => (
                  <button
                    key={template}
                    type="button"
                    onClick={() => applyTemplate(template)}
                    className="border-border-soft bg-ivory hover:border-accent-500 w-full rounded-lg border p-3 text-left text-sm leading-6 transition-colors"
                  >
                    {template}
                  </button>
                ))}
              </div>
            </div>

            <div>
              <label htmlFor="review-comment" className="text-ink text-sm font-medium">
                Your review
              </label>
              <Textarea
                id="review-comment"
                value={comment}
                onChange={(event) => setComment(event.target.value)}
                placeholder="Tell other clients what stood out about your visit..."
                className="mt-2 min-h-32"
              />
            </div>

            {!auth.user && (
              <div>
                <label htmlFor="review-name" className="text-ink text-sm font-medium">
                  Your name <span className="text-ink-muted font-normal">(optional)</span>
                </label>
                <Input
                  id="review-name"
                  value={name}
                  onChange={(event) => setName(event.target.value)}
                  placeholder={DEFAULT_REVIEWER_NAME}
                  className="mt-2"
                />
              </div>
            )}

            <div className="flex flex-col gap-2 sm:flex-row">
              <Button type="button" variant="outline" className="sm:flex-1" onClick={() => setStep(1)}>
                Back
              </Button>
              <Button
                type="button"
                variant="accent"
                className="sm:flex-1"
                disabled={comment.trim() === '' || submitting}
                onClick={submit}
              >
                Submit Review <Send className="size-4" aria-hidden="true" />
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
