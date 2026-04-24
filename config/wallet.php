<?php

return [
	'name' => env('WALLET_NAME', 'ColibriPay'),
	'about_link' => env('WALLET_ABOUT_LINK'),
	'wallet_number_prefix' => env('WALLET_NUMBER_PREFIX', 'CLB'),
	'default_balance' => env('WALLET_DEFAULT_BALANCE', 0),
	'deposit' => [
		'min_amount' => env('WALLET_DEPOSIT_MIN_AMOUNT', 10),
		'max_amount' => env('WALLET_DEPOSIT_MAX_AMOUNT', 1000000),
	],
	'transfer' => [
		'min_amount' => env('WALLET_TRANSFER_MIN_AMOUNT', 10),
		'max_amount' => env('WALLET_TRANSFER_MAX_AMOUNT', 1000000),
	],
	'commission' => [
		'deposit' => env('WALLET_COMMISSION_DEPOSIT', 3),
		'transfer' => env('WALLET_COMMISSION_TRANSFER', 1),
		'withdraw' => env('WALLET_COMMISSION_WITHDRAW', 3),
	],
	'withdraw' => [
		'min_amount' => env('WALLET_WITHDRAW_MIN_AMOUNT', 10),
		'max_amount' => env('WALLET_WITHDRAW_MAX_AMOUNT', 1000000),
	],
];