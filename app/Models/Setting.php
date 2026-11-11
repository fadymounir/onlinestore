<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Drops the cached settings.
     *
     * Saving a setting does this on its own. A migration that writes through
     * the DB facade does not go through the model, so it has to say so —
     * otherwise the old values stay served until something else saves.
     */
    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
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
     * The shop's logo. `store_logo` holds whatever an admin uploaded on the
     * settings page; when it is empty the generated badge in public/images
     * stands in, so the storefront is never left without a mark.
     */
    public static function logoUrl(): string
    {
        $uploaded = static::get('store_logo');

        return filled($uploaded)
            ? Storage::disk('public')->url($uploaded)
            : asset('images/logo-192.png');
    }

    /**
     * Whether a customer may file a new delivery address.
     *
     * Only adding is gated: an address already on file stays editable, so
     * turning this off cannot strand a customer with a wrong address.
     */
    public static function customerAddressesEnabled(): bool
    {
        return (bool) static::get('customer_addresses_enabled', '1');
    }

    /** Whether a shop has uploaded a logo of its own. */
    public static function hasCustomLogo(): bool
    {
        return filled(static::get('store_logo'));
    }

    /**
     * The tab icon. An uploaded logo takes the job whole — one file at
     * whatever size it was given — because the cut-to-size .ico and PNGs in
     * public/ are built from the bundled badge by `npm run logo` and cannot
     * be regenerated from an upload at request time.
     */
    public static function faviconUrl(): string
    {
        return static::hasCustomLogo()
            ? static::logoUrl()
            : asset('favicon.ico');
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
