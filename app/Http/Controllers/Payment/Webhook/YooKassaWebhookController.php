<?php

namespace App\Http\Controllers\Payment\Webhook;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use App\Services\Payment\Factories\PaymentHandlerFactory;

class YooKassaWebhookController extends Controller
{
    public function handleWebhook(Request $request)
    {
        $event = $request->input('event');
        $objectId = $request->input('object.id');
        $isRefundEvent = in_array($event, ['payment.refunded', 'refund.succeeded'], true);
        $shopId = config('payment.providers.yoo_kassa.credentials.shop_id');
        $secretKey = config('payment.providers.yoo_kassa.credentials.secret_key');

        if (! is_string($event) || ! is_string($objectId) || $objectId === '' || ! in_array($event, [
            'payment.succeeded',
            'payment.canceled',
            'payment.refunded',
            'refund.succeeded',
        ], true)) {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        if (! is_string($shopId) || $shopId === '' || ! is_string($secretKey) || $secretKey === '') {
            return response()->json(['message' => 'Webhook verification is unavailable.'], 503);
        }

        $localReferenceId = $isRefundEvent
            ? $request->input('object.payment_id')
            : $objectId;

        if (! is_string($localReferenceId) || $localReferenceId === '') {
            return response()->json(['message' => 'Invalid webhook payload.'], 400);
        }

        $localPayment = Payment::where('reference_id', $localReferenceId)
            ->where('payment_method', 'yookassa')
            ->first();

        if (! $localPayment) {
            return response()->json(['message' => 'Payment not found.'], 400);
        }

        $api = Http::connectTimeout(3)
            ->timeout(8)
            ->withBasicAuth($shopId, $secretKey);

        try {
            if ($isRefundEvent) {
                $response = $api->get('https://api.yookassa.ru/v3/refunds/' . rawurlencode($objectId));
            } else {
                $response = $api->get('https://api.yookassa.ru/v3/payments/' . rawurlencode($objectId));
            }
        } catch (ConnectionException) {
            return response()->json(['message' => 'Payment provider is unavailable.'], 503);
        }

        if ($isRefundEvent) {
            $refund = $response->json();
            $paymentId = data_get($refund, 'payment_id');
            $refundAmount = data_get($refund, 'amount.value');
            $refundCurrency = data_get($refund, 'amount.currency');

            if ($response->failed()
                || data_get($refund, 'id') !== $objectId
                || data_get($refund, 'status') !== 'succeeded'
                || ! is_string($paymentId)
                || $paymentId !== $localPayment->reference_id
                || ! $this->amountsMatch($refundAmount, (string) $localPayment->amount)
                || $refundCurrency !== $localPayment->currency) {
                return response()->json(['message' => 'Webhook verification failed.'], 400);
            }

            PaymentHandlerFactory::make($localPayment)->handleRefund();

            return response()->noContent();
        }
        $payment = $response->json();
        $expectedStatus = $event === 'payment.succeeded' ? 'succeeded' : 'canceled';

        if ($response->failed()
            || data_get($payment, 'id') !== $objectId
            || data_get($payment, 'status') !== $expectedStatus
            || ! $this->amountsMatch(data_get($payment, 'amount.value'), (string) $localPayment->amount)
            || data_get($payment, 'amount.currency') !== $localPayment->currency) {
            return response()->json(['message' => 'Webhook verification failed.'], 400);
        }

        if ($event === 'payment.succeeded') {
            PaymentHandlerFactory::make($localPayment)->handleSuccess();
        } else {
            PaymentHandlerFactory::make($localPayment)->handleFailure();
        }

        return response()->noContent();
    }

    private function amountsMatch(mixed $providerAmount, string $localAmount): bool
    {
        if (! is_string($providerAmount)
            || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $providerAmount)
            || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $localAmount)) {
            return false;
        }

        [$providerWhole, $providerFraction] = array_pad(explode('.', $providerAmount, 2), 2, '');
        [$localWhole, $localFraction] = array_pad(explode('.', $localAmount, 2), 2, '');

        return ltrim($providerWhole, '0') === ltrim($localWhole, '0')
            && str_pad($providerFraction, 2, '0') === str_pad($localFraction, 2, '0');
    }
}
