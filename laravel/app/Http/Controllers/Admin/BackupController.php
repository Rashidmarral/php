<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\DatabaseBackup;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Manual database backups, triggered from the admin UI — no cloud upload. The actual backup
 * (mysqldump / sqlite file copy) and listing logic live in App\Support\DatabaseBackup, shared
 * with the automatic daily backup run by the `app:backup-database` scheduled command (see
 * routes/console.php). This controller is a thin wrapper: it calls DatabaseBackup with the
 * "backup-" (manual) filename prefix and translates its result into a flash message. Files
 * live in storage/app/backups (never web-exposed directly — downloads are streamed through
 * this controller so the raw storage path is never public).
 */
class BackupController extends Controller
{
    public function index(): View
    {
        return view('admin.backups.index', [
            'backups' => DatabaseBackup::listBackups(),
            'driver' => config('database.default'),
            'autoBackupRetention' => DatabaseBackup::AUTO_RETENTION_COUNT,
        ]);
    }

    public function create(): RedirectResponse
    {
        $driver = config('database.default');
        $result = DatabaseBackup::create($driver, DatabaseBackup::MANUAL_PREFIX);

        if (!$result['success']) {
            return $this->redirectWithFlash('/admin/backups', 'error', $this->errorMessage($result));
        }

        return $this->redirectWithFlash('/admin/backups', 'success', t('admin.backups.created'));
    }

    private function errorMessage(array $result): string
    {
        return match ($result['error']) {
            'sqlite_source_missing' => t('admin.backups.sqlite_source_missing'),
            'copy_failed' => t('admin.backups.copy_failed'),
            'mysqldump_missing' => t('admin.backups.mysqldump_missing'),
            'mysqldump_failed' => t('admin.backups.mysqldump_failed', ['error' => $result['message'] ?? 'unknown error']),
            default => t('admin.backups.unsupported_driver'),
        };
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
        if (basename($filename) !== $filename || !preg_match(DatabaseBackup::FILENAME_PATTERN, $filename)) {
            return null;
        }
        $path = DatabaseBackup::dir() . '/' . $filename;
        return is_file($path) ? $path : null;
    }
}
