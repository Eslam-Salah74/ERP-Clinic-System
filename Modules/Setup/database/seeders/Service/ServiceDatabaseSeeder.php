<?php

namespace Modules\Setup\Database\Seeders\Service;

use Illuminate\Database\Seeder;
use Modules\Setup\Enums\ServiceTypeEnum;
use Modules\Setup\Models\Department;
use Modules\Setup\Models\Service;

class ServiceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $dermatology = Department::where('name', 'جلدية وليزر')->first();
        $physiotherapy = Department::where('name', 'علاج طبيعي')->first();
        $nutrition = Department::where('name', 'تغذية علاجية')->first();

        $services = [
            // ==========================================
            // 1. خدمات الجلدية والتجميل والأجهزة والتقشير
            // ==========================================
            ['name' => 'كشف جلدية', 'department_id' => $dermatology?->id, 'price' => 120.00, 'type' => ServiceTypeEnum::CONSULTATION->value],
            ['name' => 'جهاز فركشنال', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'جهاز ديرما بن', 'department_id' => $dermatology?->id, 'price' => 300.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'جلسة مورفيس', 'department_id' => $dermatology?->id, 'price' => 1200.00, 'type' => ServiceTypeEnum::DEVICE->value],

            // --- الفيلر والبوتوكس وحقن الكرتيزون (Stylage) ---
            ['name' => 'فيلر XI (1 ملي سرنجة)', 'department_id' => $dermatology?->id, 'price' => 7700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'فيلر XI (2 ملي سرنجتين)', 'department_id' => $dermatology?->id, 'price' => 15000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'فيلر XXL (1 ملي سرنجة)', 'department_id' => $dermatology?->id, 'price' => 8500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'فيلر XXL (2 ملي سرنجتين)', 'department_id' => $dermatology?->id, 'price' => 16500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'بوتوكس كوري (نص فايل)', 'department_id' => $dermatology?->id, 'price' => 3500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'بوتوكس كوري (فايل كامل)', 'department_id' => $dermatology?->id, 'price' => 6000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'حقن كرتيزون', 'department_id' => $dermatology?->id, 'price' => 350.00, 'type' => ServiceTypeEnum::SESSION->value],

            // --- جلسات تفتيح البكيني والتقشير ---
            ['name' => 'جلسة تفتيح البكيني (جلسة واحدة)', 'department_id' => $dermatology?->id, 'price' => 1500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة تفتيح البكيني (جلستين)', 'department_id' => $dermatology?->id, 'price' => 2500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة تفتيح البكيني (3 جلسات)', 'department_id' => $dermatology?->id, 'price' => 3000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تقشير اميلان (بره العروض)', 'department_id' => $dermatology?->id, 'price' => 3000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تقشير ريميلان (بره العروض)', 'department_id' => $dermatology?->id, 'price' => 2000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تقشير بيل سيستم (بره العروض)', 'department_id' => $dermatology?->id, 'price' => 1500.00, 'type' => ServiceTypeEnum::SESSION->value],

            // --- جلسات الميزوثيرابي والكوكتيلات (نص مللي ومللي) ---
            // Love
            ['name' => 'جلسة Neofound exo (اكسسوزوم) - نص مللي', 'department_id' => $dermatology?->id, 'price' => 1300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Neofound exo (اكسسوزوم) - 1 مللي', 'department_id' => $dermatology?->id, 'price' => 2300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Yaqoot (ياقوت) - نص مللي', 'department_id' => $dermatology?->id, 'price' => 1400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Yaqoot (ياقوت) - 1 مللي', 'department_id' => $dermatology?->id, 'price' => 2500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Amber saffron (عنبر) - نص مللي', 'department_id' => $dermatology?->id, 'price' => 1200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Amber saffron (عنبر) - 1 مللي', 'department_id' => $dermatology?->id, 'price' => 2200.00, 'type' => ServiceTypeEnum::SESSION->value],

            // Mg
            ['name' => 'جلسة Skin booster (اسكين بوستر) - نص مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Skin booster (اسكين بوستر) - 1 مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 1800.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Brightening (تفتيح) - نص مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Brightening (تفتيح) - 1 مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Renovate (اكسسوزوم) - نص مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 1300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Renovate (اكسسوزوم) - 1 مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 2300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Hair Duta (ديوتا) - نص مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Hair Duta (ديوتا) - 1 مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Hair complex (خلايا جذعية) - نص مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Hair complex (خلايا جذعية) - 1 مللي (Mg)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],

            // Fusion
            ['name' => 'جلسة f. mesomatrix (ندبات) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f. mesomatrix (ندبات) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f- Radince (نضارة) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f- Radince (نضارة) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 800.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F-xfc + face (نضارة) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F-xfc + face (نضارة) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 800.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f-xf (نضارة) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f-xf (نضارة) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f-eye contour (هالات) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f-eye contour (هالات) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 900.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - perfet lips (توريد شفايف) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 650.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - perfet lips (توريد شفايف) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 900.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - hair (ميزو شعر) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - hair (ميزو شعر) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f- hair men (خلايا جذعية شعر) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة f- hair men (خلايا جذعية شعر) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - mela clear (تفتيح) - نص مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة F - mela clear (تفتيح) - 1 مللي (Fusion)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],

            // Apera
            ['name' => 'جلسة Hair - matrix (خلايا جذعية) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Hair - matrix (خلايا جذعية) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 900.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Scar off (ندبات) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Scar off (ندبات) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Acne off (حب شباب) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Acne off (حب شباب) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Glutasafe (تفتيح) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Glutasafe (تفتيح) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Renew (نضارة) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Renew (نضارة) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Firming cocktail (استرتش مارك) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Firming cocktail (استرتش مارك) - فايل كامل (Apera)', 'department_id' => $dermatology?->id, 'price' => 1500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Rejelips (توريد) - نص مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 650.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Rejelips (توريد) - 1 مللي (Apera)', 'department_id' => $dermatology?->id, 'price' => 900.00, 'type' => ServiceTypeEnum::SESSION->value],

            // NJ
            ['name' => 'جلسة Acne free (حب الشباب) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Acne free (حب الشباب) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 700.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Anti Scar (ندبات) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Anti Scar (ندبات) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 800.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Skin booster (اسكين بوستر) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 1300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Skin booster (اسكين بوستر) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 2200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Whitenig cocktail (تفتيح) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Whitenig cocktail (تفتيح) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Anti - agiug (تجاعيد) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 300.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Anti - agiug (تجاعيد) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 550.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Fair sten cell (خلايا جذعية) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Fair sten cell (خلايا جذعية) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Procopil (ميزو شعر) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Procopil (ميزو شعر) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Duta steniol (ديوتا شعر) - نص مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Duta steniol (ديوتا شعر) - 1 مللي (NJ)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],

            // Skinium, Skinderma, Seda derm
            ['name' => 'جلسة Skin booster (اسكين بوستر) - نص مللي (Skinium)', 'department_id' => $dermatology?->id, 'price' => 1500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Skin booster (اسكين بوستر) - 1 مللي (Skinium)', 'department_id' => $dermatology?->id, 'price' => 2200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Exo hair (اكسسوزوم شعر) - نص مللي (Skinderma)', 'department_id' => $dermatology?->id, 'price' => 1200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Exo hair (اكسسوزوم شعر) - 1 مللي (Skinderma)', 'department_id' => $dermatology?->id, 'price' => 2500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Exo whitening (اكسسوزوم تفتيح) - نص مللي (Skinderma)', 'department_id' => $dermatology?->id, 'price' => 1200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Exo whitening (اكسسوزوم تفتيح) - 1 مللي (Skinderma)', 'department_id' => $dermatology?->id, 'price' => 2500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Pinky lips (توريد) - نص مللي (Seda derm)', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Pinky lips (توريد) - 1 مللي (Seda derm)', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::SESSION->value],

            // تنظيف البشرة وإضافات أخرى (نعتبرها جلسات أجهزة أو جلسات عادية)
            ['name' => 'تنظيف بشرة - كوري', 'department_id' => $dermatology?->id, 'price' => 750.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تنظيف بشرة - سيلفر', 'department_id' => $dermatology?->id, 'price' => 250.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تنظيف بشرة - جولد', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'ديرما بلانينج', 'department_id' => $dermatology?->id, 'price' => 100.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'تنظيف أنف فقط (إزالة بثور سوداء)', 'department_id' => $dermatology?->id, 'price' => 100.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'عازل', 'department_id' => $dermatology?->id, 'price' => 25.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'شيفينج', 'department_id' => $dermatology?->id, 'price' => 10.00, 'type' => ServiceTypeEnum::SESSION->value],

            // --- أسعار الليزر خارج العروض (نعتبرها Device) ---
            ['name' => 'ليزر - الشنب', 'department_id' => $dermatology?->id, 'price' => 75.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الشنب والذقن', 'department_id' => $dermatology?->id, 'price' => 150.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - نصف الوجه (شنب ودقن وسوالف)', 'department_id' => $dermatology?->id, 'price' => 200.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الوجه', 'department_id' => $dermatology?->id, 'price' => 300.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الرقبة', 'department_id' => $dermatology?->id, 'price' => 150.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الوجه والرقبة', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - البكيني واللاين', 'department_id' => $dermatology?->id, 'price' => 350.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الاندر ارم', 'department_id' => $dermatology?->id, 'price' => 150.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - نصف الرجل السفلية', 'department_id' => $dermatology?->id, 'price' => 750.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - نصف الرجل العلوية', 'department_id' => $dermatology?->id, 'price' => 850.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الرجل كاملة', 'department_id' => $dermatology?->id, 'price' => 1500.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - نصف الذراع', 'department_id' => $dermatology?->id, 'price' => 450.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الذراع كامل', 'department_id' => $dermatology?->id, 'price' => 850.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - المؤخرة', 'department_id' => $dermatology?->id, 'price' => 500.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - الضهر', 'department_id' => $dermatology?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - البطن', 'department_id' => $dermatology?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - جسم كامل بدون بطن وضهر', 'department_id' => $dermatology?->id, 'price' => 2750.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'ليزر - جسم كامل مع بطن وضهر', 'department_id' => $dermatology?->id, 'price' => 3000.00, 'type' => ServiceTypeEnum::DEVICE->value],

            // ==========================================
            // 2. خدمات التغذية العلاجية وأجهزة التخسيس
            // ==========================================
            ['name' => 'كشف تغذية', 'department_id' => $nutrition?->id, 'price' => 130.00, 'type' => ServiceTypeEnum::CONSULTATION->value],
            ['name' => 'متابعة تغذية', 'department_id' => $nutrition?->id, 'price' => 50.00, 'type' => ServiceTypeEnum::CONSULTATION->value], // أو كشف/متابعة
            ['name' => 'جلسة أجهزة تخسيس - سداسي', 'department_id' => $nutrition?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'جلسة أجهزة تخسيس - ايوني', 'department_id' => $nutrition?->id, 'price' => 1800.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'جلسة أجهزة تخسيس - خماسي', 'department_id' => $nutrition?->id, 'price' => 600.00, 'type' => ServiceTypeEnum::DEVICE->value],
            ['name' => 'حقن ريتا', 'department_id' => $nutrition?->id, 'price' => 4500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'حقن مونجارو', 'department_id' => $nutrition?->id, 'price' => 3500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'حقن ويجوفي', 'department_id' => $nutrition?->id, 'price' => 2500.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'لوستر شد', 'department_id' => $nutrition?->id, 'price' => 1400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'لوستر تخسيس', 'department_id' => $nutrition?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'اسكينيم شد', 'department_id' => $nutrition?->id, 'price' => 1000.00, 'type' => ServiceTypeEnum::SESSION->value],

            // ==========================================
            // 3. خدمات العلاج الطبيعي والحجامة
            // ==========================================
            ['name' => 'كشف علاج طبيعي', 'department_id' => $physiotherapy?->id, 'price' => 120.00, 'type' => ServiceTypeEnum::CONSULTATION->value],
            ['name' => 'جلسة علاج طبيعي (Single)', 'department_id' => $physiotherapy?->id, 'price' => 100.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة علاج طبيعي (Double)', 'department_id' => $physiotherapy?->id, 'price' => 200.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة حجامة - جزء واحد (3 كاسات)', 'department_id' => $physiotherapy?->id, 'price' => 290.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة حجامة - جسم كامل', 'department_id' => $physiotherapy?->id, 'price' => 400.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Recovery', 'department_id' => $physiotherapy?->id, 'price' => 290.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة Recovery حجامة جافة', 'department_id' => $physiotherapy?->id, 'price' => 320.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة مانوال', 'department_id' => $physiotherapy?->id, 'price' => 135.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'جلسة تصريف ليمفاوي - جزء واحد', 'department_id' => $physiotherapy?->id, 'price' => 275.00, 'type' => ServiceTypeEnum::SESSION->value],
            ['name' => 'إبرة علاج طبيعي (للوحدة)', 'department_id' => $physiotherapy?->id, 'price' => 12.00, 'type' => ServiceTypeEnum::SESSION->value],
        ];

        foreach ($services as $service) {
            if ($service['department_id']) {
                Service::firstOrCreate(
                    ['name' => $service['name']],
                    [
                        'department_id' => $service['department_id'],
                        'price' => $service['price'],
                        'type' => $service['type'], // حفظ النوع (consultation, session, device) بشكل دقيق
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
