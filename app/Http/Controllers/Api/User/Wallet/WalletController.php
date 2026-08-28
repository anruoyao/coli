<?php

namespace App\Http\Controllers\Api\User\Wallet;

use Exception;
use App\Support\Num;
use App\Models\Wallet;
use App\Rules\X\XRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use Illuminate\Support\Facades\Validator;
use App\Traits\Http\Api\SupportsApiResponses;
use App\Services\Currency\Fiat\FiatCurrencyService;
use App\Http\Resources\User\Wallet\TransactionCollection;
use App\Http\Resources\User\Wallet\TransferReceiverResource;
use App\Notifications\User\Wallet\PaymentReceivedNotification;

class WalletController extends Controller
{
    use SupportsApiResponses;

    private $activeProviders;

    public function __construct()
    {
        $this->activeProviders = $this->getActiveProviders();
    }

    public function getData(Request $request)
    {
        $wallet = me()->wallet;

        $fiatCurrencyService = app(FiatCurrencyService::class);

        return $this->responseSuccess([
            'data' => [
                'balance' => [
                    'raw' => $wallet->balance->getAmount(),
                    'formatted' => $wallet->balance->getFormattedAmount(),
                ],
                'wallet_number' => $wallet->wallet_number,
                'currency' => $fiatCurrencyService->getCurrencyData($wallet->currency)->toArray()
            ]
        ]);
    }

    public function getPaymentProviders()
    {
        return $this->responseSuccess([
            'data' => $this->activeProviders->map(function($provider) {
                return [
                    'name' => $provider['name'],
                    'logo' => asset($provider['logo']),
                    'id' => $provider['driver']
                ];
            })->values()->toArray()
        ]);
    }

    public function createDepositPayment(Request $request, WalletService $walletService)
    {
        $validator = Validator::make(data: [
            'amount' => $request->amount,
            'provider' => $request->provider
        ], rules: [
            'amount' => ['required', 'numeric', XRule::join('min', config('wallet.deposit.min_amount')), XRule::join('max', config('wallet.deposit.max_amount'))],
            'provider' => ['required', 'string', Rule::in($this->activeProviders->pluck('driver')->toArray())]
        ], attributes: [
            'amount' => __('labels.amount'),
            'provider' => __('labels.provider')
        ]);

        if ($validator->fails()) {
            $this->throwValidationError($validator);
        }

        try {
            $responseData = [];
            $activeProviders = $this->activeProviders->toArray();

            $paymentIntentResult = $walletService->setUserData(me())
                ->initiateDeposit($request->amount, $activeProviders[$request->provider]);

			if ($paymentIntentResult->isHostedCheckout()) {
				$responseData = [
					'is_hosted_checkout' => true,
					'checkout_url' => $paymentIntentResult->url
				];
			}

            return $this->responseSuccess([
                'data' => $responseData
            ]);
        } catch (Exception $e) {
            return $this->responseValidationError([
                'message' => $e->getMessage(),
                'errors' => [
                    'provider' => [$e->getMessage()]
                ]
            ]);
        }
    }

    public function getTransactions(Request $request)
    {
        $wallet = me()->wallet;

        $todayTransactions = $wallet->transactions()
            ->latest('id')
            ->whereDate('created_at', now()->today())
            ->get();

        $thisWeekTransactions = $wallet->transactions()
            ->latest('id')
            ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->whereDate('created_at', '!=', now()->today())
            ->get();

        $thisMonthTransactions = $wallet->transactions()
            ->latest('id')
            ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->whereDate('created_at', '<', now()->startOfWeek())
            ->get();

        $otherTransactions = $wallet->transactions()
            ->latest('id')
            ->where('created_at', '<', now()->startOfMonth())
            ->take(30)
            ->get();

        return $this->responseSuccess([
            'data' => [
                'today' => TransactionCollection::make($todayTransactions),
                'this_week' => TransactionCollection::make($thisWeekTransactions),
                'this_month' => TransactionCollection::make($thisMonthTransactions),
                'other' => TransactionCollection::make($otherTransactions)
            ]
        ]);
    }

    public function getReceivers(Request $request)
    {
        $request->validate([
            'wallet_number' => ['required', 'string', 'max:255']
        ]);

        $walletNumber = $request->get('wallet_number');
        $walletData = Wallet::excludeSelf()->whereWalletNumber($walletNumber)->with('user')->first();

        if(empty($walletData)) {
            return $this->responseResourceNotFoundError('Wallet', $walletNumber);
        }

        return $this->responseSuccess([
            'data' => TransferReceiverResource::make($walletData)
        ]);
    }

    public function getReceiverHistory(Request $request)
    {
        $transferHistory = me()->wallet->transactions()
            ->where('transaction_type', TransactionType::TRANSFER)
            ->get();

        $walletNumbers = $transferHistory->pluck('metadata.wallet_number')->unique();

        $receiverWallets = Wallet::whereIn('wallet_number', $walletNumbers->toArray())->with('user')->get();

        return $this->responseSuccess([
            'data' => $receiverWallets->map(function($walletItem) {
                return TransferReceiverResource::make($walletItem);
            })
        ]);
    }

    public function makeTransfer(Request $request)
    {
        $request->validate([
            'amount' => ['required', 'numeric', XRule::join('min', config('wallet.transfer.min_amount')), XRule::join('max', config('wallet.transfer.max_amount'))],
            'wallet_number' => ['required', 'string', 'max:255'],
            'message' => ['nullable', 'string', 'max:140'],
            'client_uuid' => ['nullable', 'string', 'max:64']
        ]);

        $walletNumber = $request->get('wallet_number');
        $transferAmount = $request->get('amount');
        $walletData = Wallet::excludeSelf()->whereWalletNumber($walletNumber)->with('user')->first();

        if($walletData) {
            // 转账隐私：收款方「谁能给我转账」设置不允许时禁止转账
            $receiverPermit = $walletData->user->permitSettings?->payment_transfers;

            if($receiverPermit && ! $receiverPermit->allows(me(), $walletData->user)) {
                return $this->responseError([
                    'message' => ($receiverPermit->nobody())
                        ? __('api/wallet.transfer_denied_nobody', [], me()->language)
                        : __('api/wallet.transfer_denied_followers', [], me()->language)
                ], 403);
            }

            $walletService = app(WalletService::class);

            try {
                // 原子转账：事务 + 行锁 + 幂等键（client_uuid）防双花/防重放
                $executed = $walletService->setUserData(me())->transferTo(
                    receiver: $walletData->user,
                    amount: (float) $transferAmount,
                    currency: me()->wallet->currency,
                    clientUuid: $request->get('client_uuid'),
                    message: $request->get('message'),
                );
            } catch (\DomainException $e) {
                return $this->responseValidationError([
                    'message' => $e->getMessage(),
                    'errors' => [
                        'amount' => [$e->getMessage()]
                    ]
                ]);
            }

            // 命中幂等键（重复请求）直接返回成功，避免重复提示
            if ($executed) {
                $commission = (float) config('wallet.commission.transfer', 0);
                $netAmount = (float) $transferAmount - ((float) $transferAmount * $commission / 100);

                $walletData->user->notify(new PaymentReceivedNotification(Num::currency($netAmount, me()->wallet->currency)));
            }

            return $this->responseSuccess([
                'data' => null
            ]);
        }
        else {
            return $this->responseResourceNotFoundError('Wallet', $walletNumber);
        }
    }

    private function getActiveProviders()
    {
        $paymentProviders = config('payment.providers');

        $providers = collect($paymentProviders)->filter(function($provider) {
            return $provider['status'];
        });

        return $providers;
    }
}
