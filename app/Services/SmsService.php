<?php

namespace App\Services;

use App\Constants\AppConst;
use App\Constants\GatewayConstant;
use App\Helpers\CommonHelper;
use App\Helpers\GatewayHelper;
use App\Helpers\LogHelper;
use App\Models\Customer;
use App\Models\Gateway;

class SmsService
{
    public function send(Customer $customer, string $message, $receiver, array &$data): void
    {
        try {
            $gateway = Gateway::where('branch_id', $customer->branch_id)
                ->where('type', GatewayConstant::TYPE_SMS)
                ->where('status', GatewayConstant::ACTIVE)
                ->first();
            if(CommonHelper::customerIsEligibleForMessage($customer)) {
                $className = GatewayHelper::purseGateway($gateway);
                $gwt = new $className($gateway);

                $mobile = $receiver;
                if ($mobile && $this->isValidMobile($mobile)) {
                    $gwt->single($message, $mobile, $data);
                }
            }
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
            LogHelper::exception($exception, [
                'keyword' => 'SMS_SERVICE_EXCEPTION',
            ]);
        }
    }

    public function bulk(string $message, $receivers, array &$data): void
    {
        try {
            $gateway = Gateway::where('branch_id', auth()->user()->branch_id)
                ->where('type', GatewayConstant::TYPE_SMS)
                ->where('status', GatewayConstant::ACTIVE)
                ->first();

            $className = GatewayHelper::purseGateway($gateway);
            $gwt = new $className($gateway);
            $gwt->bulk($message, $data);
        } catch (\Exception $exception) {
            $data['message'] = $exception->getMessage();
            LogHelper::exception($exception, [
                'keyword' => 'SMS_SERVICE_EXCEPTION',
            ]);
        }
    }

    private function getProps(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'customerID' => $customer->customerID,
            'name' => $customer->user->name,
            'package' => $customer->package->name . ' (' . $customer->package->price . ' )',
            'due' => $customer->bill->dues,
            'paid' => request()->input('amount') ?? 0
        ];
    }

    private function isValidMobile(string $mobile): bool
    {
        return (bool)preg_match(AppConst::MOBILE_REGEX_PATTERN, $mobile);
    }
}
