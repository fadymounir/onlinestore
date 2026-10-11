<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Cart;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    /** [name, email, phone, governorate, city, street, landmark] */
    private const CUSTOMERS = [
        ['منى عبد الرحمن', 'mona', '01011234501', 'القاهرة', 'مدينة نصر', 'شارع عباس العقاد', 'أمام سيتي ستارز'],
        ['أحمد سمير', 'ahmed', '01011234502', 'الجيزة', 'الدقي', 'شارع التحرير', 'بجوار محطة المترو'],
        ['فاطمة الزهراء', 'fatma', '01011234503', 'الإسكندرية', 'سموحة', 'شارع فوزي معاذ', 'بعد نادي سموحة'],
        ['محمود حسن', 'mahmoud', '01011234504', 'القاهرة', 'المعادي', 'شارع 9', 'فوق صيدلية العزبي'],
        ['نورهان إبراهيم', 'nourhan', '01011234505', 'الدقهلية', 'المنصورة', 'شارع الجيش', 'أمام جامعة المنصورة'],
        ['كريم مصطفى', 'karim', '01011234506', 'الجيزة', 'الشيخ زايد', 'الحي الأول', 'كمبوند الربوة'],
        ['سارة عادل', 'sara', '01011234507', 'القليوبية', 'بنها', 'شارع فريد نده', 'بجوار مستشفى بنها'],
        ['عمر خالد', 'omar', '01011234508', 'الإسكندرية', 'المنتزه', 'شارع المعمورة', 'قرب شاطئ المعمورة'],
        ['هدى السيد', 'hoda', '01011234509', 'أسيوط', 'أسيوط', 'شارع الجمهورية', 'أمام محافظة أسيوط'],
        ['يوسف طارق', 'youssef', '01011234510', 'بورسعيد', 'بورسعيد', 'شارع الجمهورية', 'بجوار النادي المصري'],
        ['دينا فتحي', 'dina', '01011234511', 'الغربية', 'طنطا', 'شارع البحر', 'أمام مسجد السيد البدوي'],
        ['طارق عبد العظيم', 'tarek', '01011234512', 'الأقصر', 'الأقصر', 'شارع التليفزيون', 'قرب معبد الكرنك'],
    ];

    public function run(): void
    {
        $products = Product::with('variants')->get();

        foreach (self::CUSTOMERS as $i => [$name, $handle, $phone, $govName, $cityName, $street, $landmark]) {
            $user = User::updateOrCreate(
                ['email' => "{$handle}@example.com"],
                [
                    'name' => $name,
                    'phone' => $phone,
                    'password' => 'password',
                    'is_active' => $i !== 11,            // one deactivated account
                    'email_verified_at' => now()->subMonths(2),
                    'last_login_at' => now()->subDays($i),
                ],
            );

            $governorate = Governorate::where('name', $govName)->first();
            $city = $governorate
                ? City::where('governorate_id', $governorate->id)->where('name', $cityName)->first()
                : null;

            if ($governorate) {
                Address::updateOrCreate(
                    ['user_id' => $user->id, 'street' => $street],
                    [
                        'governorate_id' => $governorate->id,
                        'city_id' => $city?->id,
                        'label' => 'المنزل',
                        'recipient_name' => $name,
                        'phone' => $phone,
                        'building' => (string) (3 + $i),
                        'floor' => (string) (1 + $i % 6),
                        'apartment' => (string) (2 + $i % 9),
                        'landmark' => $landmark,
                        'is_default' => true,
                    ],
                );

                // A few customers keep a work address too.
                if ($i % 4 === 0) {
                    Address::updateOrCreate(
                        ['user_id' => $user->id, 'street' => 'شارع جامعة الدول العربية'],
                        [
                            'governorate_id' => $governorate->id,
                            'city_id' => $city?->id,
                            'label' => 'العمل',
                            'recipient_name' => $name,
                            'phone' => $phone,
                            'building' => '15',
                            'floor' => '4',
                            'apartment' => 'مكتب 12',
                            'landmark' => 'برج الأعمال',
                            'is_default' => false,
                        ],
                    );
                }
            }

            // Live carts for a handful of shoppers.
            if ($i % 3 === 0) {
                $cart = Cart::updateOrCreate(['user_id' => $user->id]);

                $variants = ProductVariant::inStock()
                    ->where('is_active', true)
                    ->skip($i * 3)
                    ->take(2)
                    ->get();

                foreach ($variants as $variant) {
                    $cart->items()->updateOrCreate(
                        ['product_variant_id' => $variant->id],
                        ['quantity' => 1 + ($i % 3)],
                    );
                }
            }

            // Wishlists.
            $wanted = $products->slice(($i * 2) % max($products->count(), 1), 3)->pluck('id');
            $user->wishlist()->syncWithoutDetaching($wanted);
        }
    }
}
