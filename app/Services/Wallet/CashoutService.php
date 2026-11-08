<?php

namespace App\Services\Wallet;

use App\Enums\Wallet\CashoutStatus;
use App\Models\Cashout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use DomainException;

class CashoutService
{
    private User $userData;

    public function __construct(int|User $user)
    {
        $this->userData = ($user instanceof User) ? $user : User::activeById($user)->first();

        if (! $this->userData) {
            throw new InvalidArgumentException('User not found or is not active.');
        }
    }

    public function isPendingRequestExists(): bool
    {
        return $this->userData->cashouts()->pending()->exists();
    }

    private function createCashout(array $data): Cashout
    {
        $data['request_code'] = random_int($this->userData->id, $this->userData->id + 999999);
        $data['commission_percentage'] = config('wallet.commission.withdraw');
        $data['to_pay'] = calculate_commission($data['amount'], $data['commission_percentage']);

        return $this->userData->cashouts()->create($data);
    }

    public function requestCashout(array $data): Cashout
    {
        return DB::transaction(function () use ($data): Cashout {
            $wallet = $this->userData->wallet()->lockForUpdate()->firstOrFail();

            if ($this->userData->cashouts()->pending()->exists()) {
                throw new DomainException(__('business/wallet.validation.amount.pending_request'));
            }

            $amount = (float) $data['amount'];

            if (! $wallet->balance->canAfford($amount)) {
                throw new DomainException(__('business/wallet.validation.amount.insufficient_balance'));
            }

            $cashout = $this->createCashout($data);
            $wallet->update(['balance' => $wallet->balance->subtract($amount)]);

            return $cashout;
        });
    }

    public function returnAmountToWallet(float $amount): void
    {
        (new WalletService())
            ->setUserData($this->userData)
            ->addWalletBalance($amount);
    }

    public function rejectCashout(int $cashoutId): void
    {
        $this->userData->cashouts()->where('id', $cashoutId)->update([
            'status' => CashoutStatus::REJECTED
        ]);
    }

    public function markAsPaid(int $cashoutId): void
    {
        $this->userData->cashouts()->where('id', $cashoutId)->update([
            'status' => CashoutStatus::PAID
        ]);
    }
}
