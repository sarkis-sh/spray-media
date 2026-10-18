<?php

namespace SprayMedia\Console;

use Illuminate\Console\Command;
use SprayMedia\Application\DirectUploadManager;

/**
 * Clears direct uploads that were never completed: files under
 * base_dir/pending_dir older than the token's lifetime. Schedule it, e.g.
 * hourly: Schedule::command('spray-media:prune-pending')->hourly();
 */
class PrunePendingUploadsCommand extends Command
{
    protected $signature = 'spray-media:prune-pending
                            {--minutes= : Delete files older than this many minutes (default: the upload token\'s lifetime)}';

    protected $description = 'Delete direct uploads that were never completed';

    public function handle(DirectUploadManager $uploads): int
    {
        $minutes = $this->option('minutes');
        $deleted = $uploads->prunePending($minutes !== null ? (int) $minutes : null);

        $this->info("Deleted {$deleted} pending upload(s).");

        return self::SUCCESS;
    }
}
