<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Central image/video library the admin uses to add real imagery to the marketing site — upload once here,
 * then paste the resulting URL into a page's hero image/video field (Settings → Website Content) or into
 * any CMS page body (Website Pages).
 */
class MediaController extends Controller
{
    private const ALLOWED_IMAGE_TYPES = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function index(): View
    {
        return view('admin.media.index', [
            'media' => Media::orderByDesc('created_at')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $title = trim((string) $request->input('title'));
        $type = $request->input('type') === 'video' ? 'video' : 'image';

        if ($title === '') {
            return $this->redirectWithFlash('/admin/media', 'error', t('admin.media.title_required'));
        }

        $data = ['title' => $title, 'type' => $type, 'uploaded_by' => Auth::id()];

        if ($type === 'video') {
            $videoUrl = trim((string) $request->input('video_url'));
            if (!filter_var($videoUrl, FILTER_VALIDATE_URL)) {
                return $this->redirectWithFlash('/admin/media', 'error', t('admin.media.invalid_video_url'));
            }
            $data['video_url'] = $videoUrl;
        } else {
            /** @var UploadedFile|null $file */
            $file = $request->file('image');
            if (!$file || !$file->isValid() || !isset(self::ALLOWED_IMAGE_TYPES[$file->getMimeType()])) {
                return $this->redirectWithFlash('/admin/media', 'error', t('admin.media.image_type_invalid'));
            }
            if ($file->getSize() > 8 * 1024 * 1024) {
                return $this->redirectWithFlash('/admin/media', 'error', t('admin.media.image_max_size'));
            }
            $filename = bin2hex(random_bytes(8)) . '.' . self::ALLOWED_IMAGE_TYPES[$file->getMimeType()];
            $file->move(public_path('uploads/media'), $filename);
            $data['file_path'] = "/uploads/media/{$filename}";
        }

        Media::create($data);
        $this->flash('success', t('admin.media.added'));
        return redirect('/admin/media');
    }

    public function destroy(int $id): RedirectResponse
    {
        $media = Media::find($id);
        abort_if(!$media, 404, 'Media not found.');
        if ($media->file_path) {
            $file = public_path($media->file_path);
            if (is_file($file)) {
                unlink($file);
            }
        }
        $media->delete();
        $this->flash('success', t('admin.media.removed'));
        return redirect('/admin/media');
    }
}
