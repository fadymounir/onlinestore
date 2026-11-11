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
     * Permission names as the role form holds them: the abilities ticked,
     * keyed by resource. Names outside the vocabulary are dropped — a form
     * cannot show a checkbox it has no heading for.
     *
     * @param  iterable<int, string>  $names
     * @return array<string, array<int, string>>
     */
    public static function group(iterable $names): array
    {
        return Collection::make($names)
            ->map(fn (string $name): array => explode('.', $name, 2))
            ->filter(fn (array $parts): bool => count($parts) === 2
                && isset(self::RESOURCES[$parts[0]])
                && isset(self::ABILITIES[$parts[1]]))
            ->groupBy(fn (array $parts): string => $parts[0])
            ->map(fn (Collection $rows): array => $rows->map(fn (array $parts): string => $parts[1])->values()->all())
            ->all();
    }

    /**
     * The reverse: grouped abilities back to permission names.
     *
     * @param  array<string, array<int, string>|null>  $grouped
     * @return array<int, string>
     */
    public static function flatten(array $grouped): array
    {
        $names = [];

        foreach ($grouped as $resource => $abilities) {
            if (! isset(self::RESOURCES[$resource])) {
                continue;
            }

            foreach ($abilities ?? [] as $ability) {
                if (isset(self::ABILITIES[$ability])) {
                    $names[] = "{$resource}.{$ability}";
                }
            }
        }

        return $names;
    }
}
