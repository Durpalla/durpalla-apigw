<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class FrontController extends Controller
{
    public function index(Request $request)
    {
        return response('OK', 200);
    }
}
