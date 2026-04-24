<?php

namespace App\Http\Controllers\Payment\Callback;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoboKassaCallbackController extends Controller
{
    public function handleSuccess(Request $request)
    {
        dd($request->all());
    }

    public function handleCancel(Request $request)
    {
        dd($request->all());
    }
}
