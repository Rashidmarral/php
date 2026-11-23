<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/** Shared upload handling for support-ticket message attachments (used by the company, admin, and client-portal ticket controllers). */
class TicketAttachment
{
    private const ALLOWED_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
    private const MAX_BYTES = 10 * 1024 * 1024;

    /** @return array{path: ?string, name: ?string, error: ?string} */
    public static function store(?UploadedFile $file, int $ticketId): array
    {
        if (!$file) {
            return ['path' => null, 'name' => null, 'error' => null];
        }
        if (!$file->isValid()) {
            return ['path' => null, 'name' => null, 'error' => 'The attachment failed to upload. Please try again.'];
        }
        if ($file->getSize() > self::MAX_BYTES) {
            return ['path' => null, 'name' => null, 'error' => 'Attachment must be smaller than 10MB.'];
        }
        $originalName = $file->getClientOriginalName();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!in_array($ext, self::ALLOWED_EXT, true)) {
            return ['path' => null, 'name' => null, 'error' => 'Attachment type not allowed. Allowed: ' . implode(', ', self::ALLOWED_EXT)];
        }

        $storedName = bin2hex(random_bytes(12)) . '.' . $ext;
        $file->move(public_path("uploads/support/{$ticketId}"), $storedName);

        return ['path' => "/uploads/support/{$ticketId}/{$storedName}", 'name' => $originalName, 'error' => null];
    }
}
