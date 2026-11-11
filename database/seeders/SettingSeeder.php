<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /** [key, label, value, type, group] */
    private const SETTINGS = [
        ['store_name', 'اسم المتجر', 'Simple', 'string', 'general'],
        ['store_tagline', 'الشعار النصي', 'تسوّق بسهولة من أي مكان في مصر', 'string', 'general'],
        ['store_logo', 'شعار المتجر', null, 'image', 'general'],
        ['store_email', 'البريد الإلكتروني', 'info@simple.test', 'string', 'contact'],
        ['store_phone', 'رقم الهاتف', '01000000000', 'string', 'contact'],
        ['store_whatsapp', 'واتساب', '01000000000', 'string', 'contact'],
        ['store_address', 'عنوان المتجر', 'القاهرة، مصر', 'text', 'contact'],
        ['facebook_url', 'فيسبوك', null, 'string', 'social'],
        ['instagram_url', 'إنستجرام', null, 'string', 'social'],
        ['tiktok_url', 'تيك توك', null, 'string', 'social'],
        ['currency_code', 'كود العملة', 'EGP', 'string', 'checkout'],
        ['currency_symbol', 'رمز العملة', 'ج.م', 'string', 'checkout'],
        ['min_order_total', 'الحد الأدنى للطلب', '100', 'number', 'checkout'],
        ['customer_addresses_enabled', 'السماح للعميل بإضافة عناوين جديدة', '1', 'boolean', 'checkout'],
        ['cod_enabled', 'تفعيل الدفع عند الاستلام', '1', 'boolean', 'payments'],
        ['deposit_enabled', 'تفعيل الدفع بعربون (تحويل بنكي)', '1', 'boolean', 'payments'],
        ['deposit_percent', 'الحد الأدنى للعربون % من إجمالي الطلب', '20', 'number', 'payments'],
        ['deposit_instructions', 'بيانات التحويل الظاهرة للعميل', "حوّل قيمة العربون على أحد الحسابات التالية ثم ارفع صورة الإيصال:\n"
            ."• إنستاباي / محفظة: 01000000000\n"
            .'• حساب بنكي: Simple Store — 1000 0000 0000 0000', 'text', 'payments'],
        ['low_stock_alert', 'تنبيه قرب نفاد المخزون', '5', 'number', 'inventory'],
        ['reviews_need_approval', 'مراجعة التقييمات قبل النشر', '1', 'boolean', 'reviews'],
        ['maintenance_mode', 'وضع الصيانة', '0', 'boolean', 'general'],
        ['ordering_paused', 'إيقاف الطلبات مؤقتاً (التصفح والسلة يظلان يعملان)', '0', 'boolean', 'general'],
    ];

    /**
     * The order the settings page lays its fields out in.
     *
     * Not `id`: three migrations insert settings rows of their own, and on a
     * fresh install those claim the low ids — which put the deposit fields
     * above the store name. The array above is the deliberate order, so it is
     * the one the page reads.
     *
     * @return array<int, string>
     */
    public static function declaredOrder(): array
    {
        return array_column(self::SETTINGS, 0);
    }

    public function run(): void
    {
        foreach (self::SETTINGS as [$key, $label, $value, $type, $group]) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'value' => $value, 'type' => $type, 'group' => $group],
            );
        }
    }
}
