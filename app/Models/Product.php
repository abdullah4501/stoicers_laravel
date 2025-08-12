<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Product extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'featured_image',
        'images',
    ];

    protected $casts = [
        'images' => 'array',
    ];

    // Auto-generate slug on creating if not provided
    protected static function booted()
    {
        static::creating(function (Product $product) {
            if (empty($product->slug)) {
                $base = Str::slug($product->name);
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-{$i}";
                    $i++;
                }
                $product->slug = $slug;
            }
        });
    }

    // Optional accessors for full URLs if you’re using the "public" disk
    public function getFeaturedImageUrlAttribute(): ?string
    {
        return $this->featured_image ? asset('storage/'.$this->featured_image) : null;
    }

    public function getImagesUrlsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($path) => asset('storage/'.$path))
            ->all();
    }

    public function orders()
    {
        return $this->hasMany(\App\Models\Order::class);
    }
}
