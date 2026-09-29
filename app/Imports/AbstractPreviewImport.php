<?php

namespace App\Imports;

use App\Imports\Concerns\SanitizesFormulaInjection;
use App\Imports\Exceptions\ImportTooLargeException;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

/**
 * Brief Phase 6 §5: "the same importer pattern should be reusable for deals and customers later."
 * A concrete importer only has to implement `evaluateRow()` (decide create/update/error for one row)
 * and `bufferRow()`/`flushBuffer()` (persist its own `*_import_rows` table in chunks) — everything
 * else (chunked reading via PhpSpreadsheet so the whole file is never loaded into memory at once,
 * a hard row-count cap, formula-injection sanitization on every cell, running totals) lives here once.
 */
abstract class AbstractPreviewImport implements OnEachRow, WithChunkReading, WithHeadingRow
{
    use SanitizesFormulaInjection;

    protected int $rowNumber = 0;

    protected int $createCount = 0;

    protected int $updateCount = 0;

    protected int $errorCount = 0;

    public function __construct(
        protected int $maxRows = 2000,
        protected int $bufferSize = 100,
    ) {}

    public function chunkSize(): int
    {
        return 200;
    }

    public function headingRow(): int
    {
        return 1;
    }

    public function onRow(Row $row): void
    {
        $this->rowNumber++;

        if ($this->rowNumber > $this->maxRows) {
            throw new ImportTooLargeException("This file has more than the maximum {$this->maxRows} allowed rows.");
        }

        $raw = $row->toArray(null, false, true);

        // A fully blank trailing row (common at the end of a spreadsheet) isn't a real data row.
        if (count(array_filter($raw, fn ($value) => $value !== null && $value !== '')) === 0) {
            $this->rowNumber--;

            return;
        }

        $data = $this->sanitizeRow($raw);
        $result = $this->evaluateRow($data);

        match ($result['action']) {
            'create' => $this->createCount++,
            'update' => $this->updateCount++,
            default => $this->errorCount++,
        };

        $this->bufferRow($this->rowNumber, $result, $data);
    }

    public function finish(): void
    {
        $this->flushBuffer();
    }

    public function counts(): array
    {
        return [
            'total' => $this->rowNumber,
            'create' => $this->createCount,
            'update' => $this->updateCount,
            'error' => $this->errorCount,
        ];
    }

    /**
     * @return array{action: 'create'|'update'|'error', matched_id: int|null, errors: array|null}
     */
    abstract protected function evaluateRow(array $data): array;

    abstract protected function bufferRow(int $rowNumber, array $result, array $data): void;

    abstract protected function flushBuffer(): void;
}
