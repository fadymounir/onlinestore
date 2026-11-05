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
        ['cod_enabled', 'تفعيل الدفع عند الاستلام', '1', 'boolean', 'checkout'],
        ['deposit_enabled', 'تفعيل الدفع بعربون (تحويل بنكي)', '1', 'boolean', 'checkout'],
        ['deposit_percent', 'الحد الأدنى للعربون % من إجمالي الطلب', '20', 'number', 'checkout'],
        ['deposit_instructions', 'بيانات التحويل الظاهرة للعميل', "حوّل قيمة العربون على أحد الحسابات التالية ثم ارفع صورة الإيصال:\n"
            ."• إنستاباي / محفظة: 01000000000\n"
            .'• حساب بنكي: Simple Store — 1000 0000 0000 0000', 'text', 'checkout'],
        ['low_stock_alert', 'تنبيه قرب نفاد المخزون', '5', 'number', 'inventory'],
        ['reviews_need_approval', 'مراجعة التقييمات قبل النشر', '1', 'boolean', 'reviews'],
        ['maintenance_mode', 'وضع الصيانة', '0', 'boolean', 'general'],
    ];

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
