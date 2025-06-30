<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use App\Models\Payment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PaymentExport implements FromCollection, WithHeadings, ShouldAutoSize
{
    private Request $requestData;

    public function __construct(Request $request)
    {
        $this->requestData = $request;
    }

    public function collection(): Collection
    {
        $query = Payment::select('id', 'booking_id', 'customer_id', 'paid_amount', 'created_at', 'updated_at', 'transaction_id', 'status', 'bank_tran_id', 'gateway')->with(['bookingItems' => function($query) {
            $query->with(['vehicle' => function($q) {
                $q->select('id', 'vehicle_type');
            }])->select('booking_items.id', 'booking_items.vehicle_id');
        }, 'customer' => function($query) {
            $query->select('id', 'name', 'mobile');
        }]);
        if($this->requestData->date_from) {
            $dateFrom = \DateTime::createFromFormat('d/m/Y', $this->requestData->date_from)->format('Y-m-d H:i:s');
            $query->where('created_at', '>=', $dateFrom);
        }
        if($this->requestData->date_to) {
            $dateTo = \DateTime::createFromFormat('d/m/Y', $this->requestData->date_to)->format('Y-m-d H:i:s');
            $query->where('created_at', '<=', $dateTo);
        }
        if(request()->filled('transaction_id')) {
            $query->where('transaction_id', $this->requestData->transaction_id);
        }
        if(request()->filled('bank_trx')) {
            $query->where('bank_tran_id', $this->requestData->bank_trx);
        }

        if(request()->filled('status')) {
            $query->where('status', $this->requestData->status);
        }
        if(request()->filled('gateway')) {
            $query->where('gateway', $this->requestData->gateway);
        }
//        if(request()->filled('service')) {
//            $query->whereHas('bookingItems', function($q) {
//                $q->whereHas('vehicle', function($q) {
//                    $q->where('vehicle_type',  $this->requestData->service);
//                });
//            });
//        }

        return $query->get()->map(function($item, $key) {
            return [
                'pnr' => $item->booking_id,
                'customer_name' => $item->customer->name ?? null,
                'customer_mobile' => $item->customer->mobile ?? '',
                'total_payable' => $item->booking->total_payable ?? $item->paid_amount,
                'paid_amount' => $item->paid_amount,
                'transaction_id' => $item->transaction_id,
                'bank_trx_id' => $item->bank_tran_id,
                'payment_status' => ucfirst($item->status),
                'payment_gateway' => ucfirst($item->gateway),
                'paid_at' => $item->updated_at->format('d/m/Y h:i A')
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Pnr',
            'Customer Name',
            'Customer Mobile',
            'Total Payable',
            'Paid Amount',
            'Transaction ID',
            'Bank Trx ID',
            'Payment Status',
            'Payment Gateway',
            'Paid At'
        ];
    }
}
