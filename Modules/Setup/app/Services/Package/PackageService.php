<?php

namespace Modules\Setup\Services\Package;

use App\Support\API;
use Illuminate\Support\Facades\DB;
use Modules\Setup\Filters\Package\PackageFilter;
use Modules\Setup\Http\Resources\Package\PackageResource;
use Modules\Setup\Models\Package;

class PackageService
{
    public function index($request, PackageFilter $filter)
    {
        $perPage = $request->get('per_page', 10);
        $query = Package::with(['department', 'items.service', 'items.product'])
            ->filter($filter)
            ->reorder()
            ->orderBy('id', 'desc');

        $data = ($request->boolean('all') || $request->get('paginate') === 'false' || (string) $perPage === '-1')
            ? $query->get()
            : $query->paginate((int) $perPage);

        return API::newInstance()
            ->isOk('Packages retrieved successfully')
            ->setData(PackageResource::collection($data))
            ->build();
    }

    public function store($request)
    {
        return DB::transaction(function () use ($request) {
            $validated = $request->validated();
            $itemsData = $validated['items'] ?? [];
            unset($validated['items']);

            $package = Package::create($validated);

            foreach ($itemsData as $item) {
                $package->items()->create($item);
            }

            return API::newInstance()
                ->isCreated('Package created successfully')
                ->setData(new PackageResource($package->load(['department', 'items.service', 'items.product'])))
                ->build();
        });
    }

    public function show($id)
    {
        $package = Package::with(['department', 'items.service', 'items.product'])->find($id);
        if (!$package) {
            return API::newInstance()->isError('Package not found')->build();
        }

        return API::newInstance()
            ->isOk('Package retrieved successfully')
            ->setData(new PackageResource($package))
            ->build();
    }

    public function update($id, $request)
    {
        return DB::transaction(function () use ($id, $request) {
            $package = Package::find($id);
            if (!$package) {
                return API::newInstance()->isError('Package not found')->build();
            }

            $validated = $request->validated();
            $itemsData = $validated['items'] ?? null;
            unset($validated['items']);

            $package->update($validated);

            if ($itemsData !== null) {
                $package->items()->delete();
                foreach ($itemsData as $item) {
                    $package->items()->create($item);
                }
            }

            return API::newInstance()
                ->isOk('Package updated successfully')
                ->setData(new PackageResource($package->load(['department', 'items.service', 'items.product'])))
                ->build();
        });
    }

    public function destroy($id)
    {
        $package = Package::find($id);
        if (!$package) {
            return API::newInstance()->isError('Package not found')->build();
        }

        $package->delete();

        return API::newInstance()->isOk('Package deleted successfully')->build();
    }
}
