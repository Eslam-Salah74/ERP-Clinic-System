<?php

namespace Modules\Inventory\Database\Seeders\Item;

use Illuminate\Database\Seeder;
use Modules\Inventory\Enums\ItemTypeEnum;
use Modules\Inventory\Enums\ItemUnitEnum;
use Modules\Inventory\Models\Item;

class ItemDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // ==========================================
            // 1. المستهلكات الطبية (CONSUMABLE - سعر البيع 0)
            // تُباع فقط داخل الجلسات عبر جدول الخدمات
            // ==========================================

            // --- Love ---
            ['name' => 'Neofound exo - اكسسوزوم', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Yaqoot - ياقوت', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Amber saffron - عنبر', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- Mg ---
            ['name' => 'Skin booster - اسكين بوستر (Mg)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Brightening - تفتيح (Mg)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Renovate - اكسسوزوم (Mg)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Hair Duta - ديوتا (Mg)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Hair complex - خلايا جذعية (Mg)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- Fusion ---
            ['name' => 'f. mesomatrix - ندبات (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'f- Radince - نضارة (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'F-xfc + face - نضارة (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'f-xf - نضارة (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'f-eye contour - هالات (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'F - perfet lips - توريد شفايف (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'F - hair - ميزو ثيرابي شعر (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'f- hair men - خلايا جزعية شعر (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'F - mela clear - تفتيح (Fusion)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- Apera ---
            ['name' => 'Hair - matrix - خلايا جذعية (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Scar off - ندبات (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Acne off - حب شباب (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Glutasafe - تفتيح (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Renew - نضارة (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Firming cocktail - استرتش مارك (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Rejelips - توريد (Apera)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- NJ ---
            ['name' => 'Acne free - حب الشباب (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Anti Scar - ندبات (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Skin booster - اسكين بوستر (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Whitenig cocktail - تفتيح (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Anti - agiug - تجاعيد (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Fair sten cell - خلايا جذعية (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Procopil - ميزو ثيرابي شعر (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Duta steniol - ديوتا (NJ)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- Skinium & Others ---
            ['name' => 'Skin booster - اسكين بوستر (Skinium)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Exo hair - اكسسوزوم شعر (Skinderma)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Exo whitening - اكسسوزوم تفتيح (Skinderma)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Pinky lips - توريد (Seda derm)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::ML->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- Stylage (الفيلر والتقشير والبوتوكس اللي موجودة في فاتورة المشتريات) ---
            ['name' => 'XI - فيلر (سرنجة 1 مللي)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'xxl - فيلر (سرنجة 1 مللي)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Korean Botox - بوتوكس كوري (فايل كامل)', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Cortisone Injection - حقن كرتيزون', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Bikini Whitening Session - جلسة تفتيح البكيني', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value], // يفضل مستقبلاً تغيير اسمها لـ "مادة تفتيح البكيني"
            ['name' => 'Amelan Peel - تقشير اميلان', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Remelan Peel - تقشير ريميلان', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'Peel System - تقشير بيل سيستم', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // --- مستهلكات وأدوات ---
            ['name' => 'مناديل مطبخ / رولات', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'سرنجات 5 سم', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'سرنجات 10 سم', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'سرنجات انسولين', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'سنون ميزو', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],
            ['name' => 'كاسات حجامة', 'selling_price' => 0.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::CONSUMABLE->value],

            // ==========================================
            // 2. منتجات الصيدلية والتجزئة (RETAILABLE - لها سعر بيع مباشر)
            // ==========================================
            ['name' => 'أعشاب من مورينجا', 'selling_price' => 150.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'أعشاب من مانجو', 'selling_price' => 150.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'أعشاب من جارسينيا', 'selling_price' => 150.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'أعشاب من لا فنتريكس', 'selling_price' => 50.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'أعشاب من أشواجاندا', 'selling_price' => 150.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'قهوة كوكوت', 'selling_price' => 180.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'ألترا جرين كافيه', 'selling_price' => 180.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'فوار مـ بيور', 'selling_price' => 290.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'كبسولات شيو', 'selling_price' => 160.00, 'unit' => ItemUnitEnum::STRIP->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'كريم فيلر', 'selling_price' => 260.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'لبان', 'selling_price' => 120.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'تركيبات نحافة', 'selling_price' => 140.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
            ['name' => 'أعشاب للإمساك', 'selling_price' => 50.00, 'unit' => ItemUnitEnum::PIECE->value, 'type' => ItemTypeEnum::RETAILABLE->value],
        ];

        foreach ($items as $data) {
            Item::firstOrCreate(
                ['name' => $data['name']],
                [
                    'unit' => $data['unit'],
                    'type' => $data['type'], // حفظ النوع في الداتابيز
                    'selling_price' => $data['selling_price'],
                    'current_stock' => 0,
                    'is_active' => true,
                ]
            );
        }
    }
}
