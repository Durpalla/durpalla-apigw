<?php


namespace App\Http\Controllers\Api\v2;


class TestController
{
    public function test1()
    {
        session()->put('user.carts', ['test' => 'test']);
        dd(session()->get('user.carts'));
    }

    public function test2()
    {
        dd(session()->get('user.carts'));
    }
}
