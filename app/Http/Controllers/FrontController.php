<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Notifications\TestNotification;
use Illuminate\Http\Request;

class FrontController extends Controller
{
    public function index(Request $request)
    {
        try {
            $customer = Customer::first();
            if ($customer) {
                $customer->notify(new TestNotification($customer));
            }
            return response('OK', 200);
        } catch (\Exception $exception) {
            return response('OK', 200);
        }
    }
}
