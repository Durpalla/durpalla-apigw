<?php


namespace App\Gateways;

use App\Models\Booking;
use App\Services\CalculationService;
use Modules\Payment\Jobs\SslcommerzPaymentInitiatedJob;
use Rajtika\SSLCommerz\Services\SSLCommerz;

class Sslcom extends Builder implements GatewayInterface
{
    private $calculation;
    private $commerce;
    public function __construct()
    {
        $this->calculation = new CalculationService();
        config()->set('sslcommerz.sandbox_mode', (bool) getOption('Sslcommerz_gateway_sandbox', config('sslcommerz.sandbox_mode')));
        $this->commerce = Sslcommerz::storeID(getOption('Sslcommerz_app_id', config('sslcommerz.store_id')))
            ->storePassword(getOption('Sslcommerz_store_password', config('sslcommerz.store_password')));
    }

    public function token($order)
    {
        $incentive = $this->calculation->getAgentIncentive($order, $order->officer);
        $response = $this->commerce
            ->setHeader([])
            ->setParams([
                'tran_id' => $order->payment['transaction_id'],
                'product_name' => 'Launch Ticket',
                'product_category' => 'Ticket',
                'product_profile' => 'general',
                'total_amount' => round($order->total_payable - $incentive, 2),
                'currency' => getOption('default_currency', 'BDT'),
                'cus_name' => $order->customer['name'],
                'cus_email' => $order->customer['email'] ? $order->customer['name'] : 'customer@example.com',
                'cus_phone' => $order->customer['mobile'],
                'cus_add1' => 'Dhaka'
            ])
            ->setShippingInfo([
                'shipping_method' => "NO",
                'num_of_item' => $order->bookingItems->count()
            ])
            ->makePayment()
            ->checkout();

        if($response['status'] === 'success') {
                dispatch( new SslcommerzPaymentInitiatedJob($order->payment, $response['gateway_session']));
        }
        return response()->json($response);
    }

    public function create($params)
    {

    }

    public function execute($params)
    {

    }

    public function intend()
    {

    }

    public function validateTransaction(Booking $booking)
    {
        return SSLCommerz::validatePayment();
    }
}
