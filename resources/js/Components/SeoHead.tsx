import { Head, usePage } from '@inertiajs/react';

interface SharedSeoProps {
  [key: string]: unknown;
  seo?: {
    siteName?: string;
    defaultImage?: string | null;
    gtmId?: string | null;
    ga4Id?: string | null;
    gscVerification?: string | null;
  };
  ziggy?: {
    location?: string;
  };
  cspNonce?: string;
}

interface SeoHeadProps {
  title: string;
  description?: string | null;
  image?: string | null;
  type?: 'website' | 'article';
  /** One schema, several (a page can legitimately carry more than one JSON-LD block), or none. */
  jsonLd?: Record<string, unknown> | Record<string, unknown>[];
}

/**
 * Phase 13 sub-step 1: the one place Open Graph / Twitter Card tags get rendered, so every public
 * page produces the same shape instead of each hand-rolling a subset of them (before this, most
 * public pages set only `<title>` and, inconsistently, a description — no OG/Twitter tags existed
 * anywhere). Falls back to the real `seo.siteName`/`seo.defaultImage` shared Inertia props (backed by
 * the actual Phase 10 `business.name`/`business.logo_path` settings) rather than a hardcoded image
 * path — never points a crawler at a file that doesn't exist.
 *
 * The bare `title` (not a branded one) is passed to `<Head>` — `app.tsx`/`ssr.tsx` already apply a
 * global `${title} - ${appName}` template to every page (Phase 1 scaffold); appending the site name
 * here too would double it up (confirmed live: without this, `<title>` rendered
 * "X - Site Name - Site Name"). OG/Twitter `*:title` aren't covered by that template — a shared
 * social card has no templating context of its own — so those two intentionally use the branded form.
 *
 * Phase 13 sub-step 2 added GA4/GTM/GSC — all read from the shared `seo` prop (never a page prop), so
 * they render exactly once no matter which public page mounts `SeoHead` (it's already only ever used
 * once per page — see every `Public/*.tsx`), and every one is entirely absent, not a fabricated
 * placeholder id, until its matching Setting is actually configured. GA4/GTM's `<script>` tags carry
 * the real per-request CSP nonce (`cspNonce`, shared the same way `SecurityHeaders` already shares it
 * with Blade views) — this app's CSP has no `unsafe-inline`, so an un-nonced inline script here would
 * simply be silently blocked by the browser, not merely "less secure". GTM's `<noscript>` body-tag
 * fallback can't live here at all (Inertia's `<Head>` only manages `<head>`) — see `app.blade.php`.
 */
export function SeoHead({ title, description, image, type = 'website', jsonLd }: SeoHeadProps) {
  const { props } = usePage<SharedSeoProps>();
  const siteName = props.seo?.siteName ?? 'Looks Smart Beauty Salon';
  const url = props.ziggy?.location ?? '';
  const resolvedImage = image ?? props.seo?.defaultImage ?? null;
  const brandedTitle = title.includes(siteName) ? title : `${title} - ${siteName}`;
  const schemas = Array.isArray(jsonLd) ? jsonLd : jsonLd ? [jsonLd] : [];
  const nonce = props.cspNonce;
  const gtmId = props.seo?.gtmId;
  const ga4Id = props.seo?.ga4Id;
  const gscVerification = props.seo?.gscVerification;

  return (
    <Head title={title}>
      {description && <meta name="description" content={description} />}
      {url && <link rel="canonical" href={url} />}
      {gscVerification && <meta name="google-site-verification" content={gscVerification} />}

      <meta property="og:site_name" content={siteName} />
      <meta property="og:type" content={type} />
      <meta property="og:title" content={brandedTitle} />
      {description && <meta property="og:description" content={description} />}
      {url && <meta property="og:url" content={url} />}
      {resolvedImage && <meta property="og:image" content={resolvedImage} />}

      <meta name="twitter:card" content={resolvedImage ? 'summary_large_image' : 'summary'} />
      <meta name="twitter:title" content={brandedTitle} />
      {description && <meta name="twitter:description" content={description} />}
      {resolvedImage && <meta name="twitter:image" content={resolvedImage} />}

      {schemas.map((schema, index) => (
        <script key={index} type="application/ld+json">
          {JSON.stringify(schema)}
        </script>
      ))}

      {gtmId && (
        <script nonce={nonce}>
          {`(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','${gtmId}');`}
        </script>
      )}

      {ga4Id && (
        <>
          <script async nonce={nonce} src={`https://www.googletagmanager.com/gtag/js?id=${ga4Id}`} />
          <script nonce={nonce}>
            {`window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','${ga4Id}');`}
          </script>
        </>
      )}
    </Head>
  );
}
