<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * The panel's permission vocabulary, in one place.
 *
 * A permission is `resource.ability` — `products.create`, `orders.view` — and
 * those names are code identifiers: they are what `$admin->can()` is asked
 * about, so they stay ASCII. Everything a human reads is the Arabic beside
 * them here, which is also what groups the checkboxes on the role form.
 *
 * The seeder creates permissions from this list and the role form renders
 * them from it, so a new resource is one line rather than two in step.
 */
class AdminPermissions
{
    /** resource => Arabic heading, in the order the role form shows them. */
    public const RESOURCES = [
        'products' => 'المنتجات',
        'variants' => 'المتغيّرات',
        'categories' => 'الأقسام',
        'brands' => 'العلامات التجارية',
        'orders' => 'الطلبات',
        'customers' => 'العملاء',
        'coupons' => 'الكوبونات',
        'reviews' => 'التقييمات',
        'shipping' => 'الشحن والتوصيل',
        'pages' => 'الصفحات',
        'settings' => 'إعدادات المتجر',
        'admins' => 'المشرفون',
        'roles' => 'الأدوار والصلاحيات',
    ];

    /** ability => Arabic label. */
    public const ABILITIES = [
        'view' => 'عرض',
        'create' => 'إضافة',
        'update' => 'تعديل',
        'delete' => 'حذف',
    ];

    /**
     * Every permission name the panel knows about.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        $names = [];

        foreach (array_keys(self::RESOURCES) as $resource) {
            foreach (array_keys(self::ABILITIES) as $ability) {
                $names[] = "{$resource}.{$ability}";
            }
        }

        return $names;
    }

    /**
     * The whole matrix set one way: every resource, every ability, on or off.
     *
     * This is the shape the role form binds its switches to —
     * `abilities.<resource>.<ability>` is a plain boolean — so every switch
     * has a value from the start rather than only once it is touched.
     *
     * @return array<string, array<string, bool>>
     */
    public static function matrix(bool $granted = false): array
    {
        $matrix = [];

        foreach (array_keys(self::RESOURCES) as $resource) {
            foreach (array_keys(self::ABILITIES) as $ability) {
                $matrix[$resource][$ability] = $granted;
            }
        }

        return $matrix;
    }

    /**
     * Permission names as the role form holds them: the full matrix, with the
     * granted ones switched on. Names outside the vocabulary are dropped — a
     * form cannot show a switch it has no heading for.
     *
     * @param  iterable<int, string>  $names
     * @return array<string, array<string, bool>>
     */
    public static function group(iterable $names): array
    {
        $matrix = self::matrix();

        Collection::make($names)
            ->map(fn (string $name): array => explode('.', $name, 2))
            ->filter(fn (array $parts): bool => count($parts) === 2
                && isset(self::RESOURCES[$parts[0]])
                && isset(self::ABILITIES[$parts[1]]))
            ->each(function (array $parts) use (&$matrix): void {
                $matrix[$parts[0]][$parts[1]] = true;
            });

        return $matrix;
    }

    /**
     * The reverse: the switched-on cells back to permission names.
     *
     * @param  array<string, array<string, mixed>|null>  $grouped
     * @return array<int, string>
     */
    public static function flatten(array $grouped): array
    {
        $names = [];

        foreach ($grouped as $resource => $abilities) {
            if (! isset(self::RESOURCES[$resource])) {
                continue;
            }

            foreach ($abilities ?? [] as $ability => $granted) {
                if ($granted && isset(self::ABILITIES[$ability])) {
                    $names[] = "{$resource}.{$ability}";
                }
            }
        }

        return $names;
    }
}
