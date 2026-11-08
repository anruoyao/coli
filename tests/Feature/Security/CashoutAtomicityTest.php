<?php

namespace Tests\Feature\Security;

use App\Enums\User\UserStatus;
use App\Models\Cashout;
use App\Services\Wallet\CashoutService;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\Feature\Marketing\Concerns\CreatesUsers;
use Tests\TestCase;

class CashoutAtomicityTest extends TestCase
{
    use DatabaseTransactions;
    use CreatesUsers;

    public function test_duplicate_pending_cashout_request_does_not_deduct_balance_twice(): void
    {
        $user = $this->makeUser(['status' => UserStatus::ACTIVE]);
        $wallet = $user->wallet()->create([
            'wallet_number' => 'SEC-' . uniqid(),
            'balance' => 50,
            'currency' => 'USD',
        ]);
        $service = new CashoutService($user);
        $request = [
            'amount' => 20,
            'payment_method' => 'bank',
            'credentials' => 'test-account',
        ];

        $service->requestCashout($request);
        try {
            $service->requestCashout($request);
            $this->fail('A second pending cashout request must be rejected.');
        } catch (DomainException) {
            $this->assertSame(1, Cashout::where('user_id', $user->id)->where('status', 'pending')->count());
            $this->assertSame('30.00', (string) $wallet->fresh()->getRawOriginal('balance'));
        }
    }
}
