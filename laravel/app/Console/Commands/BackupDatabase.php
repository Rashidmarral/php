<?php

namespace App\Console\Commands;

use App\Support\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * Automatic daily database backup — the same mysqldump/sqlite-copy logic the admin
 * "Create Backup" button uses (App\Support\DatabaseBackup), saved to the same
 * storage/app/backups directory but with an "auto-" filename prefix (vs. "backup-" for a
 * manually triggered one), so retention cleanup below only ever prunes backups THIS
 * command created and never a manual one an admin deliberately made.
 *
 * Kept as its own command rather than a block in RunDailyTasks: backups aren't a
 * per-company reminder/renewal action shaped like RunDailyTasks' other blocks, mysqldump
 * can run for minutes against a large database, and isolating it means a backup failure
 * (missing mysqldump binary, disk full) can never block the unrelated subscription/
 * reminder/invoice work in RunDailyTasks, and a slow mysqldump can never delay those
 * time-sensitive reminders. Scheduled separately in routes/console.php.
 *
 * Safe to run more than once a day: each run just creates another timestamped backup
 * (harmless, unlike re-charging a subscription), and retention cleanup runs after every
 * successful backup, so disk usage never grows unbounded regardless of how often it runs.
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-database';

    protected $description = 'Create an automatic database backup and prune automatic backups beyond the retention count';

    public function handle(): int
    {
        $driver = config('database.default');
        $result = DatabaseBackup::create($driver, DatabaseBackup::AUTO_PREFIX);

        if (!$result['success']) {
            $detail = isset($result['message']) ? " ({$result['message']})" : '';
            $this->error("Automatic backup failed: {$result['error']}{$detail}");
            return self::FAILURE;
        }

        $this->info("Automatic backup created: {$result['filename']}");

        $pruned = $result['pruned'] ?? 0;
        if ($pruned > 0) {
            $this->info("Pruned {$pruned} old automatic backup(s) beyond the retention count of " . DatabaseBackup::AUTO_RETENTION_COUNT . '.');
        }

        return self::SUCCESS;
    }
}
