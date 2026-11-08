<?php

namespace Tests\Feature\Security;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\User\UserStatus;
use App\Enums\Wallet\TransactionType;
use App\Models\Payment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class YooKassaWebhookVerificationTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.providers.yoo_kassa.credentials.shop_id' => 'test-shop',
            'payment.providers.yoo_kassa.credentials.secret_key' => 'test-secret',
        ]);
    }

    public function test_payment_provider_name_maps_to_the_yookassa_configuration_key(): void
    {
        $payment = $this->makePayment('provider-payment-name');
        config(['payment.providers.yoo_kassa.name' => 'YooKassa']);

        $this->assertSame('YooKassa', $payment->provider_name);
    }

    public function test_success_event_requires_matching_remote_payment_and_status(): void
    {
        $payment = $this->makePayment('provider-payment-1');
        Http::fake(['https://api.yookassa.ru/v3/payments/provider-payment-1' => Http::response([
            'id' => 'provider-payment-1',
            'status' => 'succeeded',
            'amount' => ['value' => '12.50', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $payment->reference_id],
        ])->assertNoContent();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    public function test_mismatched_remote_payment_id_is_rejected_without_crediting(): void
    {
        $payment = $this->makePayment('provider-payment-2');
        Http::fake(['https://api.yookassa.ru/v3/payments/provider-payment-2' => Http::response([
            'id' => 'different-payment',
            'status' => 'succeeded',
            'amount' => ['value' => '12.50', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $payment->reference_id],
        ])->assertBadRequest();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
    }

    public function test_canceled_event_does_not_overwrite_a_completed_payment(): void
    {
        $payment = $this->makePayment('provider-payment-canceled');
        $payment->update(['status' => PaymentStatus::COMPLETED]);
        Http::fake(['https://api.yookassa.ru/v3/payments/provider-payment-canceled' => Http::response([
            'id' => 'provider-payment-canceled',
            'status' => 'canceled',
            'amount' => ['value' => '12.50', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.canceled',
            'object' => ['id' => $payment->reference_id],
        ])->assertNoContent();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
    }

    public function test_non_terminal_remote_payment_status_is_rejected(): void
    {
        $payment = $this->makePayment('provider-payment-3');
        Http::fake(['https://api.yookassa.ru/v3/payments/provider-payment-3' => Http::response([
            'id' => 'provider-payment-3',
            'status' => 'waiting_for_capture',
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $payment->reference_id],
        ])->assertBadRequest();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
    }

    public function test_unknown_event_is_rejected_without_provider_request(): void
    {
        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.waiting_for_capture',
            'object' => ['id' => 'provider-payment-4'],
        ])->assertBadRequest();

        Http::assertNothingSent();
    }

    public function test_payment_must_belong_to_yookassa_before_provider_lookup(): void
    {
        $payment = $this->makePayment('provider-payment-wrong-method');
        $payment->update(['payment_method' => 'stripe']);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $payment->reference_id],
        ])->assertBadRequest();

        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
    }

    public function test_missing_credentials_prevent_webhook_processing(): void
    {
        $payment = $this->makePayment('provider-payment-5');
        config([
            'payment.providers.yoo_kassa.credentials.shop_id' => null,
            'payment.providers.yoo_kassa.credentials.secret_key' => null,
        ]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'payment.succeeded',
            'object' => ['id' => $payment->reference_id],
        ])->assertServiceUnavailable();

        Http::assertNothingSent();
        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
    }

    public function test_refund_event_is_bound_to_verified_local_payment(): void
    {
        $payment = $this->makePayment('provider-payment-6');
        $payment->update(['status' => PaymentStatus::COMPLETED]);
        $payment->user->wallet->update(['balance' => '12.50']);
        Http::fake(['https://api.yookassa.ru/v3/refunds/refund-6' => Http::response([
            'id' => 'refund-6',
            'payment_id' => 'provider-payment-6',
            'status' => 'succeeded',
            'amount' => ['value' => '12.50', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'refund.succeeded',
            'object' => ['id' => 'refund-6', 'payment_id' => $payment->reference_id],
        ])->assertNoContent();

        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
        $this->assertSame(1, $payment->user->wallet->transactions()->where('transaction_type', TransactionType::REFUND)->count());
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization'));
    }

    public function test_refund_event_must_match_the_local_payment_reference(): void
    {
        $payment = $this->makePayment('provider-payment-7');
        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'refund.succeeded',
            'object' => ['id' => 'refund-7', 'payment_id' => 'another-payment'],
        ])->assertBadRequest();

        $this->assertSame(PaymentStatus::PENDING, $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_refund_provider_response_must_bind_to_the_local_payment(): void
    {
        $payment = $this->makePayment('provider-payment-8');
        $payment->update(['status' => PaymentStatus::COMPLETED]);
        Http::fake(['https://api.yookassa.ru/v3/refunds/refund-8' => Http::response([
            'id' => 'refund-8',
            'payment_id' => 'different-payment',
            'status' => 'succeeded',
            'amount' => ['value' => '12.50', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'refund.succeeded',
            'object' => ['id' => 'refund-8', 'payment_id' => $payment->reference_id],
        ])->assertBadRequest();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
        $this->assertSame(0, $payment->user->wallet->transactions()->where('transaction_type', TransactionType::REFUND)->count());
    }

    public function test_refund_provider_response_must_match_the_full_local_amount_and_currency(): void
    {
        $payment = $this->makePayment('provider-payment-9');
        $payment->update(['status' => PaymentStatus::COMPLETED]);
        Http::fake(['https://api.yookassa.ru/v3/refunds/refund-9' => Http::response([
            'id' => 'refund-9',
            'payment_id' => 'provider-payment-9',
            'status' => 'succeeded',
            'amount' => ['value' => '1.00', 'currency' => 'USD'],
        ])]);

        $this->postJson('/payment/yoo_kassa/webhook', [
            'event' => 'refund.succeeded',
            'object' => ['id' => 'refund-9', 'payment_id' => $payment->reference_id],
        ])->assertBadRequest();

        $this->assertSame(PaymentStatus::COMPLETED, $payment->fresh()->status);
        $this->assertSame('0.00', (string) $payment->user->wallet->fresh()->getRawOriginal('balance'));
        $this->assertSame(0, $payment->user->wallet->transactions()->where('transaction_type', TransactionType::REFUND)->count());
    }

    private function makePayment(string $referenceId): Payment
    {
        $user = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $user->wallet()->create([
            'wallet_number' => 'SEC-' . Str::uuid(),
            'balance' => 0,
            'currency' => 'USD',
        ]);

        return Payment::create([
            'user_id' => $user->id,
            'payment_uuid' => (string) Str::uuid(),
            'reference_id' => $referenceId,
            'payment_type' => PaymentType::DEPOSIT,
            'payment_method' => 'yookassa',
            'status' => PaymentStatus::PENDING,
            'amount' => '12.50',
            'currency' => 'USD',
        ]);
    }
}
