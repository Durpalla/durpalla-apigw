<?php


namespace App\Services;


use App\Repository\Interfaces\VehicleRepositoryInterface;
use App\Repository\Interfaces\ScheduleRepositoryInterface;

class VehicleService
{
    protected $schedule;
    protected $report;
    protected $calculation;
    private $vehicle;
    private $upload;

    public function __construct(
        VehicleRepositoryInterface $vehicle,
        ScheduleRepositoryInterface $scheduleRepository,
        ReportService $reportService,
        CalculationService $calculationService
    ) {
        $this->vehicle = $vehicle;
        $this->schedule = $scheduleRepository;
        $this->report = $reportService;
        $this->calculation = $calculationService;
    }

    public function getOfficersReport($vehicle_id, $date_from, $date_to, $route_id, $party)
    {
        $designations = $this->report->getDesignationsArray();
        $reports = $this->vehicle->getOfficerReports($vehicle_id, $date_from, $date_to, $route_id, $party);
        $response = ['bookings' => [], 'collections' => [], 'refunds' => []];
        if($reports) {
            foreach($reports as $report) {
                $route = explode('-', $report->route['route_name']);
                $route_name = ($report->schedule_type == 'reverse') ? trim($route[1]) . ' - ' . trim($route[0]) : $route[0] . ' - ' . $route[1];
                foreach($report->bookingItems as $bookingItem) {
                    $role_name = 'customer';
                    $officer = 'customer';
                    $mobile = '';
                    if($bookingItem['booking']['officer']) {
                        $officer = $bookingItem['booking']['officer']['name'];
                        $mobile = $bookingItem['booking']['officer']['mobile'];
                        $role_name = ($bookingItem['booking'] && $bookingItem['booking']['officer'] && $bookingItem['booking']['officer']['roles']->count() > 0) ? collect($bookingItem['booking']['officer']['roles'])->first()->name : 'customer';
                    }
                    $designation_name = 'N/A';
                    if($bookingItem['booking']['officer'] && array_key_exists($bookingItem['booking']['officer']['designation_id'], $designations->toArray())) {
                        $designation_name = $designations[$bookingItem['booking']['officer']['designation_id']];
                    }

                    $data = [
                        'route' => $route_name . '-' . $report->id,
                        'role' => $role_name,
                        'designation' => $designation_name,
                        'trip_date' => date('d/m/Y', strtotime($report->shcedule_date)),
                        'officer' => $officer,
                        'officer_id' => ($bookingItem['booking']['officer']->hasRole('customer')) ? 1 : $bookingItem->user_id,
                        'officer_mobile' => $mobile,
                        'officer_designation' => $officer . '[' . $designation_name. ']',
                        'total_amount' => $this->calculation->calculateItemTotal($bookingItem->toArray()),
                        'total_vat' => $this->calculation->calculateItemVat($bookingItem->toArray()),
                        'total_charge' => $this->calculation->calculateItemCharge($bookingItem->toArray()),
                        'total_discount' => $this->calculation->calculateItemDiscount($bookingItem->toArray()),
                        'collections' => [],
                        'refunds' => []
                    ];
                    if($bookingItem->collectors != null) {
                        $bookingItem->collectors->each(function ($item, $key) use (&$response, $data) {
                            $response['collections'][$item->booking_id . '-' . $item->id] = $item;
                            $response['collections'][$item->booking_id . '-' . $item->id]['route'] = $data['route'];
                            $response['collections'][$item->booking_id . '-' . $item->id]['role'] = $data['role'];
                            $response['collections'][$item->booking_id . '-' . $item->id]['officer'] = $data['officer'];
                        });
                    }
                    if($bookingItem->refunded != null) {
                        $bookingItem->refunded->each(function ($item, $key) use (&$response, $data) {
                            $response['refunds'][$item->booking_id . '-' . $item->id] = $item;
                            $response['refunds'][$item->booking_id . '-' . $item->id]['route'] = $data['route'];
                            $response['refunds'][$item->booking_id . '-' . $item->id]['role'] = $data['role'];
                            $response['refunds'][$item->booking_id . '-' . $item->id]['officer'] = $data['officer'];
                        });
                    }
                    foreach(collect($bookingItem) as $k => $v) {
                        if(!in_array($k, ['booking', 'item', 'payment', 'collectors', 'refunded'])) {
                            $data[$k] = $v;
                        }
                    }
                    array_push($response['bookings'], $data);
                }
            }
        }
        return $response;
    }

    public function create(array $data)
    {
        $data = array_merge($data, [
            'user_id' => auth()->user()->id,
            'registration_expiry_date' => $this->calculation->createDate($data['registration_expiry_date']),
            'fitness_expiry_date' => $this->calculation->createDate($data['fitness_expiry_date'])
        ]);
        return $this->vehicle->create($data);
    }

    public function update(array $data, $id)
    {
        return $this->vehicle->update(array_merge($data, [
            'user_id' => auth()->user()->id,
            'registration_expiry_date' => $this->calculation->createDate($data['registration_expiry_date']),
            'fitness_expiry_date' => $this->calculation->createDate($data['fitness_expiry_date'])
        ]), $id);
    }
}
