<?php

namespace App\Http\Controllers\Api\user\Wallet;

use Exception;
use App\Support\Num;
use App\Models\Wallet;
use App\Rules\X\XRule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Http\Controllers\Controller;
use App\Enums\Wallet\TransactionType;
use App\Services\Wallet\WalletService;
use App\Enums\Wallet\TransactionStatus;
use Illuminate\Support\Facades\Validator;
use App\Enums\Wallet\TransactionDirection;
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
            'message' => ['nullable', 'string', 'max:140']
        ]);

        $walletNumber = $request->get('wallet_number');
        $transferAmount = $request->get('amount');
        $walletData = Wallet::excludeSelf()->whereWalletNumber($walletNumber)->with('user')->first();

        if($walletData) {
            if(me()->wallet->balance->canAfford($transferAmount)) {
                $walletService = app(WalletService::class);
                $walletService->setUserData(me())->subtractWalletBalance($transferAmount)->addWalletTransaction([
                    'amount' => $transferAmount,
                    'transaction_type' => TransactionType::TRANSFER,
                    'status' => TransactionStatus::COMPLETED,
                    'direction' => TransactionDirection::OUTGOING,
                    'commission' => config('wallet.commission.transfer'),
                    'currency' => me()->wallet->currency,
                    'metadata' => [
                        'wallet_number' => $walletData->wallet_number,
                        'source' => [
                            'name' => $walletData->user->name
                        ]
                    ]
                ]);

                $transferAmount = ($transferAmount - ($transferAmount * config('wallet.commission.transfer') / 100));

                $walletService->setUserData($walletData->user)->addWalletBalance($transferAmount)->addWalletTransaction([
                    'amount' => $transferAmount,
                    'transaction_type' => TransactionType::TRANSFER,
                    'status' => TransactionStatus::COMPLETED,
                    'direction' => TransactionDirection::INCOMING,
                    'commission' => config('wallet.commission.transfer'),
                    'currency' => me()->wallet->currency,
                    'metadata' => [
                        'wallet_number' => me()->wallet->wallet_number,
                        'source' => [
                            'name' => me()->name
                        ],
                        'message' => $request->get('message')
                    ]
                ]);

                $walletData->user->notify(new PaymentReceivedNotification(Num::currency($transferAmount, me()->wallet->currency)));

                return $this->responseSuccess([
                    'data' => null
                ]);
            }

            else {
                return $this->responseValidationError([
                    'message' => __('wallet.validation.transfer.amount.can_afford'),
                    'errors' => [
                        'amount' => [
                            __('wallet.validation.transfer.amount.can_afford')
                        ]
                    ]
                ]);
            }
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
