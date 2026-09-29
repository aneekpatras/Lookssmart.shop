<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Phase 14: closes the "backup creation was verified, but a real restore never was" gap flagged
 * since Phase 4's audit (§9/§10 #19, DEPLOYMENT.md §7). `spatie/laravel-backup` (installed since
 * Phase 4) has no restore command at all — confirmed by reading its `src/Commands/` directory —
 * restoring is deliberately left to the operator. This command makes that a real, repeatable,
 * automated rehearsal rather than a one-off manual exercise: it extracts the real database dump
 * from the most recent real backup zip, imports it into a disposable SCRATCH database (never the
 * real one), compares row counts against the real database on a handful of key tables, then drops
 * the scratch database and deletes the extracted dump. A clean run proves the encrypted backup
 * archive genuinely contains a working, importable dump — not just that `backup:run` exits 0.
 */
class BackupRestoreRehearsalCommand extends Command
{
    protected $signature = 'backup:restore-rehearsal
        {--fresh : Run a fresh database-only backup first instead of using the most recent existing one}
        {--keep : Keep the scratch database and extracted dump file for manual inspection instead of cleaning up}';

    protected $description = 'Restores the most recent real backup into a disposable scratch database and verifies it — proves backups are actually restorable, not just creatable. Never touches the real database.';

    private string $scratchDatabase;

    public function handle(): int
    {
        $connection = config('database.default');
        $realDatabase = config("database.connections.{$connection}.database");
        $this->scratchDatabase = "{$realDatabase}_restore_rehearsal";

        if ($this->option('fresh')) {
            $this->info('Running a fresh database-only backup first...');
            $this->call('backup:run', ['--only-db' => true]);
        }

        $zipPath = $this->findMostRecentBackup();
        if (! $zipPath) {
            $this->error('No backup archive found. Run `php artisan backup:run` first, or pass --fresh.');

            return self::FAILURE;
        }
        $this->info("Using backup archive: {$zipPath}");

        $dumpPath = $this->extractDatabaseDump($zipPath);
        $this->info("Extracted database dump to: {$dumpPath}");

        try {
            $this->createScratchDatabase();
            $this->importDump($dumpPath);
            $ok = $this->verifyRestoredData($realDatabase);
        } finally {
            if (! $this->option('keep')) {
                $this->dropScratchDatabase();
                @unlink($dumpPath);
            } else {
                $this->warn("--keep passed: scratch database '{$this->scratchDatabase}' and {$dumpPath} were left in place.");
            }
        }

        if ($ok) {
            $this->info('PASS: the backup archive restores cleanly and its row counts match the real database.');

            return self::SUCCESS;
        }

        $this->error('FAIL: the restored data did not match — see above.');

        return self::FAILURE;
    }

    private function findMostRecentBackup(): ?string
    {
        $disk = Storage::disk(config('backup.backup.destination.disks')[0]);
        $appName = config('backup.backup.name');

        $files = collect($disk->allFiles($appName))
            ->filter(fn (string $file) => str_ends_with($file, '.zip'))
            ->sortByDesc(fn (string $file) => $disk->lastModified($file));

        $relative = $files->first();

        return $relative ? $disk->path($relative) : null;
    }

    private function extractDatabaseDump(string $zipPath): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("Could not open backup archive: {$zipPath}");
        }

        $dumpEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'db-dumps') && str_ends_with($name, '.sql')) {
                $dumpEntry = $name;
                break;
            }
        }

        if (! $dumpEntry) {
            $zip->close();
            throw new RuntimeException("No database dump found inside {$zipPath} (expected a db-dumps/*.sql entry).");
        }

        $password = config('backup.backup.password');
        if ($password) {
            $zip->setPassword($password);
        }

        $extractDir = storage_path('app/backup-temp/restore-rehearsal');
        if (! is_dir($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        if (! $zip->extractTo($extractDir, $dumpEntry)) {
            $zip->close();
            throw new RuntimeException('Failed to extract the database dump — wrong archive password?');
        }
        $zip->close();

        return $extractDir . DIRECTORY_SEPARATOR . $dumpEntry;
    }

    private function mysqlBinary(string $name): string
    {
        $binPath = config('database.connections.mysql.dump.dump_binary_path')
            ?: env('DB_DUMP_BINARY_PATH', '');

        return $binPath ? rtrim($binPath, '\\/') . DIRECTORY_SEPARATOR . $name : $name;
    }

    private function runMysqlCommand(array $arguments, ?string $input = null): void
    {
        $process = new Process($arguments);
        if ($input !== null) {
            $process->setInput($input);
        }
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Command failed: ' . implode(' ', $arguments) . "\n" . $process->getErrorOutput());
        }
    }

    private function connectionCredentials(): array
    {
        $config = config('database.connections.mysql');

        return [
            'host' => $config['host'],
            'port' => (string) $config['port'],
            'username' => $config['username'],
            'password' => $config['password'],
        ];
    }

    private function createScratchDatabase(): void
    {
        $creds = $this->connectionCredentials();

        $this->runMysqlCommand(array_filter([
            $this->mysqlBinary('mysql'),
            '--host=' . $creds['host'],
            '--port=' . $creds['port'],
            '--user=' . $creds['username'],
            $creds['password'] ? '--password=' . $creds['password'] : null,
            '--execute=DROP DATABASE IF EXISTS `' . $this->scratchDatabase . '`; CREATE DATABASE `' . $this->scratchDatabase . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
        ]));
    }

    private function importDump(string $dumpPath): void
    {
        $creds = $this->connectionCredentials();

        $this->runMysqlCommand(array_filter([
            $this->mysqlBinary('mysql'),
            '--host=' . $creds['host'],
            '--port=' . $creds['port'],
            '--user=' . $creds['username'],
            $creds['password'] ? '--password=' . $creds['password'] : null,
            $this->scratchDatabase,
        ]), file_get_contents($dumpPath));
    }

    /**
     * Compares row counts on a handful of representative tables (spanning the schema's oldest and
     * newest phases) between the restored scratch database and the real one — not just "the import
     * command exited 0", which would still pass on an empty or truncated dump.
     */
    private function verifyRestoredData(string $realDatabase): bool
    {
        $tables = ['users', 'services', 'bookings', 'staff', 'settings'];
        $ok = true;

        foreach ($tables as $table) {
            $realCount = DB::table("{$realDatabase}.{$table}")->count();
            $restoredCount = DB::table("{$this->scratchDatabase}.{$table}")->count();

            $match = $realCount === $restoredCount;
            $ok = $ok && $match;

            $this->line(sprintf(
                '  %s %-10s real=%-6d restored=%-6d',
                $match ? '✅' : '❌',
                $table,
                $realCount,
                $restoredCount,
            ));
        }

        return $ok;
    }

    private function dropScratchDatabase(): void
    {
        $creds = $this->connectionCredentials();

        $this->runMysqlCommand(array_filter([
            $this->mysqlBinary('mysql'),
            '--host=' . $creds['host'],
            '--port=' . $creds['port'],
            '--user=' . $creds['username'],
            $creds['password'] ? '--password=' . $creds['password'] : null,
            '--execute=DROP DATABASE IF EXISTS `' . $this->scratchDatabase . '`;',
        ]));
    }
}
