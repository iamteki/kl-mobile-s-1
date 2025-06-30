<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait HasMedia
{
    /**
     * Boot the trait.
     */
    public static function bootHasMedia()
    {
        static::deleting(function ($model) {
            if (method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                return;
            }

            $model->media->each(function ($media) {
                $media->delete();
            });
        });
    }

    /**
     * Get all media for this model.
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable')->orderBy('sort_order');
    }

    /**
     * Add media to the model.
     */
    public function addMedia(UploadedFile $file, string $collection = 'default', array $metadata = []): Media
    {
        $filename = $this->generateFilename($file);
        $path = $this->getMediaPath($collection);
        
        // Store the file
        $storedPath = $file->storeAs($path, $filename, 'public');
        
        // Determine if this should be primary
        $isPrimary = $this->media()->where('collection', $collection)->count() === 0;
        
        // Create media record
        return $this->media()->create([
            'collection' => $collection,
            'filename' => $filename,
            'path' => $storedPath,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'metadata' => $metadata,
            'is_primary' => $isPrimary,
            'sort_order' => $this->media()->where('collection', $collection)->max('sort_order') + 1,
        ]);
    }

    /**
     * Add multiple media files.
     */
    public function addMediaFromFiles(array $files, string $collection = 'default'): array
    {
        $media = [];
        
        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $media[] = $this->addMedia($file, $collection);
            }
        }
        
        return $media;
    }

    /**
     * Get media by collection.
     */
    public function getMedia(string $collection = 'default')
    {
        return $this->media()->where('collection', $collection)->get();
    }

    /**
     * Get first media from collection.
     */
    public function getFirstMedia(string $collection = 'default'): ?Media
    {
        return $this->media()->where('collection', $collection)->first();
    }

    /**
     * Get primary media from collection.
     */
    public function getPrimaryMedia(string $collection = 'default'): ?Media
    {
        return $this->media()
            ->where('collection', $collection)
            ->where('is_primary', true)
            ->first();
    }

    /**
     * Set primary media.
     */
    public function setPrimaryMedia(Media $media): bool
    {
        // Remove primary flag from other media in same collection
        $this->media()
            ->where('collection', $media->collection)
            ->where('id', '!=', $media->id)
            ->update(['is_primary' => false]);
        
        // Set this media as primary
        return $media->update(['is_primary' => true]);
    }

    /**
     * Delete media.
     */
    public function deleteMedia(Media $media): bool
    {
        // Delete file from storage
        if (Storage::disk('public')->exists($media->path)) {
            Storage::disk('public')->delete($media->path);
        }
        
        // If this was primary, make the next one primary
        if ($media->is_primary) {
            $nextMedia = $this->media()
                ->where('collection', $media->collection)
                ->where('id', '!=', $media->id)
                ->orderBy('sort_order')
                ->first();
            
            if ($nextMedia) {
                $nextMedia->update(['is_primary' => true]);
            }
        }
        
        return $media->delete();
    }

    /**
     * Clear all media from collection.
     */
    public function clearMediaCollection(string $collection = 'default'): void
    {
        $this->media()->where('collection', $collection)->each(function ($media) {
            $this->deleteMedia($media);
        });
    }

    /**
     * Sync media order.
     */
    public function syncMediaOrder(array $mediaIds): void
    {
        foreach ($mediaIds as $order => $mediaId) {
            $this->media()->where('id', $mediaId)->update(['sort_order' => $order]);
        }
    }

    /**
     * Get media URL.
     */
    public function getMediaUrl(string $collection = 'default'): ?string
    {
        $media = $this->getPrimaryMedia($collection) ?: $this->getFirstMedia($collection);
        
        return $media ? $media->getUrl() : null;
    }

    /**
     * Get all media URLs.
     */
    public function getMediaUrls(string $collection = 'default'): array
    {
        return $this->getMedia($collection)->map(function ($media) {
            return $media->getUrl();
        })->toArray();
    }

    /**
     * Generate unique filename.
     */
    protected function generateFilename(UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension();
        $filename = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        
        return $filename . '-' . time() . '-' . Str::random(5) . '.' . $extension;
    }

    /**
     * Get media storage path.
     */
    protected function getMediaPath(string $collection = 'default'): string
    {
        $modelName = Str::plural(Str::snake(class_basename($this)));
        
        return "{$modelName}/{$this->id}/{$collection}";
    }

    /**
     * Check if has media in collection.
     */
    public function hasMedia(string $collection = 'default'): bool
    {
        return $this->media()->where('collection', $collection)->exists();
    }
}