<?php


namespace App\Services;


use Illuminate\Support\Facades\Cache;
use App\Models\CabinType;
use App\Models\Designation;
use App\Repository\Interfaces\BookingRepositoryInterface;

class ReportService
{
    protected $repository;

    public function __construct(BookingRepositoryInterface $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    public function merchantReport(array $params)
    {
        $results = $this->repository->getDataForReport($params);
        $designations = $this->getDesignationsArray();
        $reports = [
            'bookings' => [],
            'collections' => [],
            'types' => [],
            'refunds' => []
        ];

        if ($results) {
            foreach ($results as $result) {
                $booking['party'] = $result->booking_party;
                $booking['refunded_items'] = 0;
                $booking['refunded_amount'] = 0;
                $booking['total_payable'] = $result->total_payable;
                $booking['total_paid'] = $result->payment['paid_amount'];
                $booking['total_charge'] = $result->charge_total;
                $booking['total_vat'] = $result->vat_total;
                $booking['dues'] = $result->payment['dues'];
//                foreach($result->bookingItems as $bookingItem) {
//                    if($bookingItem['status'] == 2) {
//                        $booking['refunded_items'] += 1;
//                    }
//                }
                if ($result->cancellations) {
                    foreach ($result->cancellations as $cancellation) {
                        $booking['refunded_items'] += count(explode(',', $cancellation['items']));
                        $booking['refunded_amount'] += $cancellation['total_refundable'];
                    }
                }
                $booking['balance'] = $booking['total_payable'] - ($booking['refunded_amount'] + $booking['dues']);
                array_push($reports['bookings'], $booking);

                if ($result->collections) {
                    foreach ($result->collections as $collection) {
                        $designation_id = $collection['supervisor']['designation_id'];
                        array_push($reports['collections'], [
                            'name' => $collection['supervisor']['name'],
                            'designation' => ($designation_id > 0) ? $designations[$designation_id] : 'Admin',
                            'amount' => $collection['amount'],
                            'method' => $collection['payment_type']
                        ]);
                    }
                } elseif ($result->payment['paid_amount'] > 0) {
                    array_push($reports['collections'], [
                        'name' => $result['booking_party'],
                        'designation' => $result['booking_party'],
                        'amount' => $result->payment['paid_amount'],
                        'method' => $result->payment['payment_method']
                    ]);
                }
            }
        }

        return $reports;
    }

    public function tripReport(array $params)
    {
        $results = $this->repository->getDataForReport($params);
        $cabinTypes = $this->getCabinTypesArray();
        $designations = $this->getDesignationsArray();
        $reports = [
            'bookings' => [],
            'collections' => [],
            'types' => [],
            'refunds' => []
        ];

        if ($results) {
            foreach ($results as $result) {
                $booking['party'] = $result->booking_party;
                $booking['refunded_items'] = 0;
                $booking['refunded_amount'] = 0;
                $booking['total_payable'] = $result->total_payable;
                $booking['total_paid'] = $result->payment['paid_amount'];
                $booking['total_charge'] = $result->charge_total;
                $booking['total_vat'] = $result->vat_total;
                $booking['dues'] = $result->payment['dues'];
                foreach ($result->bookingItems as $bookingItem) {
                    $itemTotal = $this->calculateItemTotal($bookingItem->toArray());
                    if ($bookingItem['booking_type'] == 'deck') {
                        $bookingType = 'Deck (' . $bookingItem['price'] . ')';
                    } else {
                        $type = $cabinTypes->find($bookingItem['item']['type_id']);
                        $bookingType = $type->name;
                        $bookingType .=  ($type->is_ac) ? ' (AC)' : ' (Non AC)';
                    }
                    $reports['types'][] = [
                        'type' => $bookingItem['booking_type'],
                        'type_name' => $bookingType,
                        'total_vat' => $this->calculateItemVat($bookingItem->toArray()),
                        'total_charge' => $this->calculateItemCharge($bookingItem->toArray()),
                        'total_amount' => $itemTotal,
                        'refund_amount' => ($bookingItem['status'] == 2) ? $itemTotal : 0,
                        'total_discount' => $this->calculateItemDiscount($bookingItem->toArray())
                    ];
                    if ($bookingItem['status'] == 2) {
                        $booking['refunded_items'] += 1;
                        $booking['refunded_amount'] += $itemTotal;
                    }
                }
                if ($result->cancellations) {
                    foreach ($result->cancellations as $cancellation) {
                        $booking['refunded_items'] += count(explode(',', $cancellation['items']));
                        $booking['refunded_amount'] += $cancellation['total_refundable'];
                    }
                }
                $booking['balance'] = $booking['total_payable'] - ($booking['refunded_amount'] + $booking['dues']);
                array_push($reports['bookings'], $booking);

                if ($result->collections) {
                    foreach ($result->collections as $collection) {
                        $designation_id = $collection['supervisor']['designation_id'];
                        array_push($reports['collections'], [
                            'name' => $collection['supervisor']['name'],
                            'designation' => ($designation_id > 0) ? $designations[$designation_id] : 'Admin',
                            'amount' => $collection['amount'],
                            'method' => $collection['payment_type']
                        ]);
                    }
                } elseif ($result->payment['paid_amount'] > 0) {
                    array_push($reports['collections'], [
                        'name' => $result['booking_party'],
                        'designation' => $result['booking_party'],
                        'amount' => $result->payment['paid_amount'],
                        'method' => $result->payment['payment_method']
                    ]);
                }
            }
        }

        return $reports;
    }

    public function launchReport($params)
    {
        $results = $this->repository->getLaunchBookingReport($params);
        $cabinTypes = $this->getCabinTypesArray();
        $designations = $this->getDesignationsArray();
        $reports = [];

        $results->each(function($result, $key) use($cabinTypes, $designations, &$reports) {
            $booking['party'] = $result->booking_party;
            $booking['refunded_items'] = 0;
            $booking['refunded_amount'] = 0;
            $booking['payable'] = $result->total_payable;
            $booking['paid'] = $result->payment['paid_amount'];
            $booking['charge'] = $result->charge_total;
            $booking['vat'] = $result->vat_total;
            $booking['dues'] = $result->payment['dues'];
            $booking['cash'] = 0;
            $booking['bkash'] = 0;
            $booking['rocket'] = 0;
            $booking['nagad'] = 0;
            $booking['advance'] = 0;
            $booking['total_sell'] = 0;
            $booking['cabin_sell'] = 0;
            $booking['seat_sell'] = 0;
            $booking['deck_sell'] = 0;
            $booking['total_sell_amount'] = 0;
            $booking['cabin_sell_amount'] = 0;
            $booking['seat_sell_amount'] = 0;
            $booking['deck_sell_amount'] = 0;
            $booking['collections'] = [];
            $officer = $result->officer;
            $isSupervisor = ($officer instanceof \App\Models\MerchantStaff && $officer->isSupervisor())
                || (isset($officer->type) && $officer->type == 'supervisor');
            $booking['officer'] = ($result->customer_id !== $result->user_id)
                ? ($officer['name'] ?? '')
                : ($isSupervisor ? ($officer['name'] ?? '') : 'merchant');
            $booking['officer_designation'] = 'merchant';
            $booking['officer_mobile'] = '';
            $booking['officer_role'] = $result->officer['roles']->first()->name;
            $booking['officer_id'] = $result->user_id;

            if($result->collections && count($result->collections) > 1) {
                $booking['advance'] += $result->collections->first()->amount;
            }

            if ($result->collections) {
                $result->collections->each(function($collection, $key) use(&$booking, $designations) {
                    if(in_array($collection['payment_type'], ['cash', 'bkash', 'rocket', 'nagad'])) {
                        $booking[$collection['payment_type']] += $collection['amount'];
                    }
                    $designation_id = $collection['supervisor']['designation_id'];
                    array_push($booking['collections'], [
                        'id' => $collection['supervisor_id'],
                        'name' => $collection['supervisor']['name'],
                        'designation' => ($designation_id > 0) ? $designations[$designation_id] : 'Admin',
                        'amount' => $collection['amount'],
                        'method' => $collection['payment_type']
                    ]);
                });
            } elseif ($result->payment['paid_amount'] > 0) {
                array_push($booking['collections'], [
                    'name' => $result['booking_party'],
                    'designation' => $result['booking_party'],
                    'amount' => $result->payment['paid_amount'],
                    'method' => $result->payment['payment_method']
                ]);
            }

            $result->bookingItems->each(function($bookingItem, $key) use(&$booking, $result, $cabinTypes) {
                $itemTotal = $this->calculateItemTotal($bookingItem->toArray());
                if($bookingItem->booking_type == 'deck') {
                    $passenger = json_decode($bookingItem->passenger);
                    $booking[$bookingItem->booking_type . '_sell'] += ($passenger) ? $passenger->person : 1;
                } else {
                    $booking[$bookingItem->booking_type . '_sell'] += 1;
                }
                $booking['total_sell_amount'] += $itemTotal;
                $booking[$bookingItem->booking_type . '_sell_amount'] += $itemTotal;
            });

            array_push($reports, $booking);
        });

        return $reports;
    }

    private function calculateItemTotal(array $item)
    {
        return round(($item['price'] + $this->calculateItemVat($item) + $this->calculateItemCharge($item) - $this->calculateItemDiscount($item)), 2);
    }

    private function calculateItemVat(array $item)
    {
        return round(($item['price'] * ($item['vat_amount'] / 100)), 2);
    }

    private function calculateItemCharge(array $item)
    {
        return round(($item['price'] * ($item['charge_amount'] / 100)), 2);
    }

    private function calculateItemDiscount(array $item)
    {
        return ($item['discount_type'] === 'percent') ?
            round(($item['price'] * ($item['discount'] / 100)), 2) :
            round($item['discount'], 2);
    }

    private function getCabinTypesArray()
    {
        return Cache::rememberForever('cabin_types', function() {
            return CabinType::get();
        });
    }

    public function getDesignationsArray()
    {
        return Cache::rememberForever('designations', function() {
            return Designation::pluck('name', 'id');
        });
    }
}
