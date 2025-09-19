<?php

namespace Modules\Auth\Services;

use App\Constants\LogConstant;
use App\Helpers\LogHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Entities\Permission;

class PermissionService
{
    public function all()
    {
        return Cache::rememberForever('permissions', function () {
            return Permission::all();
        });
    }

    public function create(array $data): RedirectResponse
    {
        try {
            Permission::create($data);
            return redirect()->route('permission.index')->with(['message' => 'Permission successfully created']);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => LogConstant::EXCEPTION_GENERAL
            ]);
            return redirect()->back()->withInput($data)->withErrors(['message' => $exception->getMessage()]);
        }
    }

    public function update(array $data, Permission $permission): RedirectResponse
    {
        try {
            $permission->update($data);
            return redirect()->route('permission.index')->with(['message' => 'Permission successfully updated']);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => LogConstant::EXCEPTION_GENERAL
            ]);
            return redirect()->back()->withInput($data)->withErrors(['message' => $exception->getMessage()]);
        }
    }

    public function getDataTable(): JsonResponse
    {
        return datatables()->eloquent(
            Permission::query()
        )
            ->addColumn('actions', function(Permission $permission) {
                return "<a href='" . route('permission.edit', $permission->id). "' class='btn btn-primary'><i class='fa fa-edit'></i></a>";
            })
            ->rawColumns(['actions'])
            ->toJson();
    }
}
