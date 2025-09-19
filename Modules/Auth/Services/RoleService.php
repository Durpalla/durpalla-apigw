<?php

namespace Modules\Auth\Services;

use App\Constants\LogConstant;
use App\Events\RoleUpdatedEvent;
use App\Helpers\LogHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Entities\Permission;
use Modules\Auth\Entities\Role;

class RoleService
{
    public function all()
    {
        Cache::forget('roles');
        return Cache::rememberForever('roles', function () {
            return Role::all();
        });
    }

    public function create(array $data): RedirectResponse
    {
        try {
            $role = Role::create($data + ['guard_name' => 'web']);
            $role->syncPermissions(
                Permission::whereIn('id', $data['permission'])->get()
            );
            return redirect()->route('role.index')->with(['status' => true, 'message' => __('Role successfully created')]);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => LogConstant::EXCEPTION_GENERAL
            ]);
            return redirect()->back()->withInput($data)->with(['status' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function update(array $data, Role $role): RedirectResponse
    {
        try {
            $role->update($data);
            $role->syncPermissions(
                Permission::whereIn('id', $data['permission'])->get()
            );
            Cache::forget('spatie.permission.cache');
            return redirect()->route('role.index')->with(['status' => true,'message' => __('Role successfully updated')]);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => LogConstant::EXCEPTION_GENERAL
            ]);
            return redirect()->back()->withInput($data)->with(['status' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function getDataTable(): JsonResponse
    {
        return datatables()->eloquent(
            Role::with('permissions')
                ->where('guard_name', 'web')
        )
            ->addColumn('permissions', function (Role $role) {
                $str = '';
                foreach($role->permissions as $permission) {
                    $str .= " <span class='badge bg-info p-md-l'>{$permission->name}</span>";
                }
                return $str;
            })
            ->addColumn('actions', function(Role $role) {
                if($role->isEditable()) {
                    return "<a href='" . route('role.edit', $role->id) . "' class='btn btn-primary'><i class='fa fa-edit'></i></a>";
                } else {
                    return '';
                }
            })
            ->rawColumns(['actions', 'permissions'])
            ->toJson();
    }

    public function getPermissions(): array
    {
        $array = [];
        $permissions = (new PermissionService())->all();
        foreach ($permissions as $q) {
            $param = explode('-', $q->name);
            $array[$param[0]][] = $q;
        }
        return $array;
    }
}
