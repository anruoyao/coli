<?php

namespace App\Services\Payment\Handlers;

use Throwable;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionDirection;
use App\Services\Payment\Interfaces\PaymentHandlerInterface;
use App\Notifications\System\Deposit\DepositSuccessNotification;

class DepositPaymentHandler implements PaymentHandlerInterface
{
private Payment $paymentData;

public function __construct(Payment $paymentData)
{
$this->paymentData = $paymentData;
}

public function handleSuccess(): void
{
try {
DB::transaction(function (): void {
$payment = Payment::whereKey($this->paymentData->getKey())->lockForUpdate()->first();

if (! $payment || ! $payment->status->isPending()) {
return;
}

$walletService = app(WalletService::class);
$walletService
->setUserData($payment->user)
->addWalletBalance($payment->amount)
->addWalletTransaction([
'amount' => $payment->amount,
                        'transaction_type' => TransactionType::DEPOSIT,
                        'status' => TransactionStatus::COMPLETED,
                        'direction' => TransactionDirection::INCOMING,
'currency' => $payment->currency,
'metadata' => [
'reference_id' => $payment->reference_id,
'source' => [
'name' => $payment->provider_name
]
]
]);

$payment->update([
'status' => PaymentStatus::COMPLETED
]);
DB::afterCommit(fn () => $payment->user->notify(new DepositSuccessNotification()));
});
} catch (Throwable $th) {
payment_log($th->getMessage());
}
}

public function handleFailure(): void
{
$this->paymentData->status = PaymentStatus::FAILED;
$this->paymentData->save();

payment_log('Payment with id ' . $this->paymentData->id . ' failed');
}

public function handleRefund(): void
{
try {
DB::transaction(function (): void {
$payment = Payment::whereKey($this->paymentData->getKey())->lockForUpdate()->first();

if (! $payment || ! $payment->status->isCompleted()) {
return;
}

$walletService = app(WalletService::class);
$walletService->setUserData($payment->user)
->subtractWalletBalance($payment->amount)
->addWalletTransaction([
'amount' => $payment->amount,
'transaction_type' => TransactionType::REFUND,
'status' => TransactionStatus::COMPLETED,
'direction' => TransactionDirection::OUTGOING,
'currency' => $payment->currency,
'metadata' => [
'reference_id' => $payment->reference_id,
'source' => ['name' => $payment->provider_name]
]
]);

$payment->update(['status' => PaymentStatus::REFUNDED]);
});
} catch (Throwable $th) {
payment_log($th->getMessage());
}
}

public function handleExpiration(): void
{
$this->paymentData->status = PaymentStatus::EXPIRED;
$this->paymentData->save();

payment_log('Payment with id ' . $this->paymentData->id . ' expired');
}
}
