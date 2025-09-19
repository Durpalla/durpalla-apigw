<?php

namespace Modules\Auth\Http\Controllers;

use App\Helpers\CommonHelper;
use App\Helpers\LogHelper;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Auth\Entities\UserAccessBlock;
use Modules\Auth\Http\Requests\UserAccessUnblockRequest;

class UserAccessBlockController extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            return datatables()->eloquent(
                UserAccessBlock::filter($request)
            )
                ->addColumn('action', function ($accessBlock) {
                    $str = '';
                    if (CommonHelper::hasPermission(['block-access-action'])) {
                        if ($accessBlock->unblocked_at->gte(now()) && $accessBlock->deleted_at == null) {
                            $str .= '<button class="btn btn-success btn-xs unblockAccess" data-id="' . $accessBlock->id . '" data-action="' . route('access-block.update', $accessBlock->id) . '"><i class="fa fa-check"></i> Unblock</button>';
                        }
                    }
                    return $str;
                })
                ->rawColumns(['action'])
                ->toArray();
        }
        return $this->themedView('auth::user.access.index', [
            'title' => 'User Access Block'
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        //
    }

    public function update(UserAccessUnblockRequest $request, UserAccessBlock $accessBlock): JsonResponse
    {
        $data = ['status' => true, 'label' => 'info', 'message' => __('Failed to unblock user')];
        try {
            $accessBlock->update($request->validated() +
                [
                    'is_blocked' => false,
                    'deleted_at' => now()->toDateTimeString(),
                    'unblocked_by' => $request->user()->id
                ]
            );
            $data['status'] = true;
            $data['label'] = 'success';
            $data['message'] = __('User unblocked successfully');
        } catch (\Exception $exception) {
            LogHelper::exception($exception, [
                'keyword' => 'USER_ACCESS_UNBLOCK_EXCEPTION',
            ]);
        }
        return response()->json($data);
    }

    public function destroy($id)
    {
        //
    }
}
