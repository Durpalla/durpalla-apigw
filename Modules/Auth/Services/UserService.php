<?php

namespace Modules\Auth\Services;

use App\Constants\LogConstant;
use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\Entities\Role;
use Modules\Auth\Entities\User;
use Modules\Auth\Repositories\UserRepositoryInterface;

class UserService
{
    private UserRepositoryInterface $userRepository;

    public function __construct(UserRepositoryInterface $userRepository)
    {
        $this->userRepository = $userRepository;
    }

    public function create(array $data): RedirectResponse
    {
        try {
            DB::transaction(function () use ($data) {
                $user = $this->userRepository->create($data);
                $user->assignRole(Role::find($data['role']));
            });
            return redirect()->route('user.index')->with(['status' => true, 'message' => 'User successfully created']);
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => LogConstant::EXCEPTION_GENERAL
            ]);
            return redirect()->back()->withInput($data)->with(['status' => false, 'message' => $exception->getMessage()]);
        }
    }

    public function update(array $data, $id): RedirectResponse
    {
        try {
            DB::transaction(function () use ($data, $id) {
                $user = $this->userRepository->update(Arr::except($data, ['password']), $id);
                if (isset($data['password'])) {
                    $user->update(['password' => Hash::make($data['password'])]);
                }
                if (array_key_exists('role', $data) && $user) {
                    $user->syncRoles(Role::whereIn('id', [$data['role']])->pluck('name')->toArray());
                }
            });
            return redirect()->route('user.index')->with(['status' => true, 'message' => 'User successfully updated']);
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
            $this->userRepository->getModel()->query()
        )
            ->addColumn('role', function (User $user) {
                return $user->role()->name ?? '---';
            })
            ->addColumn('status', function (User $user) {
                $isChecked = '';
                if($user->isActive()) {
                    $isChecked = 'checked';
                }
                if($user->id !== auth()->user()->id && $user->is_editable) {
                    return '<div class="form-check form-switch">
                      <input class="form-check-input toggleStatus" type="checkbox" value="1" data-id="' . $user->id . '" ' . $isChecked . '>
                    </div>';
                } else {
                    return '<div class="form-check form-switch"><input class="form-check-input" type="checkbox" value="1" ' . $isChecked . ' disabled></div>';
                }
            })
            ->addColumn('actions', function (User $user) {
                $str = '';
                if ($user->id !== auth()->user()->id && $user->is_editable) {
                    $str .= "<a href='" . route('user.edit', $user->id) . "' class='btn btn-primary'><i class='fa fa-edit'></i></a>";
                }
                return $str;
            })
            ->rawColumns(['actions', 'status'])
            ->toJson();
    }

    public function suggestions($request): JsonResponse
    {
        try {
            $data = $this->userRepository->all()
                ->filter(function ($user) use ($request) {
                    $matched = true;
                    if ($request->filled('term')) {
                        $matched = CommonHelper::matchText($user->name, $request->input('term'));
                    }

                    return $matched;
                })
                ->map(function ($user, $key) {
                    return [
                        'id' => $user->id,
                        'text' => $user->name
                    ];
                })->values();
            return response()->json(['results' => $data]);
        } catch (\Exception $exception) {
            return response()->json(['message' => 'No data!', 'results' => []]);
        }
    }
}
