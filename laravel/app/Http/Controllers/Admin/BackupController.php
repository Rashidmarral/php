<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Manual database backups only — no scheduling/cron automation, no cloud upload. Detects the
 * current DB driver: mysqldump for mysql (credentials read from config, never hardcoded), a
 * straight file copy for sqlite (this app's common local/dev setup). Files live in
 * storage/app/backups (never web-exposed directly — downloads are streamed through this
 * controller so the raw storage path is never public).
 */
class BackupController extends Controller
{
    private const FILENAME_PATTERN = '/^backup-\d{4}-\d{2}-\d{2}_\d{6}\.(sql|sqlite)$/';

    public function index(): View
    {
        return view('admin.backups.index', [
            'backups' => $this->listBackups(),
            'driver' => config('database.default'),
        ]);
    }

    public function create(): RedirectResponse
    {
        $driver = config('database.default');
        $dir = $this->backupDir();
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $timestamp = now()->format('Y-m-d_His');

        if ($driver === 'sqlite') {
            return $this->backupSqlite($dir, $timestamp);
        }
        if ($driver === 'mysql') {
            return $this->backupMysql($dir, $timestamp);
        }

        return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.unsupported_driver'));
    }

    private function backupSqlite(string $dir, string $timestamp): RedirectResponse
    {
        $source = config('database.connections.sqlite.database');
        if (!$source || !is_file($source)) {
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.sqlite_source_missing'));
        }

        $filename = "backup-{$timestamp}.sqlite";
        if (!copy($source, $dir . '/' . $filename)) {
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.copy_failed'));
        }

        return $this->redirectWithFlash('/admin/backups', 'success', t('admin.backups.created'));
    }

    private function backupMysql(string $dir, string $timestamp): RedirectResponse
    {
        $binary = (new ExecutableFinder())->find('mysqldump');
        if (!$binary) {
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.mysqldump_missing'));
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
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.mysqldump_failed', ['error' => trim($process->getErrorOutput()) ?: 'unknown error']));
        }

        $filename = "backup-{$timestamp}.sql";
        file_put_contents($dir . '/' . $filename, $process->getOutput());

        return $this->redirectWithFlash('/admin/backups', 'success', t('admin.backups.created'));
    }

    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $path = $this->safePath($filename);
        if (!$path) {
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.not_found'));
        }

        return response()->download($path, $filename);
    }

    public function destroy(string $filename): RedirectResponse
    {
        $path = $this->safePath($filename);
        if (!$path) {
            return $this->redirectWithFlash('/admin/backups', 'error', t('admin.backups.not_found'));
        }

        unlink($path);
        return $this->redirectWithFlash('/admin/backups', 'success', t('admin.backups.deleted'));
    }

    /** Only ever resolves to a plain file directly inside the backups directory — never allows path traversal. */
    private function safePath(string $filename): ?string
    {
        if (basename($filename) !== $filename || !preg_match(self::FILENAME_PATTERN, $filename)) {
            return null;
        }
        $path = $this->backupDir() . '/' . $filename;
        return is_file($path) ? $path : null;
    }

    private function backupDir(): string
    {
        return storage_path('app/backups');
    }

    private function listBackups(): array
    {
        $dir = $this->backupDir();
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
            ];
        }
        usort($files, fn (array $a, array $b) => $b['modified'] <=> $a['modified']);
        return $files;
    }
}
