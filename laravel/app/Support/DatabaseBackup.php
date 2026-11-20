<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Shared database backup logic used by both the admin "Create Backup" button
 * (Admin\BackupController, manual, filename prefix "backup-") and the daily
 * `app:backup-database` scheduled command (automatic, filename prefix "auto-").
 * Detects the current DB driver: mysqldump for mysql (credentials read from config,
 * never hardcoded), a straight file copy for sqlite. Files live in storage/app/backups
 * (never web-exposed directly — downloads are streamed through BackupController so the
 * raw storage path is never public).
 *
 * The filename prefix is what lets retention cleanup (pruneAutoBackups()) tell the two
 * apart: it only ever deletes "auto-" files, never a "backup-" one an admin deliberately
 * created, no matter how old that manual backup gets.
 */
class DatabaseBackup
{
    public const MANUAL_PREFIX = 'backup';
    public const AUTO_PREFIX = 'auto';

    /** Matches both manual ("backup-...") and automatic ("auto-...") backup filenames. */
    public const FILENAME_PATTERN = '/^(backup|auto)-\d{4}-\d{2}-\d{2}_\d{6}\.(sql|sqlite)$/';

    /**
     * How many automatic backups to keep. Daily backups + 7 days of retention covers a
     * full week of rollback room without the backups directory growing without bound —
     * enough to recover from a bad deploy or data mistake noticed a few days later,
     * without quietly accumulating months of snapshots on disk.
     */
    public const AUTO_RETENTION_COUNT = 7;

    public static function dir(): string
    {
        return storage_path('app/backups');
    }

    /**
     * Creates a backup for the given DB driver with the given filename prefix
     * (self::MANUAL_PREFIX or self::AUTO_PREFIX). When the prefix is "auto", also prunes
     * old automatic backups beyond the retention count afterward.
     *
     * Returns ['success' => true, 'filename' => ..., 'pruned' => int] on success, or
     * ['success' => false, 'error' => <code>, 'message' => <detail, mysqldump only>] on failure.
     */
    public static function create(string $driver, string $prefix): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $timestamp = now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            $result = self::createSqlite($dir, $timestamp, $prefix);
        } elseif ($driver === 'mysql') {
            $result = self::createMysql($dir, $timestamp, $prefix);
        } else {
            return ['success' => false, 'error' => 'unsupported_driver'];
        }

        if ($result['success'] && $prefix === self::AUTO_PREFIX) {
            $result['pruned'] = self::pruneAutoBackups();
        }

        return $result;
    }

    public static function createSqlite(string $dir, string $timestamp, string $prefix): array
    {
        $source = config('database.connections.sqlite.database');
        if (!$source || !is_file($source)) {
            return ['success' => false, 'error' => 'sqlite_source_missing'];
        }

        $filename = "{$prefix}-{$timestamp}.sqlite";
        if (!copy($source, $dir . '/' . $filename)) {
            return ['success' => false, 'error' => 'copy_failed'];
        }

        return ['success' => true, 'filename' => $filename];
    }

    public static function createMysql(string $dir, string $timestamp, string $prefix): array
    {
        $binary = (new ExecutableFinder())->find('mysqldump');
        if (!$binary) {
            return ['success' => false, 'error' => 'mysqldump_missing'];
        }

        $config = config('database.connections.mysql');
        $args = [$binary, '--host=' . $config['host'], '--port=' . $config['port'], '--user=' . $config['username']];
        if (!empty($config['unix_socket'])) {
            $args[] = '--socket=' . $config['unix_socket'];
        }
        $args[] = $config['database'];

        // The password is passed via MYSQL_PWD (an env var), never as a CLI argument, so it never
        // shows up in `ps` output or process listings.
        $process = new Process($args, null, ['MYSQL_PWD' => $config['password'] ?? '']);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'success' => false,
                'error' => 'mysqldump_failed',
                'message' => trim($process->getErrorOutput()) ?: 'unknown error',
            ];
        }

        $filename = "{$prefix}-{$timestamp}.sql";
        file_put_contents($dir . '/' . $filename, $process->getOutput());

        return ['success' => true, 'filename' => $filename];
    }

    /**
     * Deletes automatic ("auto-") backups beyond the retention count, oldest first.
     * Never touches a "backup-" (manual) file regardless of its age. Returns the number
     * of files deleted.
     */
    public static function pruneAutoBackups(int $keep = self::AUTO_RETENTION_COUNT): int
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return 0;
        }

        $autoFiles = [];
        foreach (scandir($dir) ?: [] as $name) {
            if (!preg_match('/^' . self::AUTO_PREFIX . '-\d{4}-\d{2}-\d{2}_\d{6}\.(sql|sqlite)$/', $name)) {
                continue;
            }
            $path = $dir . '/' . $name;
            $autoFiles[] = ['name' => $name, 'path' => $path, 'modified' => filemtime($path)];
        }

        usort($autoFiles, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);

        $deleted = 0;
        foreach (array_slice($autoFiles, $keep) as $old) {
            if (unlink($old['path'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** All backups (manual and automatic) in the backups directory, newest first. */
    public static function listBackups(): array
    {
        $dir = self::dir();
        if (!is_dir($dir)) {
            return [];
        }

        $files = [];
        foreach (scandir($dir) ?: [] as $name) {
            if (!preg_match(self::FILENAME_PATTERN, $name)) {
                continue;
            }
            $path = $dir . '/' . $name;
            $files[] = [
                'name' => $name,
                'size' => filesize($path),
                'modified' => filemtime($path),
                'auto' => str_starts_with($name, self::AUTO_PREFIX . '-'),
            ];
        }
        usort($files, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);
        return $files;
    }
}
