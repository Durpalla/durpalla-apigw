<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Services\ReportService;

class MerchantReportController extends Controller
{
    private $report;
    public function __construct(ReportService $reportService)
    {
        $this->report = $reportService;
//        $this->middleware('role_or_permission:merchant|manager|supervisor');
    }

    /**
     * Display a listing of the resource.
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function index( Request $request)
    {
        $params = [
            'merchant_id' => auth()->user()->hasRole('merchant') ? auth()->user()->id : auth()->user()->merchant_id,
            'vehicle_id' => $request->vehicle_id,
            'officer_id' => $request->officer_id,
            'trip_id' => $request->trip_id,
            'trip_date' => ($request->trip_date) ? date('Y-m-d', strtotime($request->trip_date)) : date('Y-m-d')
        ];
        $reports = $this->report->merchantReport($params);
        dd($reports);
        return view('admin.report.index', compact('reports'))->withTitle('Report');
    }

    public function statistics( Request $request)
    {
        $user = Auth::user();
        $merchant_id = ($user->merchant_id) ? $user->merchant_id : $user->id;
        $merchant = Merchant::where('user_id', $merchant_id)->first();

        return view('admin.report.statistics', compact('merchant'))->withTitle('Sale Statistics');
    }
}
