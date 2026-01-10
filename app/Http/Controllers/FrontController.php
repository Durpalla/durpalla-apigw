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
            $customer->notify(new TestNotification($customer));
        } catch (\Exception $exception) {
            //
        }
    }
}
