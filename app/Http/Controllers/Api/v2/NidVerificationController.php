<?php

namespace App\Http\Controllers\Api\v2;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\NidVerificationCreateRequest;
use App\Http\Requests\GetNidNumberRequest;
use App\Services\NidVerificationService;
use App\Models\UserMeta;

class NidVerificationController extends Controller
{
    private $service;

    public function __construct(NidVerificationService $verificationService)
    {
        $this->service = $verificationService;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(NidVerificationCreateRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot save attachments')];
        $user = auth()->user();
        try {
            if($request->hasFile('front_side')) {
                $nid_front = 'nid_front_' . $user->id . '.' . $request->file('front_side')->extension();
                $request->file('front_side')->move(public_path('nid'), $nid_front);
            }
            if($request->hasFile('back_side')) {
                $nid_back = 'nid_back_' . $user->id . '.' . $request->file('back_side')->extension();
                $request->file('back_side')->move(public_path('nid'), $nid_back);
            }
            UserMeta::updateOrCreate(['user_id' => $user->id], [
                'nid_no' => $request->nid_no,
                'nid_photo' => $nid_front,
                'nid_back_side' => $nid_back,
                'nid_verified' => 1
            ]);
            $data['success'] = true;
            $data['message'] = __('Your NID submission success');
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * GetNID a newly created resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getNID(GetNidNumberRequest $request): JsonResponse
    {
        $data = ['success' => false, 'message' => __('Cannot capture the NID number, please enter manually')];
        $user = auth()->user();
        try {
            if($request->hasFile('front_side')) {
                $nid_front = 'nid_front_' . $user->id . '.' . $request->file('front_side')->extension();
                $request->file('front_side')->move(public_path('temp'), $nid_front);

                $nidNumber = $this->service->getNidNumber(public_path('temp/' . $nid_front));

                unlink('temp/' . $nid_front);

                if($nidNumber) {
                    $data['nid'] = $nidNumber;
                    $data['success'] = true;
                    $data['message'] = __('NID found');
                }
            }
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
        }

        return response()->json($data);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Request $request
     * @param  int  $id
     * @return Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return Response
     */
    public function destroy($id)
    {
        //
    }
}
