<?php


namespace Modules\Customer;


use App\User;
use Modules\BroadCast\Jobs\SendFcmJob;
use Modules\Customer\Jobs\PartnerRequestJob;

class CustomerService
{
    public function sendPartnerRequest(array $data)
    {
        $customer = User::find($data['user_id']);
        dispatch(new PartnerRequestJob($customer, $data));
        dispatch(new SendFcmJob($customer, 'Your partner request has been send to ' . config('app.name')));
    }
}
