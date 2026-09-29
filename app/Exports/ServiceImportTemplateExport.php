<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ServiceImportTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new ServiceImportTemplateSheet,
            new ServiceImportInstructionsSheet,
        ];
    }
}
