<?php

namespace App\Support;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Renders a QR code as a self-contained `data:` URI.
 *
 * Three deliberate choices:
 *
 * - **`bacon/bacon-qr-code`, which is already installed** as a `laravel/fortify` dependency (it
 *   renders the TOTP enrolment QR). No new package, and no third-party QR *service* — an external
 *   image API would be blocked outright by this app's `img-src 'self' data: https://images.unsplash.com`
 *   CSP, and would leak the encoded contents to whoever runs it.
 * - **A `data:` URI rather than inline SVG markup**, so the frontend can use a plain `<img src>`.
 *   Injecting the SVG string would mean `dangerouslySetInnerHTML`, which this project's conventions
 *   prohibit outside server-side-purified content — avoided entirely rather than argued about.
 * - **Base64-encoded**, not percent-encoded: bacon's SVG contains quotes and `#` characters that
 *   break a raw `data:image/svg+xml,…` URI in some browsers.
 */
class QrCode
{
    /**
     * @param int $size Rendered edge length in pixels.
     * @param int $margin Quiet-zone width in modules, kept deliberately small because the white
     *                    card the code sits on supplies the rest of the quiet zone visually. Raise
     *                    it if the code is ever placed against a busy or coloured background, where
     *                    scanners need the full 4-module border to lock on.
     */
    public static function svgDataUri(string $text, int $size = 320, int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(
                size: $size,
                margin: $margin,
                // Pure black on white. Tinting a QR to match a palette reduces the contrast ratio
                // scanners rely on, and this one sits on a white card anyway.
                fill: Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(0, 0, 0)),
            ),
            new SvgImageBackEnd,
        );

        $svg = (new Writer($renderer))->writeString($text);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
