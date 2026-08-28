<?php

namespace App\Services\Wallet;

use App\Enums\Wallet\TransactionDirection;
use App\Enums\Wallet\TransactionStatus;
use App\Enums\Wallet\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Enums\Payment\PaymentType;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\PaymentIntentService;
use App\Services\Payment\DTO\PaymentIntentResult;

class WalletService
{
	private User $userData;

	public function setUserData(User $userData)
	{
		$this->userData = $userData;

		return $this;
	}

	public function initiateDeposit(float $amount, array $providerData): PaymentIntentResult
	{
		$paymentIntentService = new PaymentIntentService($providerData['driver']);

		$paymentIntentDTO = new PaymentIntent(
			title: __('payment.intents.deposit.title'),
			description: __('payment.intents.deposit.description', ['app_name' => config('app.name')]),
			amount: $amount,
			currency: $providerData['currency'] ?? config('app.default_currency'),
			returnUrl: route($providerData['redirect_route']),
			cancelUrl: route($providerData['cancel_route'])
		);

		$paymentIntentResult = $paymentIntentService->initiate($paymentIntentDTO);

		if ($paymentIntentResult->success) {
			// Create a new payment record if initiated successfully.
			// This is used to track the payment and handle the payment status.

			$this->userData->payments()->create([
				'payment_uuid' => Str::uuid(),
				'reference_id' => $paymentIntentResult->referenceId,
				'payment_type' => PaymentType::DEPOSIT,
				'payment_method' => $providerData['driver'],
				'amount' => $amount,
				'currency' => $providerData['currency'] ?? config('app.default_currency'),
				'metadata' => []
			]);
		}

		return $paymentIntentResult;
	}

	public function addWalletBalance(float $amount)
	{
		// 事务 + 行锁：防止并发充值/退款/转账时余额丢更新
		DB::transaction(function () use ($amount) {
			$wallet = $this->userData->wallet()->lockForUpdate()->firstOrFail();

			$wallet->update([
				'balance' => $wallet->balance->add($amount)
			]);
		});

		return $this;
	}

    public function setWalletBalance(float $amount)
    {
        DB::transaction(function () use ($amount) {
            $wallet = $this->userData->wallet()->lockForUpdate()->firstOrFail();

            $wallet->update([
                'balance' => $wallet->balance->set($amount)
            ]);
        });

        return $this;
    }

	public function subtractWalletBalance(float $amount)
	{
		// 事务 + 行锁：余额不足抛出 DomainException，由调用方按业务返回错误
		DB::transaction(function () use ($amount) {
			$wallet = $this->userData->wallet()->lockForUpdate()->firstOrFail();

			if (! $wallet->balance->canAfford($amount)) {
				throw new \DomainException(__('wallet.validation.transfer.amount.can_afford'));
			}

			$wallet->update([
				'balance' => $wallet->balance->subtract($amount)
			]);
		});

		return $this;
	}

    /**
     * 原子转账（扣款方 → 收款方，含佣金），防双花与请求重放。
     *
     * - 单事务内按钱包 id 升序加行锁（避免并发死锁），串行化同一用户的所有转账；
     * - 支持幂等键 $clientUuid：同一键的已完成转账直接跳过（返回 false）；
     * - 余额不足抛出 DomainException。
     *
     * @return bool true=已执行转账，false=命中幂等键跳过
     */
    public function transferTo(User $receiver, float $amount, string $currency, ?string $clientUuid = null, ?string $message = null): bool
    {
        return DB::transaction(function () use ($receiver, $amount, $currency, $clientUuid, $message) {
            $senderWalletId = (int) $this->userData->wallet->id;
            $receiverWalletId = (int) $receiver->wallet->id;

            // 按 id 升序加锁，避免交错转账死锁
            $wallets = Wallet::whereIn('id', [$senderWalletId, $receiverWalletId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $senderWallet = $wallets->get($senderWalletId);
            $receiverWallet = $wallets->get($receiverWalletId);

            if (! $senderWallet || ! $receiverWallet) {
                throw new \RuntimeException('Wallet not found for transfer.');
            }

            // 幂等键去重：同一 client_uuid 的已完成转支出账已存在则跳过（配合行锁串行化防重放）
            if ($clientUuid) {
                $duplicated = $senderWallet->transactions()
                    ->where('transaction_type', TransactionType::TRANSFER->value)
                    ->where('status', TransactionStatus::COMPLETED->value)
                    ->where('metadata->client_uuid', $clientUuid)
                    ->exists();

                if ($duplicated) {
                    return false;
                }
            }

            $commission = (float) config('wallet.commission.transfer', 0);
            $netAmount = $amount - ($amount * $commission / 100);

            if (! $senderWallet->balance->canAfford($amount)) {
                throw new \DomainException(__('wallet.validation.transfer.amount.can_afford'));
            }

            $senderWallet->update(['balance' => $senderWallet->balance->subtract($amount)]);
            $receiverWallet->update(['balance' => $receiverWallet->balance->add($netAmount)]);

            $senderWallet->transactions()->create([
                'amount' => $amount,
                'transaction_type' => TransactionType::TRANSFER,
                'status' => TransactionStatus::COMPLETED,
                'direction' => TransactionDirection::OUTGOING,
                'commission' => $commission,
                'currency' => $currency,
                'metadata' => array_filter([
                    'wallet_number' => $receiverWallet->wallet_number,
                    'source' => ['name' => $receiver->name],
                    'client_uuid' => $clientUuid,
                ]),
            ]);

            $receiverWallet->transactions()->create([
                'amount' => $netAmount,
                'transaction_type' => TransactionType::TRANSFER,
                'status' => TransactionStatus::COMPLETED,
                'direction' => TransactionDirection::INCOMING,
                'commission' => $commission,
                'currency' => $currency,
                'metadata' => array_filter([
                    'wallet_number' => $senderWallet->wallet_number,
                    'source' => ['name' => $this->userData->name],
                    'message' => $message,
                    'client_uuid' => $clientUuid,
                ]),
            ]);

            return true;
        });
    }
	public function addWalletTransaction(array $data)
	{
		return $this->userData->wallet->transactions()->create($data);

		return $this;
	}
}
