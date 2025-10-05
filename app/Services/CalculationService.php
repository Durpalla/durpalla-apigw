<?php


namespace App\Services;


use App\Constants\AppConst;

class CalculationService
{
    private $numberFormat;
    public function __construct()
    {
        $this->numberFormat = getOption('number_format', 'actual')    . 'Format';
    }

    public function actualFormat($num): string
    {
        return sprintf("%.2f", $num);
    }

    public function ceilFormat($num)
    {
        return ceil($num);
    }

    public function floorFormat($num)
    {
        return floor($num);
    }

    public function format($num)
    {
        return call_user_func([$this, $this->numberFormat], $num);
    }

    public function calculateItemTotal(array $item)
    {
        $total = $item['price'] - $this->calculateItemDiscount($item);
        if($item['booking_party'] != 'merchant') {
            $total += $this->calculateItemCharge($item);
        }
        if($item['vat_applicable_to'] == 'customer') {
            $total += $this->calculateItemVat($item);
        }

        return call_user_func([$this, $this->numberFormat], $total);
    }

    public function calculateItemVat(array $item)
    {
        return call_user_func([$this, $this->numberFormat],($item['price'] * ($item['vat_amount'] / 100)));
    }

    public function calculateItemCharge(array $item)
    {
        return ($item['charge_type'] == 'percent') ? call_user_func([$this, $this->numberFormat], ($item['price'] * ($item['charge_amount'] / 100))) : call_user_func([$this, $this->numberFormat],$item['charge_amount']);
    }

    public function calculateItemDiscount(array $item)
    {
        return ($item['discount_type'] === 'percent') ?
            call_user_func([$this, $this->numberFormat],($item['price'] * ($item['discount'] / 100)), 2) :
            call_user_func([$this, $this->numberFormat],$item['discount'], 2);
    }

    public function calculateRefundableAmount(array $item, $charge_refundable = false)
    {
        $vat = (getOption('vat_refundable', 0)) ? $this->calculateItemVat($item) : 0;
        $charge = ( $charge_refundable || getOption('charge_refundable', 0)) ? $this->calculateItemCharge($item) : 0;
        $discount = $this->calculateItemDiscount($item);

        return call_user_func([$this, $this->numberFormat], ($item['price'] + $vat + $charge - $discount));
    }

    public function getServiceCharge(array $item, $platform = 'web')
    {
        $charge = 0;
        if(getOption('service_charge_type', 'global') == 'global') {
            $charge += getOption('service_charge_' . $platform, 0);
        } else {
            return $item['service_charge'];
        }
        return call_user_func([$this, $this->numberFormat], $charge);
    }

    public function getCharges(array $item, string $platform = 'web'): array
    {
        $res = [
            'amount' => 0,
            'type' => getOption('service_charge_type', 'percent'),
            'total' => 0
        ];
        if(getOption('service_charge_platform', 'global') === 'global') {
            $res['amount'] = getOption('service_charge_' . $platform, 0);
            $res['total'] = ($res['type'] == 'percent') ? ($item['fare'] * ($res['amount'] / 100)) : $res['amount'];
        } else {
            $res['type'] = $item['service_charge_type'];
            $res['amount'] = $item['service_charge'];
            $res['total'] = ($res['type'] == 'percent') ? ($item['fare'] * $res['amount'] / 100) : $res['amount'];
        }
        return $res;
    }

    public function createDate($date, $format = 'd/m/Y')
    {
        $date_formated = date('Y-m-d');
        if ($date) {
            $date_formated = \DateTime::createFromFormat('d/m/Y', $date)->format('Y-m-d');
        }
        return $date_formated;
    }

    public function calculateVat(int $price, $type = 'percent'): float
    {
        return ($type == 'percent') ? call_user_func([$this, $this->numberFormat],($price * (getOption('vat_amount', 0) / 100)), 2) : call_user_func([$this, $this->numberFormat], $price);
    }

    public function calculateCharge($amount, $type): float
    {
        return ($type == 'percent') ? call_user_func([$this, $this->numberFormat],($amount * (getOption('vat_amount', 0) / 100)), 2) : call_user_func([$this, $this->numberFormat], $amount);
    }

    public function calculateAgentCommission(array $item)
    {
        return ($item['incentive_type'] == 'fixed') ?  call_user_func([$this, $this->numberFormat], $item['incentive']) : call_user_func([$this, $this->numberFormat], ($item['price'] * ($item['incentive'] / 100)));
    }

    public function calculatePartnerCommission($price, $partner)
    {
        $incentive = ($partner->incentive) ? $partner->incentive->incentive : 0;
        $type = ($partner->incentive) ? $partner->incentive->incentive_type : 'percent';
        return ($type == 'fixed') ?  call_user_func([$this, $this->numberFormat], $incentive) : call_user_func([$this, $this->numberFormat], ($price * ($incentive / 100)));
    }

    public function getAgentIncentive($order, $user)
    {
        if($user->type == AppConst::AGENT_ROLE) {
            return $order->bookingItems->map(function ($item, $key) {
                return [
                    'incentive' => ($item->incentive_type === 'percent') ? ($item->price * ($item->incentive / 100)) : $item->incentive
                ];
            })->sum('incentive');
        }
        return 0;
    }
}
