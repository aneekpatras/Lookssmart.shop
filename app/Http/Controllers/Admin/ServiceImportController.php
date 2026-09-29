<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ServiceImportErrorReportExport;
use App\Exports\ServiceImportTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UploadServiceImportRequest;
use App\Jobs\ProcessServiceImportJob;
use App\Models\Service;
use App\Models\ServiceImport;
use App\Models\ServiceImportRow;
use App\Services\HtmlSanitizerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ServiceImportController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', ServiceImport::class);

        $imports = ServiceImport::query()
            ->with('creator:id,name')
            ->latest()
            ->paginate(10);

        return Inertia::render('Admin/ServiceImport', [
            'imports' => $imports->through(fn (ServiceImport $import) => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'status' => $import->status,
                'total_rows' => $import->total_rows,
                'create_count' => $import->create_count,
                'update_count' => $import->update_count,
                'error_count' => $import->error_count,
                'created_by' => $import->creator?->name,
                'created_at' => $import->created_at?->toIso8601String(),
            ]),
        ]);
    }

    public function template(): BinaryFileResponse
    {
        $this->authorize('create', ServiceImport::class);

        return Excel::download(new ServiceImportTemplateExport, 'services-import-template.xlsx');
    }

    public function store(UploadServiceImportRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $storedPath = $file->store('service-imports', 'local');

        $import = ServiceImport::create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $storedPath,
            'status' => 'queued',
            'created_by' => $request->user()->id,
        ]);

        ProcessServiceImportJob::dispatch($import->id);

        return redirect()->route('admin.services.import.show', $import)
            ->with('success', 'File uploaded — processing now.');
    }

    public function show(ServiceImport $import): Response
    {
        $this->authorize('view', $import);

        $rows = $import->rows()
            ->orderBy('row_number')
            ->paginate(50);

        return Inertia::render('Admin/ServiceImportShow', [
            'import' => [
                'id' => $import->id,
                'original_filename' => $import->original_filename,
                'status' => $import->status,
                'total_rows' => $import->total_rows,
                'create_count' => $import->create_count,
                'update_count' => $import->update_count,
                'error_count' => $import->error_count,
                'failure_reason' => $import->failure_reason,
                'committed_at' => $import->committed_at?->toIso8601String(),
            ],
            'rows' => $rows->through(fn (ServiceImportRow $row) => [
                'id' => $row->id,
                'row_number' => $row->row_number,
                'action' => $row->action,
                'data' => $row->data,
                'errors' => $row->errors,
            ]),
        ]);
    }

    public function commit(ServiceImport $import, HtmlSanitizerService $sanitizer): RedirectResponse
    {
        $this->authorize('update', $import);

        if ($import->status !== 'previewed') {
            return back()->withErrors(['import' => 'This import is not ready to commit.']);
        }

        $import->update(['status' => 'committing']);

        DB::transaction(function () use ($import, $sanitizer) {
            $import->rows()
                ->whereIn('action', ['create', 'update'])
                ->orderBy('row_number')
                ->chunkById(200, function ($rows) use ($sanitizer) {
                    foreach ($rows as $row) {
                        $this->applyRow($row, $sanitizer);
                    }
                });

            $import->update(['status' => 'committed', 'committed_at' => now()]);
        });

        activity('catalog')
            ->causedBy(request()->user())
            ->performedOn($import)
            ->withProperties([
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created' => $import->create_count,
                'updated' => $import->update_count,
                'skipped_errors' => $import->error_count,
            ])
            ->log('service import committed');

        return redirect()->route('admin.services.import.show', $import)
            ->with('success', "Import committed: {$import->create_count} created, {$import->update_count} updated.");
    }

    public function errorReport(ServiceImport $import): BinaryFileResponse
    {
        $this->authorize('view', $import);

        return Excel::download(
            new ServiceImportErrorReportExport($import->id),
            "service-import-{$import->id}-errors.xlsx",
        );
    }

    private function applyRow(ServiceImportRow $row, HtmlSanitizerService $sanitizer): void
    {
        $data = $row->data;

        $categoryId = DB::table('service_categories')
            ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $data['category']))])
            ->orWhereRaw('LOWER(slug) = ?', [Str::slug((string) $data['category'])])
            ->value('id');

        if (! $categoryId) {
            return;
        }

        $attributes = [
            'service_category_id' => $categoryId,
            'sku' => $data['sku'] ?: null,
            'name' => $data['name'],
            'duration_min' => (int) $data['duration_min'],
            'buffer_min' => $data['buffer_min'] !== null && $data['buffer_min'] !== '' ? (int) $data['buffer_min'] : 0,
            'base_price' => (float) $data['base_price'],
            'is_active' => (bool) ($data['is_active'] ?? true),
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'description' => ! empty($data['description']) ? $sanitizer->clean((string) $data['description']) : null,
        ];

        // Only touch the image on an update when the sheet actually has a URL in that cell — an
        // admin re-importing a file exported before this column existed (or one where they left it
        // blank on purpose) must not wipe out a photo already uploaded directly in the admin panel.
        if (! empty($data['image_url'])) {
            $attributes['stock_image_url'] = $data['image_url'];
        }

        if ($row->matched_service_id) {
            Service::whereKey($row->matched_service_id)->update($attributes);

            return;
        }

        Service::create([
            ...$attributes,
            'slug' => Str::slug((string) $data['name']),
        ]);
    }
}
