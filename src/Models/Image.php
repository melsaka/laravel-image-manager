<?php

namespace Melsaka\ImageManager\Models;

use Melsaka\ImageManager\Services\ImageManagerService;
use Illuminate\Database\Eloquent\Model;

class Image extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'type',
        'imageable_type',
        'imageable_id',
    ];

    protected static function boot()
    {
        parent::boot();
        
        // When an image is deleted, remove the files
        static::deleting(function ($image) {
            app(ImageManagerService::class)->deleteFiles($image);
        });
    }

    public function getUrls(): array
    {
        return app(ImageManagerService::class)->getImageUrls(
                $this->name,
                $this->imageable_type,
                $this->type
            );
    }

    public function getUrl(string $size): ?string
    {
        return app(ImageManagerService::class)->getImageUrl(
                $this->imageable_type,
                $this->type,
                $size,
                $this->name
            );
    }

    public function getPath(string $size): ?string
    {
        return app(ImageManagerService::class)->getImagePath(
            $this->imageable_type,
            $this->type,
            $size,
            $this->name
        );
    }

    public function getSizes(): array
    {
        return app(ImageManagerService::class)->getModelSizes(
            $this->imageable_type,
            $this->type
        );
    }

    // Polymorphic relation
    public function imageable()
    {
        return $this->morphTo();
    }
}