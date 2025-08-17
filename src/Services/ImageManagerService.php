<?php

namespace Melsaka\ImageManager\Services;

use Melsaka\ImageManager\Models\Image;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ImageManagerService
{
    private const BASE_PATH = 'uploads';

    private ImageManager $imageManager;

    private array $config;

    /**
     * Constructor to initialize the ImageManager and configuration settings.
     */
    public function __construct()
    {
        $this->config = config('image-manager');
        $this->imageManager = new ImageManager(new Driver);
    }

    /**
     * Store or update an image for a specific model.
     * This method stores a new image or updates an existing image of the specified type for the model.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file to store or update.
     * @param \Illuminate\Database\Eloquent\Model $model The model instance associated with the image.
     * @param string $imageType The type of image to store or update (default is 'default').
     * @param \App\Models\Image|null $existingImage The existing Image instance to update, if any.
     * @return \App\Models\Image The stored or updated Image instance.
     * @throws \Exception
     */
    public function storeOrUpdate(UploadedFile $file, Model $model, string $imageType = 'default', ?Image $existingImage = null): Image
    {
        try {
            return DB::transaction(function () use ($file, $model, $imageType, $existingImage) {
                if ($existingImage) {
                    // Delete old files but keep the record
                    $this->deleteFiles($existingImage);

                    // Store new files
                    $imageName = $this->generateImageName($file);
                    $sizes = $this->getModelSizes($model::class, $imageType);

                    $this->storeImageVersions(
                        $file,
                        $model::class,
                        $imageType,
                        $imageName,
                        $sizes
                    );

                    // Update existing record
                    $existingImage->update(['name' => $imageName]);

                    return $existingImage->fresh();
                }

                // If no existing image, create new
                return $this->store($file, $model, $imageType);
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Store an image for a specific model with its defined sizes.
     *
     * @param \Illuminate\Http\UploadedFile $file The uploaded file to store.
     * @param \Illuminate\Database\Eloquent\Model $model The model instance associated with the image.
     * @param string $imageType The type of image to store (default is 'default').
     * @return \App\Models\Image The stored Image instance.
     * @throws \Exception
     */
    public function store(UploadedFile $file, Model $model, string $imageType = 'default'): Image
    {
        try {
            return DB::transaction(function () use ($file, $model, $imageType) {

                $imageName = $this->generateImageName($file);
                $sizes = $this->getModelSizes($model::class, $imageType);

                // Store the files
                $this->storeImageVersions(
                    $file,
                    $model::class,
                    $imageType,
                    $imageName,
                    $sizes
                );

                // Create database record
                $image = $model->images()->create([
                    'name' => $imageName,
                    'type' => $imageType,
                ]);

                return $image;
            });
        } catch (Exception $e) {
            // Clean up any files that might have been created
            $this->deleteFiles($imageName, $model::class, $imageType);
            throw $e;
        }
    }

    /**
     * Delete an image record and its files.
     *
     * @param \App\Models\Image $image The Image instance to delete.
     * @return bool True if the image and its files were deleted successfully, false otherwise.
     * @throws \Exception
     */
    public function delete(Image $image): bool
    {
        try {
            return DB::transaction(function () use ($image) {
                $deleted = $this->deleteFiles($image);

                $image->delete();

                return $deleted;
            });
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Delete just the files associated with an image.
     *
     * @param \App\Models\Image $image The Image instance whose files are to be deleted.
     * @return bool True if the files were deleted successfully, false otherwise.
     */
    public function deleteFiles(Image $image): bool
    {
        return $this->deleteImageVersions(
            $image->name,
            $image->imageable_type,
            $image->type
        );
    }

    /**
     * Get all URLs for an image's different sizes.
     * 
     * @param string $imageName The name of the image file.
     * @param string $modelType The model type associated with the image.
     * @param string $imageType The category/type of the image (default is 'default').
     * 
     * @return array An associative array containing URLs for all image sizes.
     */
    public function getImageUrls(
        string $imageName,
        string $modelType,
        string $imageType = 'default'
    ): array {
        if (! $imageName) {
            return [];
        }

        $sizes = $this->getModelSizes($modelType, $imageType);

        $urls = [
            'name' => $imageName,
            'original' => $this->getImageUrl($modelType, $imageType, 'original', $imageName)
        ];

        foreach ($sizes as $size => $dimensions) {
            $urls[$size] = $this->getImageUrl($modelType, $imageType, $size, $imageName);
        }

        return $urls;
    }

    /**
     * Get the storage URL of a specific image size.
     * 
     * @param string $modelType The model type associated with the image.
     * @param string $imageType The category/type of the image.
     * @param string $size The size label of the image (e.g., 'thumbnail', 'medium').
     * @param string $imageName The name of the image file.
     * 
     * @return string The full URL of the image.
     */
    public function getImageUrl(
        string $modelType,
        string $imageType,
        string $size,
        string $imageName
    ): string {
        $path = $this->getImagePath($modelType, $imageType, $size, $imageName);

        return $this->storageUrl($path);
    }

    /**
     * Retrieve the defined sizes for a model's images.
     * 
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * 
     * @return array An array of image sizes and their dimensions.
     */
    public function getModelSizes(string $modelType, string $imageType): array
    {
        $config = $this->getModelConfig($modelType, $imageType);

        return isset($config['sizes']) ? $config['sizes'] : [];
    }

    /**
     * Determine if a model's images are publicly accessible.
     * 
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * 
     * @return bool True if the images are public, false otherwise.
     */
    public function isModelPublic(string $modelType, string $imageType): bool
    {
        $config = $this->getModelConfig($modelType, $imageType);

        return isset($config['public']) ? $config['public'] : false;
    }

    /**
     * Determine if a model's images are private.
     * 
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * 
     * @return bool True if the images are private, false otherwise.
     */
    public function isModelPrivate(string $modelType, string $imageType): bool
    {
        return ! $this->isModelPublic($modelType, $imageType);
    }

    /**
     * Generate the storage path for an image.
     * 
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * @param string $size The size label of the image.
     * @param string $imageName The name of the image file.
     * 
     * @return string The constructed storage path.
     */
    public function getImagePath(
        string $modelType,
        string $imageType,
        string $size,
        string $imageName
    ): string {
        $modelType = Str::snake(class_basename($modelType));

        return sprintf(
            '%s/%s/%s/%s/%s',
            $this->config['base_path'],
            $modelType,
            $imageType,
            $size,
            $imageName
        );
    }

    /**
     * Store an image and its resized versions in storage.
     * 
     * @param UploadedFile $file The uploaded image file.
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * @param string $imageName The name under which the image should be stored.
     * @param array $sizes The sizes to generate and store.
     */
    private function storeImageVersions(
        UploadedFile $file,
        string $modelType,
        string $imageType,
        string $imageName,
        array $sizes
    ): void {
        $visibility = 'public';

        // Store original image
        $originalPath = $this->getImagePath($modelType, $imageType, 'original', $imageName);

        $this->storagePut(
            $originalPath,
            $this->imageManager->read($file)->encodeByExtension(
                extension: $this->config['format'],
                quality: $this->config['quality']
            )->toFilePointer(),
            ['visibility' => $visibility]
        );

        // Store resized versions
        foreach ($sizes as $size => $dimensions) {
            $resizedPath = $this->getImagePath($modelType, $imageType, $size, $imageName);

            $image = $this->imageManager->read($file);

            if ($dimensions['mode'] === 'cover') {
                $image->cover($dimensions['width'], $dimensions['height']);
            } else {
                $image->scale($dimensions['width'], $dimensions['height']);
            }

            $this->storagePut(
                $resizedPath,
                $image->encodeByExtension(
                    extension: $this->config['format'],
                    quality: $this->config['quality']
                )->toFilePointer(),
                ['visibility' => $visibility]
            );
        }
    }

    /**
     * Delete an image and all its resized versions from storage.
     * 
     * @param string $imageName The name of the image to delete.
     * @param string $modelType The model type.
     * @param string $imageType The category/type of the image.
     * 
     * @return bool True if the deletion was successful, false otherwise.
     */
    private function deleteImageVersions(
        string $imageName,
        string $modelType,
        string $imageType
    ): bool {
        if (! $imageName) {
            return false;
        }

        $sizes = $this->getModelSizes($modelType, $imageType);
        $paths = [$this->getImagePath($modelType, $imageType, 'original', $imageName)];

        foreach ($sizes as $size => $dimensions) {
            $paths[] = $this->getImagePath($modelType, $imageType, $size, $imageName);
        }

        foreach ($paths as $path) {
            if ($this->storageExists($path)) {
                $this->storageDelete($path);
            }
        }

        return true;
    }

    /**
     * Retrieve the configuration settings for a model's image type.
     *
     * @param string $modelType The model type.
     * @param string $imageType The image category.
     * @return array The configuration array for the specified model type and image type.
     * @throws \Exception If the model type or image type is invalid.
     */
    private function getModelConfig(string $modelType, string $imageType): array
    {
        $modelType = Str::snake(class_basename($modelType));

        return $this->config['models'][$modelType]['types'][$imageType] ?? throw new \Exception(__('Invalid model or image type'));
    }

    /**
     * Generate a unique name for an uploaded image.
     *
     * @param UploadedFile $file The uploaded file.
     * @return string A unique filename with the configured format.
     */
    private function generateImageName(UploadedFile $file): string
    {
        return Str::uuid().'.'.$this->config['format'];
    }

    /**
     * Get the configured storage disk or default to 'local'.
     *
     * @return string The storage disk name.
     */
    public function getStorageDisk(): string
    {
        return $this->config['storage_disk'] ?? 'local';
    }

    /**
     * Check if a file exists in the storage disk.
     *
     * @param string $path The relative path of the file.
     * @return bool True if the file exists, false otherwise.
     */
    public function storageExists(string $path): bool
    {
        return Storage::disk($this->getStorageDisk())->exists($path);
    }

    /**
     * Delete a file from the storage disk.
     *
     * @param string $path The relative path of the file to delete.
     * @return bool True if the file was deleted, false otherwise.
     */
    public function storageDelete(string $path): bool
    {
        return Storage::disk($this->getStorageDisk())->delete($path);
    }

    /**
     * Get the publicly accessible URL of a file in storage.
     *
     * @param string $path The relative path of the file.
     * @return string The file URL.
     */
    public function storageUrl(string $path): string
    {
        return Storage::disk($this->getStorageDisk())->url($path);
    }

    /**
     * Store a file in the storage disk.
     *
     * @param mixed ...$arguments Arguments passed to the `put` method.
     * @return bool True if the file was stored successfully, false otherwise.
     */
    public function storagePut(mixed ...$arguments): bool
    {
        return Storage::disk($this->getStorageDisk())->put(...$arguments);
    }
}