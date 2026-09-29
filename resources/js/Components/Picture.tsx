import * as React from 'react';

interface PictureProps extends React.ImgHTMLAttributes<HTMLImageElement> {
  src: string;
  /** Only rendered as a `<source>` when present — the server only sends this once a real `.webp`
   * sibling file actually exists on disk (see `SecureUploadService::webpSiblingPath()`), never a
   * guessed URL that might 404. */
  webpSrc?: string | null;
  alt: string;
}

/**
 * Phase 13 sub-step 2: WebP-with-fallback wrapper. `<picture>`'s `<source type="image/webp">` is
 * chosen by the browser purely on declared MIME support, before any request is made — a `<source>`
 * pointing at a file that doesn't actually exist would just break the image in WebP-capable browsers
 * rather than falling through to `<img>`, so `webpSrc` must already be a confirmed-real URL by the
 * time it reaches this component (never fabricated here).
 */
export function Picture({ src, webpSrc, alt, loading = 'lazy', decoding = 'async', ...imgProps }: PictureProps) {
  return (
    <picture>
      {webpSrc && <source srcSet={webpSrc} type="image/webp" />}
      <img src={src} alt={alt} loading={loading} decoding={decoding} {...imgProps} />
    </picture>
  );
}
