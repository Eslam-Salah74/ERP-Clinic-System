<?php

namespace Modules\Inventory\Services\PurchaseInvoice;

use App\Support\API;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Filters\PurchaseInvoice\PurchaseInvoiceFilter;
use Modules\Inventory\Http\Resources\PurchaseInvoice\PurchaseInvoiceResource;
use Modules\Inventory\Models\Item;
use Modules\Inventory\Models\PurchaseInvoice;
use Modules\Inventory\Models\PurchaseInvoiceItem;

class PurchaseInvoiceService
{
    public function index($request, PurchaseInvoiceFilter $filter)
    {
        $data = PurchaseInvoice::with(['supplier', 'items.item'])->filter($filter)->latest()->paginate(10);
        return API::newInstance()->isOk('Data retrieved successfully')->setData(PurchaseInvoiceResource::collection($data))->build();
    }

    // public function store($request)
    // {
    //     return DB::transaction(function () use ($request) {
    //         $validated = $request->validated();

    //         $totalAmount = 0;
    //         foreach ($validated['items'] as $itemData) {
    //             $totalAmount += $itemData['quantity'] * $itemData['purchase_price'];
    //         }

    //         $invoice = PurchaseInvoice::create([
    //             'supplier_id' => $validated['supplier_id'],
    //             'total_amount' => $totalAmount,
    //             'notes' => $validated['notes'] ?? null,
    //         ]);

    //         foreach ($validated['items'] as $itemData) {
    //             $quantity = $itemData['quantity'];
    //             $price = $itemData['purchase_price'];
    //             $itemId = $itemData['item_id'];

    //             PurchaseInvoiceItem::create([
    //                 'purchase_invoice_id' => $invoice->id,
    //                 'item_id' => $itemId,
    //                 'quantity' => $quantity,
    //                 'purchase_price' => $price,
    //                 'total_price' => $quantity * $price,
    //             ]);

    //             // تحديث رصيد المخزن تلقائياً
    //             $item = Item::findOrFail($itemId);
    //             $item->increment('current_stock', $quantity);
    //         }

    //         return API::newInstance()
    //             ->isCreated('Purchase invoice created successfully')
    //             ->setData(new PurchaseInvoiceResource($invoice->load('items.item', 'supplier')))
    //             ->build();
    //     });
    // }

    public function store($requestOrData)
    {
        return DB::transaction(function () use ($requestOrData) {
            // لو جاي من كائن Request حقيقي، خذ الـ validated، ولو جاي من مصفوفة (من السييدر) استخدها مباشرة
            $validated = is_object($requestOrData) && method_exists($requestOrData, 'validated')
                ? $requestOrData->validated()
                : $requestOrData;

            $totalAmount = 0;
            foreach ($validated['items'] as $itemData) {
                $totalAmount += $itemData['quantity'] * $itemData['purchase_price'];
            }

            $invoice = PurchaseInvoice::create([
                'supplier_id' => $validated['supplier_id'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $itemData) {
                $quantity = $itemData['quantity'];
                $price = $itemData['purchase_price'];
                $itemId = $itemData['item_id'];

                PurchaseInvoiceItem::create([
                    'purchase_invoice_id' => $invoice->id,
                    'item_id' => $itemId,
                    'quantity' => $quantity,
                    'purchase_price' => $price,
                    'total_price' => $quantity * $price,
                ]);

                // تحديث رصيد المخزن تلقائياً
                $item = Item::findOrFail($itemId);
                $item->increment('current_stock', $quantity);
            }

            return API::newInstance()
                ->isCreated('Purchase invoice created successfully')
                ->setData(new PurchaseInvoiceResource($invoice->load('items.item', 'supplier')))
                ->build();
        });
    }

    public function show($id)
    {
        $record = PurchaseInvoice::with(['supplier', 'items.item'])->find($id);
        if (!$record) {
            return API::newInstance()->isError('Record not found')->build();
        }
        return API::newInstance()->isOk('Data retrieved successfully')->setData(new PurchaseInvoiceResource($record))->build();
    }

    public function update($id, $request)
    {
        $record = PurchaseInvoice::findOrFail($id);
        $record->update($request->validated());
        return API::newInstance()->isOk('Updated successfully')->setData(new PurchaseInvoiceResource($record->load('items.item', 'supplier')))->build();
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = PurchaseInvoice::with('items')->findOrFail($id);

            // عند حذف الفاتورة، يُفضل خصم الكميات التي تمت إضافتها مسبقاً من المخزن للحفاظ على الدقة
            foreach ($record->items as $invoiceItem) {
                $item = Item::find($invoiceItem->item_id);
                if ($item) {
                    $item->decrement('current_stock', $invoiceItem->quantity);
                }
            }

            $record->delete();
            return API::newInstance()->isOk('Deleted successfully')->build();
        });
    }
}
