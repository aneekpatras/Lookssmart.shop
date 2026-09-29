<?php

use App\Support\CurrencyFormatter;

it('formats a whole rupee amount with no decimals and a thousands separator by default', function () {
    expect(CurrencyFormatter::format(2500))->toBe('Rs. 2,500')
        ->and(CurrencyFormatter::format('2500.00'))->toBe('Rs. 2,500')
        ->and(CurrencyFormatter::format(15000.75))->toBe('Rs. 15,001');
});

it('keeps decimal precision when the caller explicitly asks for it', function () {
    expect(CurrencyFormatter::format(194.286, 2))->toBe('Rs. 194.29');
});
