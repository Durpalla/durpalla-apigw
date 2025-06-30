<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Http\Request;
use App\Exports\DailyTripReportExport;
use App\Http\Controllers\Controller;
use App\Services\DailyBookingReportService;
use App\Services\DailyTripBookingItemReport;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    private $dailyBookingReport;
    private $dailyTripBookingItem;

    public function __construct(DailyBookingReportService $dailyBookingReport, DailyTripBookingItemReport $dailyTripBookingItemReport)
    {
        $this->dailyBookingReport = $dailyBookingReport;
        $this->dailyTripBookingItem = $dailyTripBookingItemReport;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('admin.report.index')->withTitle('All reports');
    }

    public function dailyBookings(Request $request)
    {
        if (request()->wantsJson()) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');
            $status = request('status');
            $booking_date = date('Y-m-d');
            if (request('booking_date')) {
                $booking_date = \DateTime::createFromFormat('d/m/Y', request('booking_date'))->format('Y-m-d');
            }

            $bookings = $this->dailyBookingReport->get($booking_date, $status, $limit, $start);
            $total = $bookings->count();
            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $bookings
            ];
            return response()->json($data);
        }
        return view('admin.report.daily-booking')->withTitle('Daily booking report');
    }

    public function dailyTripReport()
    {
        return view('admin.report.trip-report')->withTitle('Trip booking list');
    }

    public function exportTripReport(Request $request)
    {
        if ($request->schedule_id) {
            $results = $this->dailyTripBookingItem->getTripReport($request->schedule_id, $request->type);
            return Excel::download(new DailyTripReportExport($results, ''), 'Trip-Report-' . $request->schedule_id . '.xlsx');
        }
    }

    public function dailyVehicleBookings()
    {
        return view('admin.report.launch-report')->withTitle('Daily launch bookings');
    }

    public function exportDailyVehicleBookings(Request $request)
    {
        try {
            if ($request->vehicle_id && $request->booking_date) {
                $booking_date = \DateTime::createFromFormat('d/m/Y', $request->booking_date)->format('Y-m-d');
                $results = $this->dailyBookingReport->getLaunchReport($request->vehicle_id, $booking_date, $request->type);
                return Excel::download(new DailyTripReportExport($results, ''), 'Launch-Booking-Report-' . $request->vehicle_id . '.xlsx');
            }
            throw new \Exception("Launch not found");
        } catch (\Exception $e) {
            return redirect()->back()->withMessage(['status' => false, 'label' => 'error', 'content' => $e->getMessage()]);
        }
    }
}
