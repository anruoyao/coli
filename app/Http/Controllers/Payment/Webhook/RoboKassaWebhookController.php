<?php

namespace App\Http\Controllers\Payment\Webhook;

use Throwable;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class RoboKassaWebhookController extends Controller
{
    private $passTwo;

    public function __construct()
    {
        $this->passTwo = config('payment.providers.robokassa.credentials.pass_two');
    }

    public function handleWebhook(Request $request, PaymentProcessService $paymentProcessService)
    {
        try {
            $outSum = $request->get('out_summ');
            $invId = $request->get('inv_id');
            $signature = $request->get('crc');
            
            $mySignature = strtoupper(md5("{$outSum}:{$invId}:{$this->passTwo}"));
            $signature = strtoupper($signature);

            if ($mySignature !== $signature) {
                payment_log('Invalid signature');
            }
            else {
                $paymentProcessService->getHandler($invId)->handleSuccess();
            }
        } catch (Throwable $th) {
            payment_log($th->getMessage());
        }
    }
}
