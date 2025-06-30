<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $fillable = [
        'collection',
        'filename',
        'path',
        'mime_type',
        'size',
        'metadata',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'metadata' => 'array',
        'size' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($media) {
            // Delete the file when the model is deleted
            if (Storage::disk('public')->exists($media->path)) {
                Storage::disk('public')->delete($media->path);
            }
        });
    }

    /**
     * Get the owning mediable model.
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the full URL of the media.
     */
    public function getUrl(): string
    {
        return asset('storage/' . $this->path);
    }

    /**
     * Get the full path of the media.
     */
    public function getFullPath(): string
    {
        return storage_path('app/public/' . $this->path);
    }

    /**
     * Check if the media is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }

    /**
     * Check if the media is a video.
     */
    public function isVideo(): bool
    {
        return str_starts_with($this->mime_type, 'video/');
    }

    /**
     * Check if the media is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    /**
     * Get file size in human readable format.
     */
    public function getHumanReadableSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size;
        $i = 0;

        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Get thumbnail URL or generate one.
     */
    public function getThumbnailUrl(int $width = 200, int $height = 200): string
    {
        if (!$this->isImage()) {
            // Return default thumbnail for non-images
            return asset('images/file-thumbnail.png');
        }

        // For now, return the original image
        // In production, you might want to use an image manipulation library
        // to generate actual thumbnails
        return $this->getUrl();
    }

    /**
     * Scope to get media by collection.
     */
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    /**
     * Scope to get primary media.
     */
    public function scopePrimary($query)
    {
        return $query->where('is_primary', true);
    }

    /**
     * Scope to order by sort order.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}