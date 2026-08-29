<?php

namespace Modules\Setup\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Setup\Models\Service;
use Modules\Inventory\Models\Item;

class ServiceItemDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // مصفوفة الربط: 'اسم المنتج في المخزن' => ['اسم الخدمة' => الكمية المخصومة]
        $mappings = [
            // ================== Love ==================
            'Neofound exo - اكسسوزوم' => [
                'جلسة Neofound exo (اكسسوزوم) - نص مللي' => 0.5,
                'جلسة Neofound exo (اكسسوزوم) - 1 مللي' => 1.0,
            ],
            'Yaqoot - ياقوت' => [
                'جلسة Yaqoot (ياقوت) - نص مللي' => 0.5,
                'جلسة Yaqoot (ياقوت) - 1 مللي' => 1.0,
            ],
            'Amber saffron - عنبر' => [
                'جلسة Amber saffron (عنبر) - نص مللي' => 0.5,
                'جلسة Amber saffron (عنبر) - 1 مللي' => 1.0,
            ],

            // ================== Mg ==================
            'Skin booster - اسكين بوستر (Mg)' => [
                'جلسة Skin booster (اسكين بوستر) - نص مللي (Mg)' => 0.5,
                'جلسة Skin booster (اسكين بوستر) - 1 مللي (Mg)' => 1.0,
            ],
            'Brightening - تفتيح (Mg)' => [
                'جلسة Brightening (تفتيح) - نص مللي (Mg)' => 0.5,
                'جلسة Brightening (تفتيح) - 1 مللي (Mg)' => 1.0,
            ],
            'Renovate - اكسسوزوم (Mg)' => [
                'جلسة Renovate (اكسسوزوم) - نص مللي (Mg)' => 0.5,
                'جلسة Renovate (اكسسوزوم) - 1 مللي (Mg)' => 1.0,
            ],
            'Hair Duta - ديوتا (Mg)' => [
                'جلسة Hair Duta (ديوتا) - نص مللي (Mg)' => 0.5,
                'جلسة Hair Duta (ديوتا) - 1 مللي (Mg)' => 1.0,
            ],
            'Hair complex - خلايا جذعية (Mg)' => [
                'جلسة Hair complex (خلايا جذعية) - نص مللي (Mg)' => 0.5,
                'جلسة Hair complex (خلايا جذعية) - 1 مللي (Mg)' => 1.0,
            ],

            // ================== Fusion ==================
            'f. mesomatrix - ندبات (Fusion)' => [
                'جلسة f. mesomatrix (ندبات) - نص مللي (Fusion)' => 0.5,
                'جلسة f. mesomatrix (ندبات) - 1 مللي (Fusion)' => 1.0,
            ],
            'f- Radince - نضارة (Fusion)' => [
                'جلسة f- Radince (نضارة) - نص مللي (Fusion)' => 0.5,
                'جلسة f- Radince (نضارة) - 1 مللي (Fusion)' => 1.0,
            ],
            'F-xfc + face - نضارة (Fusion)' => [
                'جلسة F-xfc + face (نضارة) - نص مللي (Fusion)' => 0.5,
                'جلسة F-xfc + face (نضارة) - 1 مللي (Fusion)' => 1.0,
            ],
            'f-xf - نضارة (Fusion)' => [
                'جلسة f-xf (نضارة) - نص مللي (Fusion)' => 0.5,
                'جلسة f-xf (نضارة) - 1 مللي (Fusion)' => 1.0,
            ],
            'f-eye contour - هالات (Fusion)' => [
                'جلسة f-eye contour (هالات) - نص مللي (Fusion)' => 0.5,
                'جلسة f-eye contour (هالات) - 1 مللي (Fusion)' => 1.0,
            ],
            'F - perfet lips - توريد شفايف (Fusion)' => [
                'جلسة F - perfet lips (توريد شفايف) - نص مللي (Fusion)' => 0.5,
                'جلسة F - perfet lips (توريد شفايف) - 1 مللي (Fusion)' => 1.0,
            ],
            'F - hair - ميزو ثيرابي شعر (Fusion)' => [
                'جلسة F - hair (ميزو شعر) - نص مللي (Fusion)' => 0.5,
                'جلسة F - hair (ميزو شعر) - 1 مللي (Fusion)' => 1.0,
            ],
            'f- hair men - خلايا جزعية شعر (Fusion)' => [
                'جلسة f- hair men (خلايا جذعية شعر) - نص مللي (Fusion)' => 0.5,
                'جلسة f- hair men (خلايا جذعية شعر) - 1 مللي (Fusion)' => 1.0,
            ],
            'F - mela clear - تفتيح (Fusion)' => [
                'جلسة F - mela clear (تفتيح) - نص مللي (Fusion)' => 0.5,
                'جلسة F - mela clear (تفتيح) - 1 مللي (Fusion)' => 1.0,
            ],

            // ================== Apera ==================
            'Hair - matrix - خلايا جذعية (Apera)' => [
                'جلسة Hair - matrix (خلايا جذعية) - نص مللي (Apera)' => 0.5,
                'جلسة Hair - matrix (خلايا جذعية) - 1 مللي (Apera)' => 1.0,
            ],
            'Scar off - ندبات (Apera)' => [
                'جلسة Scar off (ندبات) - نص مللي (Apera)' => 0.5,
                'جلسة Scar off (ندبات) - 1 مللي (Apera)' => 1.0,
            ],
            'Acne off - حب شباب (Apera)' => [
                'جلسة Acne off (حب شباب) - نص مللي (Apera)' => 0.5,
                'جلسة Acne off (حب شباب) - 1 مللي (Apera)' => 1.0,
            ],
            'Glutasafe - تفتيح (Apera)' => [
                'جلسة Glutasafe (تفتيح) - نص مللي (Apera)' => 0.5,
                'جلسة Glutasafe (تفتيح) - 1 مللي (Apera)' => 1.0,
            ],
            'Renew - نضارة (Apera)' => [
                'جلسة Renew (نضارة) - نص مللي (Apera)' => 0.5,
                'جلسة Renew (نضارة) - 1 مللي (Apera)' => 1.0,
            ],
            'Firming cocktail - استرتش مارك (Apera)' => [
                'جلسة Firming cocktail (استرتش مارك) - نص مللي (Apera)' => 0.5,
                'جلسة Firming cocktail (استرتش مارك) - فايل كامل (Apera)' => 1.0,
            ],
            'Rejelips - توريد (Apera)' => [
                'جلسة Rejelips (توريد) - نص مللي (Apera)' => 0.5,
                'جلسة Rejelips (توريد) - 1 مللي (Apera)' => 1.0,
            ],

            // ================== NJ ==================
            'Acne free - حب الشباب (NJ)' => [
                'جلسة Acne free (حب الشباب) - نص مللي (NJ)' => 0.5,
                'جلسة Acne free (حب الشباب) - 1 مللي (NJ)' => 1.0,
            ],
            'Anti Scar - ندبات (NJ)' => [
                'جلسة Anti Scar (ندبات) - نص مللي (NJ)' => 0.5,
                'جلسة Anti Scar (ندبات) - 1 مللي (NJ)' => 1.0,
            ],
            'Skin booster - اسكين بوستر (NJ)' => [
                'جلسة Skin booster (اسكين بوستر) - نص مللي (NJ)' => 0.5,
                'جلسة Skin booster (اسكين بوستر) - 1 مللي (NJ)' => 1.0,
            ],
            'Whitenig cocktail - تفتيح (NJ)' => [
                'جلسة Whitenig cocktail (تفتيح) - نص مللي (NJ)' => 0.5,
                'جلسة Whitenig cocktail (تفتيح) - 1 مللي (NJ)' => 1.0,
            ],
            'Anti - agiug - تجاعيد (NJ)' => [
                'جلسة Anti - agiug (تجاعيد) - نص مللي (NJ)' => 0.5,
                'جلسة Anti - agiug (تجاعيد) - 1 مللي (NJ)' => 1.0,
            ],
            'Fair sten cell - خلايا جذعية (NJ)' => [
                'جلسة Fair sten cell (خلايا جذعية) - نص مللي (NJ)' => 0.5,
                'جلسة Fair sten cell (خلايا جذعية) - 1 مللي (NJ)' => 1.0,
            ],
            'Procopil - ميزو ثيرابي شعر (NJ)' => [
                'جلسة Procopil (ميزو شعر) - نص مللي (NJ)' => 0.5,
                'جلسة Procopil (ميزو شعر) - 1 مللي (NJ)' => 1.0,
            ],
            'Duta steniol - ديوتا (NJ)' => [
                'جلسة Duta steniol (ديوتا شعر) - نص مللي (NJ)' => 0.5,
                'جلسة Duta steniol (ديوتا شعر) - 1 مللي (NJ)' => 1.0,
            ],

            // ================== Skinium & Skinderma & Seda derm ==================
            'Skin booster - اسكين بوستر (Skinium)' => [
                'جلسة Skin booster (اسكين بوستر) - نص مللي (Skinium)' => 0.5,
                'جلسة Skin booster (اسكين بوستر) - 1 مللي (Skinium)' => 1.0,
            ],
            'Exo hair - اكسسوزوم شعر (Skinderma)' => [
                'جلسة Exo hair (اكسسوزوم شعر) - نص مللي (Skinderma)' => 0.5,
                'جلسة Exo hair (اكسسوزوم شعر) - 1 مللي (Skinderma)' => 1.0,
            ],
            'Exo whitening - اكسسوزوم تفتيح (Skinderma)' => [
                'جلسة Exo whitening (اكسسوزوم تفتيح) - نص مللي (Skinderma)' => 0.5,
                'جلسة Exo whitening (اكسسوزوم تفتيح) - 1 مللي (Skinderma)' => 1.0,
            ],
            'Pinky lips - توريد (Seda derm)' => [
                'جلسة Pinky lips (توريد) - نص مللي (Seda derm)' => 0.5,
                'جلسة Pinky lips (توريد) - 1 مللي (Seda derm)' => 1.0,
            ],

            // ================== Stylage & Others (Filler, Botox, Peeling) ==================
            'XI - فيلر (سرنجة 1 مللي)' => [
                'فيلر XI (1 ملي سرنجة)' => 1.0,
                'فيلر XI (2 ملي سرنجتين)' => 2.0, // هيخصم 2 من المخزن
            ],
            'xxl - فيلر (سرنجة 1 مللي)' => [
                'فيلر XXL (1 ملي سرنجة)' => 1.0,
                'فيلر XXL (2 ملي سرنجتين)' => 2.0,
            ],
            'Korean Botox - بوتوكس كوري (فايل كامل)' => [
                'بوتوكس كوري (نص فايل)' => 0.5,
                'بوتوكس كوري (فايل كامل)' => 1.0,
                'حقن بوتوكس كوري' => 1.0,
            ],
            'Cortisone Injection - حقن كرتيزون' => [
                'حقن كرتيزون' => 1.0,
            ],
            'Bikini Whitening Session - جلسة تفتيح البكيني' => [
                'جلسة تفتيح البكيني (جلسة واحدة)' => 1.0,
                'جلسة تفتيح البكيني (جلستين)' => 2.0,
                'جلسة تفتيح البكيني (3 جلسات)' => 3.0,
            ],
            'Amelan Peel - تقشير اميلان' => [
                'تقشير اميلان (بره العروض)' => 1.0,
            ],
            'Remelan Peel - تقشير ريميلان' => [
                'تقشير ريميلان (بره العروض)' => 1.0,
            ],
            'Peel System - تقشير بيل سيستم' => [
                'تقشير بيل سيستم (بره العروض)' => 1.0,
            ],
        ];

        // اللوب السحري: بيلف على كل مادة ويربطها بخدماتها
        foreach ($mappings as $itemName => $services) {
            $item = Item::where('name', $itemName)->first();

            if (!$item) {
                continue; // لو المنتج مش موجود في المخزن هيكمل من غير ما يضرب إيرور
            }

            foreach ($services as $serviceName => $quantity) {
                $service = Service::where('name', $serviceName)->first();

                if ($service) {
                    // ربط الخدمة بالمنتج مع تحديد الكمية المستهلكة (بدون تكرار)
                    $service->items()->syncWithoutDetaching([
                        $item->id => ['quantity' => $quantity]
                    ]);
                }
            }
        }
    }
}
