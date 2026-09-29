import * as React from 'react';
import { motion } from 'framer-motion';

import { BeforeAfterSlider } from '@/Components/BeforeAfterSlider';
import { Lightbox } from '@/Components/Lightbox';
import { Picture } from '@/Components/Picture';
import { SeoHead } from '@/Components/SeoHead';
import PublicLayout from '@/Layouts/PublicLayout';

interface GalleryImage {
  id: number;
  image_path: string;
  image_path_webp: string | null;
  caption: string | null;
  is_before_after: boolean;
  pair_image_path: string | null;
  pair_image_path_webp: string | null;
}

interface GallerySection {
  id: number | string;
  name: string;
  subtitle: string | null;
  images: GalleryImage[];
}

interface GalleryPageProps {
  sections: GallerySection[];
}

function GalleryImageTile({
  image,
  onClick,
}: {
  image: GalleryImage;
  onClick: () => void;
}) {
  return (
    <button
      type="button"
      onClick={onClick}
      aria-label={image.caption ? `View "${image.caption}" in fullscreen` : 'View image in fullscreen'}
      className="group border-border-soft bg-surface relative aspect-square overflow-hidden rounded-lg border text-left"
    >
      <Picture
        src={image.image_path}
        webpSrc={image.image_path_webp}
        alt={image.caption || ''}
        className="size-full object-cover transition-transform duration-300 group-hover:scale-105"
      />
      <div className="bg-ink/0 group-hover:bg-ink/40 group-focus-within:bg-ink/40 absolute inset-0 flex items-end p-3 transition-colors duration-300">
        {image.caption && (
          <span className="text-ivory text-xs opacity-0 transition-opacity duration-300 group-hover:opacity-100 group-focus-within:opacity-100">
            {image.caption}
          </span>
        )}
      </div>
    </button>
  );
}

function CategorySection({
  section,
  isFirst,
  imageIndexById,
  onImageClick,
}: {
  section: GallerySection;
  isFirst: boolean;
  imageIndexById: Map<number, number>;
  onImageClick: (index: number) => void;
}) {
  const beforeAfterImages = section.images.filter((image) => image.is_before_after);
  const regularImages = section.images.filter((image) => !image.is_before_after);

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: '-80px' }}
      transition={{ duration: 0.5 }}
      className={isFirst ? '' : 'border-border-soft border-t pt-16'}
    >
      <h2 className="font-display text-ink text-3xl font-medium">{section.name}</h2>
      {section.subtitle && <p className="text-ink-muted mt-2 max-w-2xl">{section.subtitle}</p>}

      {beforeAfterImages.length > 0 && (
        <div className="mt-8 space-y-8">
          <h3 className="text-ink-muted text-sm font-semibold tracking-[0.1em] uppercase">
            Transformations
          </h3>
          <div className="grid gap-8 md:grid-cols-2">
            {beforeAfterImages.map((image) => (
              <BeforeAfterSlider
                key={image.id}
                beforeImage={image.image_path}
                beforeImageWebp={image.image_path_webp}
                afterImage={image.pair_image_path || image.image_path}
                afterImageWebp={image.pair_image_path ? image.pair_image_path_webp : image.image_path_webp}
                beforeLabel="Before"
                afterLabel="After"
                caption={image.caption || undefined}
              />
            ))}
          </div>
        </div>
      )}

      {regularImages.length > 0 && (
        <div className="mt-8 grid grid-cols-2 gap-4 md:grid-cols-4">
          {regularImages.map((image) => (
            <GalleryImageTile
              key={image.id}
              image={image}
              onClick={() => onImageClick(imageIndexById.get(image.id) ?? 0)}
            />
          ))}
        </div>
      )}
    </motion.div>
  );
}

export default function Gallery({ sections }: GalleryPageProps) {
  const [lightboxOpen, setLightboxOpen] = React.useState(false);
  const [lightboxIndex, setLightboxIndex] = React.useState(0);

  // One shared lightbox spans every category section — regular (non before/after) images across
  // ALL sections, in section order, so Next/Previous can move seamlessly from one category's photos
  // into the next rather than being scoped to whichever section the customer clicked into.
  const allRegularImages = React.useMemo(
    () => sections.flatMap((section) => section.images.filter((image) => !image.is_before_after)),
    [sections],
  );

  const imageIndexById = React.useMemo(() => {
    const map = new Map<number, number>();
    allRegularImages.forEach((image, index) => map.set(image.id, index));
    return map;
  }, [allRegularImages]);

  function handleImageClick(index: number) {
    setLightboxIndex(index);
    setLightboxOpen(true);
  }

  return (
    <>
      <SeoHead
        title="Gallery"
        description="Real results from our hair, skin, bridal, and beauty work at Looks Smart."
      />

      <section className="bg-ink px-6 py-16 text-ivory lg:px-10">
        <div className="mx-auto max-w-6xl">
          <p className="text-accent-300 text-sm font-semibold uppercase tracking-[0.18em]">
            Portfolio
          </p>
          <h1 className="mt-3 text-5xl font-medium sm:text-6xl">Our Salon Gallery</h1>
          <p className="mt-5 max-w-xl text-ivory/70">
            Browse real transformations and explore the artistry of our beauty experts, organized by
            the work we&apos;re proudest of.
          </p>
        </div>
      </section>

      <section className="px-6 py-16 lg:px-10">
        <div className="mx-auto max-w-6xl">
          {sections.length === 0 ? (
            <div className="py-20 text-center">
              <p className="text-ink-muted">No gallery photos are available yet.</p>
            </div>
          ) : (
            <div className="space-y-16">
              {sections.map((section, index) => (
                <CategorySection
                  key={section.id}
                  section={section}
                  isFirst={index === 0}
                  imageIndexById={imageIndexById}
                  onImageClick={handleImageClick}
                />
              ))}
            </div>
          )}
        </div>
      </section>

      <Lightbox
        images={allRegularImages}
        initialIndex={lightboxIndex}
        isOpen={lightboxOpen}
        onClose={() => setLightboxOpen(false)}
      />
    </>
  );
}

Gallery.layout = (page: React.ReactNode) => <PublicLayout>{page}</PublicLayout>;
