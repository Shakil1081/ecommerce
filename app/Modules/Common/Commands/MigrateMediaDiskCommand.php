<?php

declare(strict_types=1);

namespace App\Modules\Common\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateMediaDiskCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'media:migrate-disk 
                            {--from=local : The source filesystem disk name}
                            {--to=media : The destination filesystem disk name}';

    /**
     * The console command description.
     */
    protected $description = 'Migrate media library files from one storage disk to another without broken URLs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromDiskName = (string) $this->option('from');
        $toDiskName = (string) $this->option('to');

        $this->info("Preparing media migration from disk [{$fromDiskName}] to [{$toDiskName}]...");

        try {
            $fromDisk = Storage::disk($fromDiskName);
            $toDisk = Storage::disk($toDiskName);
        } catch (Throwable $e) {
            $this->error("Failed to access storage disks: {$e->getMessage()}");

            return self::FAILURE;
        }

        $allFiles = array_values(array_filter($fromDisk->allFiles(), function (string $file): bool {
            return ! str_starts_with(basename($file), '.');
        }));
        $totalFiles = count($allFiles);

        if ($totalFiles === 0) {
            $this->warn("No media files found on source disk [{$fromDiskName}]. Nothing to migrate.");

            return self::SUCCESS;
        }

        $this->info("Found {$totalFiles} files to migrate.");
        $progressBar = $this->output->createProgressBar($totalFiles);
        $progressBar->start();

        $migratedCount = 0;
        $failedCount = 0;

        foreach ($allFiles as $file) {
            try {
                $stream = $fromDisk->readStream($file);
                if ($stream !== false) {
                    $toDisk->put($file, $stream);
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    $migratedCount++;
                } else {
                    $failedCount++;
                }
            } catch (Throwable $e) {
                $failedCount++;
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        $this->info("Migration completed! Successfully copied: {$migratedCount} files.");
        if ($failedCount > 0) {
            $this->warn("Failed transfers: {$failedCount} files.");
        }

        return self::SUCCESS;
    }
}
