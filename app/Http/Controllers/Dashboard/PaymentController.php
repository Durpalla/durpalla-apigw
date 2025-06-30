<?php

namespace App\Http\Controllers\Dashboard;

use App\Exports\PaymentExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use Maatwebsite\Excel\Facades\Excel;

class PaymentController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->ajax() === True) {
            $limit = $request->input('length');
            $start = $request->input('start');
            $columns = $request->get('columns');
            $column = $columns[$request->input('order.0.column')]['data'];
            $order = $request->input('order.0.dir');

            $query = Payment::with('booking', 'bookingItems.launch', 'customer');

            if (isset($_GET['date_from']) && $_GET['date_from'] != null) {
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_from']);
                $query->where('created_at', '>=', $date->format('Y-m-d H:i:s'));
            }

            if (isset($_GET['date_to']) && $_GET['date_to'] != null) {
                $date = \DateTime::createFromFormat('d/m/Y', $_GET['date_to']);
                $query->where('created_at', '<=', $date->format('Y-m-d 23:59:59'));
            }

            if (isset($_GET['invoice_id']) && $_GET['invoice_id'] != null) {
                $query->where('booking_id', $_GET['invoice_id']);
            }

            if (isset($_GET['transaction_id']) && $_GET['transaction_id'] != null) {
                $query->where('transaction_id', $_GET['transaction_id']);
            }

            if (isset($_GET['bank_trx']) && $_GET['bank_trx'] != null) {
                $query->where('bank_tran_id', $_GET['bank_trx']);
            }

            if (isset($_GET['service_type']) && $_GET['service_type'] != 'null') {
                $query->whereHas('bookingItems', function ($q) {
                    $q->whereHas('launch', function ($q) {
                        $q->where('vehicle_type', $_GET['service_type']);
                    });
                });
            }

            if (isset($_GET['status']) && $_GET['status'] != null) {
                $status = $_GET['status'];
                if ($status == 9) {
                    $query->onlyTrashed();
                } elseif ($status === 'due') {
                    $query->where('dues', '>', 0);
                } elseif ($status === 'success') {
                    $query->where('status', $status)->where('dues', 0);
                } else {
                    $query->where('status', $status);
                }
            }

            if (isset($_GET['status']) && $_GET['status'] != null) {
                $query->where('gateway', $_GET['gateway']);
            }

            $total = $query->count();

            $query->offset($start);
            if ($limit < 0) {
                $limit = $total;
            }
            $query->limit($limit);
            $query->orderBy($column, $order);
            $payments = $query->get();

            $data = [
                'draw' => $request->get('draw'),
                'recordsTotal' => $total,
                'recordsFiltered' => $total,
                'data' => $payments->toArray()
            ];

            return response()->json($data);
        }
        $service_type = (isset($_GET['type'])) ? $_GET['type'] : 'launch';
        return view('admin.booking.payment.index', compact('service_type'))->withTitle(ucfirst($service_type) . ' payments');
    }

    public function export(Request $request)
    {
        try {
            return Excel::download(new PaymentExport($request), 'payment-export-report.xlsx');
        } catch (\Exception $exception) {
            dd($exception);
        }
    }
}
