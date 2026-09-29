<?php

namespace App\Imports\Concerns;

/**
 * Brief Phase 6 §5 item: any cell value starting with `= + - @` is a potential formula-injection
 * payload if the data is ever re-opened in a spreadsheet application (e.g. an admin exporting the
 * error report and opening it in Excel) — prefixing with a leading apostrophe forces spreadsheet
 * software to treat it as literal text instead of evaluating it as a formula. Applied on BOTH the
 * read side (parsing an uploaded file) and the write side (generating the error-report export), since
 * either direction could otherwise carry a payload into a spreadsheet application.
 */
trait SanitizesFormulaInjection
{
    protected function sanitizeCell(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return in_array($value[0], ['=', '+', '-', '@'], true) ? "'" . $value : $value;
    }

    protected function sanitizeRow(array $row): array
    {
        return array_map(fn ($value) => $this->sanitizeCell($value), $row);
    }
}
