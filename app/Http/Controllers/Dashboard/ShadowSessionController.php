<?php

namespace App\Http\Controllers\Dashboard;

use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;

class ShadowSessionController extends Controller
{
    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $admin = auth()->user();
        $user = User::findOrFail($id);
        if($user->id == $admin->id) {
            session()->flash('warning', 'You cannot shadow your own account');
        }
        if($admin->hasRole('admin')) {
            session()->put('master_id', $admin->id);
        } else {
            session()->forget('master_id');
        }
        Auth::login($user);

        return redirect()->route('home');
    }
}
