<?php

namespace App\Exports;

use App\Imports\Concerns\SanitizesFormulaInjection;
use App\Models\Service;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Full catalog export — same column set as ServiceImportTemplateSheet (sku, name, category,
 * duration_min, buffer_min, base_price, is_active, is_featured, description) plus `image_url`, so
 * this file round-trips through the importer unchanged: export, edit in Excel (including swapping
 * `image_url` to a different remote image), re-upload, and every row matches its existing service by
 * `sku`/`slug` and becomes an "update" rather than a duplicate "create" — see
 * `ServicesPreviewImport::evaluateRow()` and `ServiceImportController::applyRow()`.
 *
 * `image_url` here is the resolved DISPLAY image (uploaded medialibrary file, if any, else the
 * `stock_image_url` fallback — same precedence as everywhere else, see
 * `ServiceController::displayImageUrl()`), not the raw `stock_image_url` column. A service whose
 * image is a real upload still exports a usable, absolute URL rather than a blank cell; re-importing
 * that URL back in sets it as the new `stock_image_url` (the importer has no way to "upload" a file
 * from a URL, so on re-import it always lands as the remote fallback, not a new medialibrary file).
 *
 * Cell values are sanitized against formula injection on the way out, same as
 * ServiceImportErrorReportExport — `description` in particular is free text an admin typed and could
 * start with `=`/`+`/`-`/`@`.
 */
class ServicesExport implements FromCollection, WithHeadings, WithTitle
{
    use SanitizesFormulaInjection;

    public function headings(): array
    {
        return ['sku', 'name', 'category', 'duration_min', 'buffer_min', 'base_price', 'is_active', 'is_featured', 'description', 'image_url'];
    }

    public function collection(): Collection
    {
        return Service::query()
            ->with('category:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Service $service) => $this->sanitizeRow([
                $service->sku ?? '',
                $service->name,
                $service->category?->name ?? '',
                $service->duration_min,
                $service->buffer_min,
                (string) $service->base_price,
                $service->is_active ? 1 : 0,
                $service->is_featured ? 1 : 0,
                $service->description ?? '',
                $service->getFirstMediaUrl('image') ?: ($service->stock_image_url ?? ''),
            ]));
    }

    public function title(): string
    {
        return 'Services';
    }
}
