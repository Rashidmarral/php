<?php

namespace App\Models;

class Media extends Model
{
    public $timestamps = true;
    const UPDATED_AT = null;

    protected $guarded = ['id'];

    public const TYPES = ['image' => 'Image', 'video' => 'Video'];

    /** The URL to use wherever this media item is referenced — an uploaded file path or an external video URL. */
    public function url(): string
    {
        return $this->type === 'video' ? (string) $this->video_url : (string) $this->file_path;
    }
}
