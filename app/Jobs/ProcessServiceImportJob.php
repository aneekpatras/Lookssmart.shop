<?php

namespace App\Jobs;

use App\Imports\Exceptions\ImportTooLargeException;
use App\Imports\ServicesPreviewImport;
use App\Models\ServiceImport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

/**
 * Runs off the queue (Brief Phase 6 §5: "Upload → queued job → row-by-row validation → dry-run
 * preview"). Parses the uploaded file with chunked reading (memory-safe for large files — the whole
 * spreadsheet is never loaded into memory at once) and persists one `ServiceImportRow` per data row,
 * each already tagged create/update/error — the preview screen reads straight from that table rather
 * than re-parsing the file.
 */
class ProcessServiceImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $serviceImportId) {}

    public function handle(): void
    {
        $import = ServiceImport::findOrFail($this->serviceImportId);
        $import->update(['status' => 'processing']);

        $importer = new ServicesPreviewImport($import->id);

        try {
            Excel::import($importer, Storage::disk('local')->path($import->file_path));
            $importer->finish();

            $import->update([
                ...$importer->counts() === [] ? [] : [
                    'total_rows' => $importer->counts()['total'],
                    'create_count' => $importer->counts()['create'],
                    'update_count' => $importer->counts()['update'],
                    'error_count' => $importer->counts()['error'],
                ],
                'status' => 'previewed',
                'previewed_at' => now(),
            ]);
        } catch (ImportTooLargeException $e) {
            $import->update(['status' => 'failed', 'failure_reason' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $import->update(['status' => 'failed', 'failure_reason' => 'The file could not be read. Confirm it matches the template format.']);
        }
    }
}
