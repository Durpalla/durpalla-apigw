<?php

namespace App\Http\Controllers\Api\v1;

use App\Constants\AppConst;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Repository\Interfaces\CancellationRepositoryInterface;
use App\Services\CancellationService;

class ApiCancellationController extends Controller
{
    protected $cancellation;
    protected $cancellationRepository;
    protected $success = 200;

    public function __construct(CancellationRepositoryInterface $cancellationRepository, CancellationService $cancellationService)
    {
        $this->cancellation = $cancellationService;
        $this->cancellationRepository = $cancellationRepository;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        $cancellations = $this->cancellation->getMyCancellations();
        return response()->json(['success' => true, 'message' => '', 'data' => $cancellations], $this->success);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'booking_id' => 'required|integer|exists:bookings,id',
            'items' => 'required|array|min:1',
            'items.*' => 'integer',
        ]);

        $data = ['success' => false, 'message' => __('Your cancellation request failed')];
        try{
            DB::transaction(function() use($request, &$data) {
                $params = [
                    'items' => $request->items,
                    'booking_id' => $request->booking_id
                ];

                $this->cancellation->cancelBooking($params);
                $data['success'] = true;
                $data['message'] = __('Your cancellation request success');
            }, 2);
        } catch( \Exception $e ) {
            $data['message'] = $e->getMessage();
        }

        return response()->json($data, $this->success);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        return response()->json(['success' => true, 'data' => $this->cancellation->details($id)], $this->success);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $data = ['success' => false, 'message' => __('Cannot handle your request')];
        try {
            DB::transaction(function() use($request, &$data, $id) {
                $user = auth()->user();
                $cancellation = $this->cancellationRepository->get($id);
                if($cancellation->user_id == $user->id && $cancellation->status == AppConst::CANCELLATION_APPROVED) {
                    $this->cancellation->confirm($cancellation);
//                    call_user_func(array(CancellationService::class, $request->action_type), $cancellation);
                } else {
                    throw new \Exception(trans('You cannot take action on other officers task / unapproved request'));
                }
                $data['success'] = true;
                $data['message'] = __('Your request has been successfully proceed');
            }, 2);
        } catch (\Exception $e) {
            $data['message'] = $e->getMessage();
        }

        return response()->json($data, $this->success);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        //
    }
}
