# Laravel Image Manager Package

A comprehensive Laravel package for managing polymorphic images with automatic resizing, multiple formats support, and flexible storage options.

## Features

- **Polymorphic Image Relations**: Associate images with any Eloquent model
- **Automatic Image Resizing**: Configure multiple sizes for different use cases
- **Format Conversion**: Convert images to WebP, JPEG, PNG, or keep original format
- **Flexible Storage**: Support for any Laravel filesystem disk
- **Batch Operations**: Store, update, sync, and delete multiple images
- **Easy Configuration**: Simple configuration for different models and image types

## Installation

Install the package via Composer:

```bash
composer require melsaka/laravel-image-manager
```

Publish the configuration file:

```bash
php artisan vendor:publish --provider="Melsaka\ImageManager\ImageManagerServiceProvider" --tag="image-manager-config"
```

Publish and run the migration:

```bash
php artisan vendor:publish --provider="Melsaka\ImageManager\ImageManagerServiceProvider" --tag="image-manager-migrations"
php artisan migrate
```

## Configuration

The configuration file `config/image-manager.php` allows you to customize:

- Storage disk and base path
- Default image format and quality
- Model-specific image types and sizes
- Public/private access settings

### Example Configuration

```php
return [
    'storage_disk' => 'public', // could be r2, aws, etc..
    'base_path' => 'uploads',
    'format' => 'webp',
    'quality' => 90,
    
    'models' => [
        'user' => [
            'types' => [
                'avatar' => [
                    'sizes' => [
                        'thumbnail' => [
                            'width' => 100,
                            'height' => 100,
                            'mode' => 'cover',
                        ],
                        'medium' => [
                            'width' => 300,
                            'height' => 300,
                            'mode' => 'cover',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
```

## Usage

### 1. Add the Trait to Your Model

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Melsaka\ImageManager\Traits\HasImages;

class User extends Model
{
    use HasImages;
    
    // Your model code...
}
```

### 2. Store Images

```php
// Store a single image
$user = User::find(1);

$image = $user->storeImage($uploadedFile, 'avatar');

// Store multiple images
$images = $user->storeImages($uploadedFiles, 'gallery');

// Store or update (replaces existing image of same type)
$image = $user->storeOrUpdateImage($uploadedFile, 'avatar');

// get array of all image urls 
$image->getUrls();
```

### 3. Retrieve Images

```php
// Get all images of a specific type
$avatars = $user->getImages('avatar');

// Get a single image
$avatar = $user->getImage('avatar');

// Get image URLs
$urls = $user->getImageUrls('avatar');
// Returns: ['name' => 'filename.webp', 'original' => 'url', 'thumbnail' => 'url', 'medium' => 'url']

// Get specific size URL
$thumbnailUrl = $user->getImageUrl('thumbnail', 'avatar');
```

### 4. Delete Images

```php
// Delete single image by type
$user->deleteImage('avatar');

// Delete all images of a type
$user->deleteImagesOfType('gallery');

// Delete all images
$user->deleteAllImages();
```

### 5. Advanced Operations

```php
// Replace all images of a type
$user->replaceImages($newFiles, 'gallery');

// Sync images (update existing, add new, remove extra)
$user->syncImages($files, 'gallery');

// Update image by ID
$user->updateImageById($uploadedFile, $imageId);
```

### 6. Eager Load Images

```php
// eager load all user iamges
User::with('images')->get();

// load user images
$user->load('images');
```

## Image Resize Modes

- **cover**: Resize and crop to exact dimensions (recommended for thumbnails)
- **scale**: Scale proportionally maintaining aspect ratio
- **resize**: Resize to exact dimensions (may distort the image)

## Environment Variables

You can override configuration values using environment variables:

```env
IMAGE_MANAGER_STORAGE_DISK=s3
IMAGE_MANAGER_BASE_PATH=images
IMAGE_MANAGER_FORMAT=webp
IMAGE_MANAGER_QUALITY=85
```

## Requirements

- PHP ^8.2
- Laravel ^11.0 or ^12.0
- Intervention Image ^3.0

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.