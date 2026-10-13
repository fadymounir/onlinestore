<?php

namespace Database\Seeders;

use App\Enums\AttributeType;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    /**
     * A generic colour/size pair, kept as a fixture for the test suite.
     *
     * Not part of the default seed run: FloukaaCatalogSeeder brings the real
     * shop's own variation attributes (مقاس الخاتم، طول السلسلة، اللون) and
     * clears this table before inserting them.
     */
    private const ATTRIBUTES = [
        [
            'name' => 'اللون',
            'slug' => 'color',
            'type' => AttributeType::Color,
            'values' => [
                ['أسود', '#000000'],
                ['أبيض', '#ffffff'],
                ['أحمر', '#e53935'],
                ['أزرق', '#1e88e5'],
                ['أخضر', '#43a047'],
                ['أصفر', '#fdd835'],
                ['بيج', '#d7ccc8'],
                ['رمادي', '#9e9e9e'],
                ['بني', '#6d4c41'],
                ['كحلي', '#1a237e'],
            ],
        ],
        [
            'name' => 'المقاس',
            'slug' => 'size',
            'type' => AttributeType::Select,
            'values' => [
                ['XS', null], ['S', null], ['M', null], ['L', null],
                ['XL', null], ['XXL', null], ['3XL', null],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::ATTRIBUTES as $i => $definition) {
            $attribute = Attribute::updateOrCreate(
                ['slug' => $definition['slug']],
                [
                    'name' => $definition['name'],
                    'type' => $definition['type'],
                    'sort_order' => $i,
                    'is_active' => true,
                ],
            );

            foreach ($definition['values'] as $j => [$value, $hex]) {
                AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'value' => $value],
                    ['color_hex' => $hex, 'sort_order' => $j],
                );
            }
        }
    }
}
