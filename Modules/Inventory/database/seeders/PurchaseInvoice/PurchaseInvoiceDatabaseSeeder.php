<?php

namespace Modules\Inventory\Database\Seeders\PurchaseInvoice;

use Illuminate\Database\Seeder;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\Supplier;
use Modules\Inventory\Services\PurchaseInvoice\PurchaseInvoiceService;

class PurchaseInvoiceDatabaseSeeder extends Seeder
{
    protected $purchaseInvoiceService;

    // حقن السيرفس مباشرة في السييدر
    public function __construct(PurchaseInvoiceService $purchaseInvoiceService)
    {
        $this->purchaseInvoiceService = $purchaseInvoiceService;
    }

    public function run(): void
    {
        // خريطة الموردين وأصنافهم مع كميات الشراء وأسعار الشراء الافتتاحية
        $invoicesData = [
            'Love' => [
                ['name' => 'Neofound exo - اكسسوزوم', 'purchase_price' => 1800, 'qty' => 5],
                ['name' => 'Yaqoot - ياقوت', 'purchase_price' => 2000, 'qty' => 5],
                ['name' => 'Amber saffron - عنبر', 'purchase_price' => 1700, 'qty' => 5],
            ],
            'Mg' => [
                ['name' => 'Skin booster - اسكين بوستر (Mg)', 'purchase_price' => 1400, 'qty' => 5],
                ['name' => 'Brightening - تفتيح (Mg)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'Renovate - اكسسوزوم (Mg)', 'purchase_price' => 1800, 'qty' => 5],
                ['name' => 'Hair Duta - ديوتا (Mg)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'Hair complex - خلايا جذعية (Mg)', 'purchase_price' => 800, 'qty' => 5],
            ],
            'Fusion' => [
                ['name' => 'f. mesomatrix - ندبات (Fusion)', 'purchase_price' => 800, 'qty' => 5],
                ['name' => 'f- Radince - نضارة (Fusion)', 'purchase_price' => 650, 'qty' => 5],
                ['name' => 'F-xfc + face - نضارة (Fusion)', 'purchase_price' => 650, 'qty' => 5],
                ['name' => 'f-xf - نضارة (Fusion)', 'purchase_price' => 550, 'qty' => 5],
                ['name' => 'f-eye contour - هالات (Fusion)', 'purchase_price' => 700, 'qty' => 5],
                ['name' => 'F - perfet lips - توريد شفايف (Fusion)', 'purchase_price' => 700, 'qty' => 5],
                ['name' => 'F - hair - ميزو ثيرابي شعر (Fusion)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'f- hair men - خلايا جزعية شعر (Fusion)', 'purchase_price' => 800, 'qty' => 5],
                ['name' => 'F - mela clear - تفتيح (Fusion)', 'purchase_price' => 800, 'qty' => 5],
            ],
            'Apera' => [
                ['name' => 'Hair - matrix - خلايا جذعية (Apera)', 'purchase_price' => 700, 'qty' => 5],
                ['name' => 'Scar off - ندبات (Apera)', 'purchase_price' => 550, 'qty' => 5],
                ['name' => 'Acne off - حب شباب (Apera)', 'purchase_price' => 550, 'qty' => 5],
                ['name' => 'Glutasafe - تفتيح (Apera)', 'purchase_price' => 400, 'qty' => 5],
                ['name' => 'Renew - نضارة (Apera)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'Firming cocktail - استرتش مارك (Apera)', 'purchase_price' => 250, 'qty' => 5],
                ['name' => 'Rejelips - توريد (Apera)', 'purchase_price' => 700, 'qty' => 5],
            ],
            'NJ' => [
                ['name' => 'Acne free - حب الشباب (NJ)', 'purchase_price' => 550, 'qty' => 5],
                ['name' => 'Anti Scar - ندبات (NJ)', 'purchase_price' => 650, 'qty' => 5],
                ['name' => 'Skin booster - اسكين بوستر (NJ)', 'purchase_price' => 1700, 'qty' => 5],
                ['name' => 'Whitenig cocktail - تفتيح (NJ)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'Anti - agiug - تجاعيد (NJ)', 'purchase_price' => 400, 'qty' => 5],
                ['name' => 'Fair sten cell - خلايا جذعية (NJ)', 'purchase_price' => 800, 'qty' => 5],
                ['name' => 'Procopil - ميزو ثيرابي شعر (NJ)', 'purchase_price' => 450, 'qty' => 5],
                ['name' => 'Duta steniol - ديوتا (NJ)', 'purchase_price' => 450, 'qty' => 5],
            ],
            'Skinium' => [
                ['name' => 'Skin booster - اسكين بوستر (Skinium)', 'purchase_price' => 1700, 'qty' => 5],
            ],
            'Skinderma' => [
                ['name' => 'Exo hair - اكسسوزوم شعر (Skinderma)', 'purchase_price' => 1900, 'qty' => 5],
                ['name' => 'Exo whitening - اكسسوزوم تفتيح (Skinderma)', 'purchase_price' => 1900, 'qty' => 5],
            ],
            'Seda derm' => [
                ['name' => 'Pinky lips - توريد (Seda derm)', 'purchase_price' => 450, 'qty' => 5],
            ],
            'Stylage' => [
                ['name' => 'XI - فيلر (سرنجة 1 مللي)', 'purchase_price' => 6000, 'qty' => 5],
                ['name' => 'xxl - فيلر (سرنجة 1 مللي)', 'purchase_price' => 6800, 'qty' => 5],
                ['name' => 'Korean Botox - بوتوكس كوري (فايل كامل)', 'purchase_price' => 4800, 'qty' => 5],
                ['name' => 'Cortisone Injection - حقن كرتيزون', 'purchase_price' => 250, 'qty' => 5],
                ['name' => 'Bikini Whitening Session - جلسة تفتيح البكيني', 'purchase_price' => 1100, 'qty' => 5],
                ['name' => 'Amelan Peel - تقشير اميلان', 'purchase_price' => 2300, 'qty' => 5],
                ['name' => 'Remelan Peel - تقشير ريميلان', 'purchase_price' => 1500, 'qty' => 5],
                ['name' => 'Peel System - تقشير بيل سيستم', 'purchase_price' => 1100, 'qty' => 5],
            ],
        ];

        foreach ($invoicesData as $supplierName => $invoiceItems) {
            $supplier = Supplier::where('name', $supplierName)->first();

            if (!$supplier) {
                continue;
            }

            $formattedItems = [];

            foreach ($invoiceItems as $data) {
                $item = Item::where('name', $data['name'])->first();
                if ($item) {
                    $formattedItems[] = [
                        'item_id' => $item->id,
                        'quantity' => $data['qty'],
                        'purchase_price' => $data['purchase_price'],
                    ];
                }
            }

            if (!empty($formattedItems)) {
                // بناء هيكل البيانات المحاكي للطلب (Request) واستدعاء دالة الـ store من السيرفس مباشرة
                $payload = [
                    'supplier_id' => $supplier->id,
                    'notes' => 'فاتورة مشتريات افتتاحية لشركة / مورد: ' . $supplierName,
                    'items' => $formattedItems,
                ];

                // استخدام السيرفس الرسمية لحفظ الفاتورة وتحديث المخزن أوتوماتيكياً
                $this->purchaseInvoiceService->store($payload);
            }
        }
    }
}
