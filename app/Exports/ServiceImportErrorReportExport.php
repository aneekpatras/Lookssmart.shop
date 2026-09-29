<?php

namespace App\Exports;

use App\Imports\Concerns\SanitizesFormulaInjection;
use App\Models\ServiceImportRow;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Downloadable report of exactly the rows that failed validation, with a Reason column — cell
 * values are re-sanitized against formula injection on the way OUT too (`data` was already
 * sanitized on the way in, but re-applying here means this export is safe even if a row's stored
 * `data` were ever populated by something other than the sanitizing importer).
 */
class ServiceImportErrorReportExport implements FromCollection, WithHeadings
{
    use SanitizesFormulaInjection;

    public function __construct(private readonly int $serviceImportId) {}

    public function headings(): array
    {
        return ['row_number', 'sku', 'name', 'category', 'duration_min', 'buffer_min', 'base_price', 'reason'];
    }

    public function collection(): Collection
    {
        return ServiceImportRow::query()
            ->where('service_import_id', $this->serviceImportId)
            ->where('action', 'error')
            ->orderBy('row_number')
            ->get()
            ->map(function (ServiceImportRow $row) {
                $data = $this->sanitizeRow($row->data ?? []);

                return $this->sanitizeRow([
                    $row->row_number,
                    $data['sku'] ?? '',
                    $data['name'] ?? '',
                    $data['category'] ?? '',
                    $data['duration_min'] ?? '',
                    $data['buffer_min'] ?? '',
                    $data['base_price'] ?? '',
                    implode('; ', $row->errors ?? []),
                ]);
            });
    }
}
