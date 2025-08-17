<?php

namespace Melsaka\ImageManager\Traits;

use Melsaka\ImageManager\Models\Image;
use Melsaka\ImageManager\Services\ImageManagerService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as SupportCollection;

trait HasImages
{
    /**
     * Boot the HasImages trait for a model.
     * This method adds a deleting event listener to delete all images when the model is deleted.
     */
    protected static function bootHasImages()
    {
        static::deleting(function ($model) {
            // If model is force deleting or doesn't support soft deletes
            if (! method_exists($model, 'isForceDeleting') || $model->isForceDeleting()) {
                $model->deleteAllImages();
            }
        });
    }

    /**
     * Define a polymorphic one-to-many relationship to the Image model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable');
    }

    /**
     * Store multiple images for the model.
     * This method stores the uploaded files as images of the specified type for the model.
     *
     * @param array $files An array of UploadedFile instances.
     * @param string $imageType The type of images to store (default is 'default').
     * @return \Illuminate\Support\Collection The collection of stored Image instances.
     */
    public function storeImages(array $files, string $imageType = 'default'): SupportCollection
    {
        $images = collect();

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $images->push(app(ImageManagerService::class)->store($file, $this, $imageType));
            }
        }

        return $images;
    }

    /**
     * Store a single image for the model.
     * This method stores the uploaded file as an image of the specified type for the model.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file to store.
     * @param string $imageType The type of image to store (default is 'default').
     * @return \Melsaka\ImageManager\Models\Image The stored Image instance.
     */
    public function storeImage(UploadedFile $file, string $imageType = 'default'): Image
    {
        return app(ImageManagerService::class)->store($file, $this, $imageType);
    }

    /**
     * Retrieve images of a specific type for the model.
     *
     * @param string $imageType The type of images to retrieve (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return \Illuminate\Database\Eloquent\Collection The collection of Image instances.
     */
    public function getImages(string $imageType = 'default', bool $loaded = false): Collection
    {
        if ($loaded) {
            return $this->images
                ->where('type', $imageType);
        }

        return $this->images()
            ->where('type', $imageType)
            ->get();
    }

    /**
     * Retrieve images of multiple types for the model.
     *
     * @param array $types An array of image types to retrieve.
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return \Illuminate\Database\Eloquent\Collection The collection of Image instances.
     */
    public function getImagesOfTypes(array $types, bool $loaded = false): Collection
    {
        if ($loaded) {
            return $this->images
                ->whereIn('type', $types);
        }

        return $this->images()
            ->whereIn('type', $types)
            ->get();
    }

    /**
     * Retrieve a single image of a specific type for the model.
     *
     * @param string $imageType The type of image to retrieve (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return \Melsaka\ImageManager\Models\Image|null The Image instance or null if not found.
     */
    public function getImage(string $imageType = 'default', bool $loaded = false): ?Image
    {
        if ($loaded) {
            return $this->images
                ->where('type', $imageType)
                ->first();
        }

        return $this->images()
            ->where('type', $imageType)
            ->first();
    }

    /**
     * Retrieve URLs of all images of a specific type for the model.
     *
     * @param string $imageType The type of images to retrieve URLs for (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return array An array of URLs for the images.
     */
    public function getAllImageUrls(string $imageType = 'default', bool $loaded = false): array
    {
        return $this->getImages($imageType, $loaded)
            ->map(fn (Image $image) => $image->getUrls())
            ->toArray();
    }

    /**
     * Retrieve URLs of all images of a specific size and type for the model.
     *
     * @param string $size The size of the images to retrieve URLs for.
     * @param string $imageType The type of images to retrieve URLs for (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return array An array of URLs for the images.
     */
    public function getAllImageUrlsForSize(string $size, string $imageType = 'default', bool $loaded = false): array
    {
        return $this->getImages($imageType, $loaded)
            ->map(fn (Image $image) => $image->getUrl($size))
            ->filter()
            ->toArray();
    }

    /**
     * Retrieve URLs of a single image of a specific type for the model.
     *
     * @param string $imageType The type of image to retrieve URLs for (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return array An array of URLs for the image.
     */
    public function getImageUrls(string $imageType = 'default', bool $loaded = false): array
    {
        $image = $this->getImage($imageType, $loaded);

        return $image ? $image->getUrls() : [];
    }

    /**
     * Retrieve the URL of a single image of a specific size and type for the model.
     *
     * @param string $size The size of the image to retrieve the URL for.
     * @param string $imageType The type of image to retrieve the URL for (default is 'default').
     * @param bool $loaded Whether to use the loaded images relation or query the database.
     * @return string|null The URL of the image or null if not found.
     */
    public function getImageUrl(string $size, string $imageType = 'default', bool $loaded = false): ?string
    {
        $image = $this->getImage($imageType, $loaded);

        return $image ? $image->getUrl($size) : null;
    }

    /**
     * Delete a single image of a specific type for the model.
     *
     * @param string $imageType The type of image to delete.
     * @return bool True if the image was deleted successfully, false otherwise.
     */
    public function deleteImage(string $imageType): bool
    {
        try {
            $image = $this->getImage($imageType);

            if ($image) {
                return $image->delete();
            }

            return false;
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete all images of a specific type for the model.
     *
     * @param string $imageType The type of images to delete.
     * @return bool True if the images were deleted successfully, false otherwise.
     */
    public function deleteImagesOfType(string $imageType): bool
    {
        try {
            return $this->images()
                ->where('type', $imageType)
                ->chunk(100, function ($images) {
                    $images->each(function ($image) {
                        $image->delete();
                    });
                });
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete all images for the model.
     *
     * @return bool True if the images were deleted successfully, false otherwise.
     */
    public function deleteAllImages(): bool
    {
        try {
            return $this->images()
                ->chunk(100, function ($images) {
                    $images->each(function ($image) {
                        $image->delete();
                    });
                });
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete images of multiple types for the model.
     *
     * @param array $types An array of image types to delete.
     * @return bool True if the images were deleted successfully, false otherwise.
     */
    public function deleteImages(array $types): bool
    {
        try {
            return $this->images()
                ->whereIn('type', $types)
                ->chunk(100, function ($images) {
                    $images->each(function ($image) {
                        $image->delete();
                    });
                });
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }

    /**
     * Delete images of multiple types for the model by image name.
     *
     * @param array $types An array of image types to delete.
     * @param array $names An array of image names to delete.
     * @return bool True if the images were deleted successfully, false otherwise.
     */
    public function deleteImagesByName(array $types, array $names): bool
    {
        try {
            return $this->images()
                ->whereIn('type', $types)
                ->chunk(100, function ($images) use($names){
                    $images = $images->whereIn('name', $names);

                    if (! $images->count()) {
                        throw new \Exception(__("There's no images with any of these names"));
                    }

                    $images->each(function ($image) {
                        $image->delete();
                    });
                });
        } catch (\Exception $e) {
            report($e);

            return false;
        }
    }
    
    /**
     * Store or update an image for the model.
     * This method stores a new image or updates an existing image of the specified type for the model.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file to store or update.
     * @param string $imageType The type of image to store or update (default is 'default').
     * @return \Melsaka\ImageManager\Models\Image The stored or updated Image instance.
     */
    public function storeOrUpdateImage(UploadedFile $file, string $imageType = 'default'): Image
    {
        $existingImage = $this->getImage($imageType);

        return app(ImageManagerService::class)->storeOrUpdate($file, $this, $imageType, $existingImage);
    }

    /**
     * Update an image by its ID.
     * This method updates an existing image identified by its ID with the uploaded file.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file to update the image with.
     * @param int $imageId The ID of the image to update.
     * @return \Melsaka\ImageManager\Models\Image|null The updated Image instance or null if the image was not found.
     */
    public function updateImageById(UploadedFile $file, int $imageId): ?Image
    {
        $existingImage = $this->images()->where('id', $imageId)->first();

        if (! $existingImage) {
            return null;
        }

        return app(ImageManagerService::class)->storeOrUpdate(
            $file,
            $this,
            $existingImage->type,
            $existingImage
        );
    }

    /**
     * Replace all images of a specific type for the model.
     * This method deletes all existing images of the specified type and stores new images.
     *
     * @param array $files An array of UploadedFile instances.
     * @param string $imageType The type of images to replace (default is 'default').
     * @return \Illuminate\Support\Collection The collection of newly stored Image instances.
     */
    public function replaceImages(array $files, string $imageType = 'default'): SupportCollection
    {
        // Delete all existing images of this type
        $this->deleteImagesOfType($imageType);

        // Store new images
        return $this->storeImages($files, $imageType);
    }

    /**
     * Sync images of a specific type for the model.
     * This method updates existing images or creates new ones and removes any extra existing images.
     *
     * @param array $files An array of UploadedFile instances.
     * @param string $imageType The type of images to sync (default is 'default').
     * @return \Illuminate\Support\Collection The collection of synced Image instances.
     */
    public function syncImages(array $files, string $imageType = 'default'): SupportCollection
    {
        $existingImages = $this->getImages($imageType);
        $images = collect();

        // Update existing images or create new ones
        foreach ($files as $index => $file) {
            if ($file instanceof UploadedFile) {
                $existingImage = $existingImages->get($index);
                $images->push(
                    app(ImageManagerService::class)->storeOrUpdate($file, $this, $imageType, $existingImage)
                );
            }
        }

        // Remove any extra existing images
        $existingImages->slice(count($files))->each(function ($image) {
            $image->delete();
        });

        return $images;
    }
}