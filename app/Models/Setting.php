<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'group', 'label'];

    protected $attributes = [
        'type' => 'string',
        'group' => 'general',
    ];

    private const CACHE_KEY = 'settings.all';

    protected static function booted(): void
    {
        // Settings are read on nearly every page; keep the cache honest.
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $settings = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all(),
        );

        return $settings[$key] ?? $default;
    }

    /**
     * Updates a declared setting. Settings are declared by seeders (which
     * carry the label and type), so an unknown key is not created silently —
     * the false return tells the caller nothing was written.
     */
    public static function put(string $key, mixed $value): bool
    {
        $updated = static::query()->where('key', $key)->update(['value' => $value]) > 0;

        Cache::forget(self::CACHE_KEY);

        return $updated;
    }
}
