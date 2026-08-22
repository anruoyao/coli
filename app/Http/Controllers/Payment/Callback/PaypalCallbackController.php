<?php

namespace App\Http\Controllers\Payment\Callback;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentCaptureService;
use App\Services\Payment\PaymentProcessService;

class PaypalCallbackController extends Controller
{
    public function handleSuccess(Request $request)
    {
        try {
            $referenceId = $request->get('token');
            $paymentCaptureService = new PaymentCaptureService('paypal');
            $isCaptured = $paymentCaptureService->capturePayment($referenceId);

            if($isCaptured) {
                $paymentProcessService = app(PaymentProcessService::class);
                $paymentProcessService->getHandler($referenceId)->handleSuccess();
            }
                
            return redirect()->route('user.desktop.index');
        } catch (Throwable $th) {
            dd($th);
        }
    }

    public function handleCancel(Request $request)
    {
        return redirect()->route('user.desktop.index');
    }
}
