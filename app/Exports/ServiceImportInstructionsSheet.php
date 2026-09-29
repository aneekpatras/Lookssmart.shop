<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class ServiceImportInstructionsSheet implements FromArray, WithTitle
{
    public function array(): array
    {
        return [
            ['Looks Smart Beauty Salon — Services Import Instructions'],
            [''],
            ['1. Fill in one row per service on the "Template" sheet. Do not change the column headers.'],
            ['2. sku (optional) — if a service with this SKU already exists, its row will be UPDATED, not duplicated.'],
            ['3. name (required) — if sku is blank, a service with the same auto-generated slug will be UPDATED instead of duplicated.'],
            ['4. category (required) — must match an existing category name exactly; use the dropdown on the "Template" sheet.'],
            ['5. duration_min (required) — whole minutes, e.g. 45.'],
            ['6. buffer_min (optional) — whole minutes of cleanup/gap time after the service, e.g. 10. Leave blank for 0.'],
            ['7. base_price (required) — a number, e.g. 65.00. No currency symbol.'],
            ['8. is_active / is_featured (optional) — 1 for yes, 0 or blank for no.'],
            ['9. description (optional) — plain text; rich formatting is not preserved.'],
            ['10. image_url (optional) — a full https:// link to an image. Used as a fallback picture'],
            ['    only when the service has no photo uploaded directly in the admin panel — an uploaded'],
            ['    photo always takes priority over this link.'],
            [''],
            ['After uploading, you will see a preview of what will be created/updated, and any rows with'],
            ['errors, before anything is saved. Nothing is written to the catalog until you confirm.'],
            ['A maximum of 2,000 rows can be imported in a single file.'],
        ];
    }

    public function title(): string
    {
        return 'Instructions';
    }
}
