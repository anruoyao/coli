<?php

namespace Tests\Feature\Security;

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\User\UserStatus;
use App\Enums\Wallet\TransactionType;
use App\Models\Payment;
use App\Services\Payment\Handlers\DepositPaymentHandler;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class RefundPaymentIdempotencyTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public function test_repeated_refund_callbacks_debit_wallet_once(): void
    {
        $user = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $wallet = $user->wallet()->create([
            'wallet_number' => 'SEC-' . Str::uuid(),
            'balance' => '12.50',
            'currency' => 'USD',
        ]);
        $payment = Payment::create([
            'user_id' => $user->id,
            'payment_uuid' => (string) Str::uuid(),
            'reference_id' => 'security-' . Str::uuid(),
            'payment_type' => PaymentType::DEPOSIT,
            'payment_method' => 'yookassa',
            'status' => PaymentStatus::COMPLETED,
            'amount' => '12.50',
            'currency' => 'USD',
        ]);
        $handler = new DepositPaymentHandler($payment);

        $handler->handleRefund();
        $handler->handleRefund();

        $this->assertSame('0.00', (string) $wallet->fresh()->getRawOriginal('balance'));
        $this->assertSame(1, $wallet->transactions()->where('transaction_type', TransactionType::REFUND)->count());
        $this->assertSame(PaymentStatus::REFUNDED, $payment->fresh()->status);
    }
}
