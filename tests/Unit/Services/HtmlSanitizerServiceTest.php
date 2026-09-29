<?php

use App\Services\HtmlSanitizerService;

it('strips script tags and inline event handlers from rich text', function () {
    $dirty = '<p>Hello</p><script>alert(1)</script><img src="x.jpg" onerror="alert(2)">';

    $clean = app(HtmlSanitizerService::class)->clean($dirty, 'blog');

    expect($clean)->not->toContain('<script')
        ->and($clean)->not->toContain('onerror')
        ->and($clean)->toContain('Hello');
});

it('strips disallowed tags on the default profile but keeps a plain allowed tag', function () {
    $dirty = '<div>note</div><iframe src="https://evil.example"></iframe>';

    $clean = app(HtmlSanitizerService::class)->clean($dirty);

    expect($clean)->not->toContain('<iframe')
        ->and($clean)->toContain('note');
});
