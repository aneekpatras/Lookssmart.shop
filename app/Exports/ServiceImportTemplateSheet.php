<?php

namespace App\Exports;

use App\Models\ServiceCategory;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ServiceImportTemplateSheet implements FromArray, WithEvents, WithHeadings, WithTitle
{
    public function headings(): array
    {
        return ['sku', 'name', 'category', 'duration_min', 'buffer_min', 'base_price', 'is_active', 'is_featured', 'description', 'image_url'];
    }

    public function array(): array
    {
        return [
            ['CUT-001', 'Signature Haircut', 'Hair', 45, 10, 65.00, 1, 1, 'A full consultation, wash, cut, and style.', 'https://images.unsplash.com/photo-example'],
        ];
    }

    public function title(): string
    {
        return 'Template';
    }

    /**
     * A dropdown data-validation list on the `category` column (C), sourced from every real
     * category name currently in the database — steers the importing admin toward a name that will
     * actually match on upload instead of a guess.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $categories = ServiceCategory::query()->orderBy('name')->pluck('name');
                $list = '"' . $categories->implode(',') . '"';

                foreach (range(2, 500) as $row) {
                    $cell = $event->sheet->getCell("C{$row}");
                    $validation = $cell->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_WARNING);
                    $validation->setAllowBlank(true);
                    $validation->setShowDropDown(true);
                    $validation->setFormula1($list);
                }
            },
        ];
    }
}
