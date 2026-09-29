<?php

namespace App\Imports;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceImportRow;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Expected columns (see ServiceImportTemplateExport): sku, name, category, duration_min, buffer_min,
 * base_price, is_active, is_featured, description, image_url. Matches an existing service on `sku` first, then
 * `slug` (derived from `name` if no explicit slug column) — never blind-overwrites: a matched row
 * becomes an "update" of exactly that row, an unmatched row becomes a "create", and any row that
 * fails validation becomes an "error" that is never written to the database.
 */
class ServicesPreviewImport extends AbstractPreviewImport
{
    protected array $buffer = [];

    public function __construct(
        private readonly int $serviceImportId,
        int $maxRows = 2000,
        int $bufferSize = 100,
    ) {
        parent::__construct($maxRows, $bufferSize);
    }

    protected function evaluateRow(array $data): array
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'duration_min' => ['required', 'integer', 'min:1'],
            'buffer_min' => ['nullable', 'integer', 'min:0'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'sku' => ['nullable', 'string', 'max:255'],
            'image_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return ['action' => 'error', 'matched_id' => null, 'errors' => $validator->errors()->all()];
        }

        $category = ServiceCategory::whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $data['category']))])
            ->orWhereRaw('LOWER(slug) = ?', [Str::slug((string) $data['category'])])
            ->first();

        if (! $category) {
            return ['action' => 'error', 'matched_id' => null, 'errors' => ["Category \"{$data['category']}\" does not exist."]];
        }

        $sku = ! empty($data['sku']) ? trim((string) $data['sku']) : null;
        $slug = Str::slug((string) $data['name']);

        $existing = null;
        if ($sku) {
            $existing = Service::where('sku', $sku)->first();
        }
        $existing ??= Service::where('slug', $slug)->first();

        return [
            'action' => $existing ? 'update' : 'create',
            'matched_id' => $existing?->id,
            'errors' => null,
        ];
    }

    protected function bufferRow(int $rowNumber, array $result, array $data): void
    {
        $this->buffer[] = [
            'service_import_id' => $this->serviceImportId,
            'row_number' => $rowNumber,
            'action' => $result['action'],
            'matched_service_id' => $result['matched_id'],
            'data' => json_encode($data),
            'errors' => $result['errors'] ? json_encode($result['errors']) : null,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (count($this->buffer) >= $this->bufferSize) {
            $this->flushBuffer();
        }
    }

    protected function flushBuffer(): void
    {
        if ($this->buffer !== []) {
            ServiceImportRow::insert($this->buffer);
            $this->buffer = [];
        }
    }
}
