<?php

namespace App\Http\Controllers\Payment\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentProcessService;

class YooKassaWebhookController extends Controller
{
    public function handleWebhook(Request $request, PaymentProcessService $paymentProcessService)
    {
        $event = $request->input('event');
        $referenceId = $request->input('object.id');

        if (! is_string($event) || ! is_string($referenceId)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if ($event === 'payment.refunded') {
            $refundResponse = Http::withBasicAuth(
                config('payment.providers.yoo_kassa.credentials.shop_id'),
                config('payment.providers.yoo_kassa.credentials.secret_key')
            )->get('https://api.yookassa.ru/v3/refunds/' . rawurlencode($referenceId));

            $refund = $refundResponse->json();

            if ($refundResponse->failed() || data_get($refund, 'id') !== $referenceId || data_get($refund, 'status') !== 'succeeded' || ! is_string(data_get($refund, 'payment_id'))) {
                return response()->json(['message' => 'Webhook verification failed.'], 400);
            }

            $paymentProcessService->getHandler(data_get($refund, 'payment_id'))->handleRefund();

            return response()->noContent();
        }

        $paymentResponse = Http::withBasicAuth(
            config('payment.providers.yoo_kassa.credentials.shop_id'),
            config('payment.providers.yoo_kassa.credentials.secret_key')
        )->get('https://api.yookassa.ru/v3/payments/' . rawurlencode($referenceId));

        if ($paymentResponse->failed() || data_get($paymentResponse->json(), 'id') !== $referenceId) {
            return response()->json(['message' => 'Webhook verification failed.'], 400);
        }

        $providerStatus = data_get($paymentResponse->json(), 'status');

        if ($event === 'payment.succeeded' && $providerStatus === 'succeeded') {
            $paymentProcessService->getHandler($referenceId)->handleSuccess();
        } elseif ($event === 'payment.canceled' && $providerStatus === 'canceled') {
            $paymentProcessService->getHandler($referenceId)->handleFailure();
        }

        return response()->noContent();
    }
}
