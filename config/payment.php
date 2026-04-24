<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Payment Providers
    |--------------------------------------------------------------------------
    |
    | Here you may define the payment providers you want to use.
	| Add the providers you want to use but only if the status is true.
    |
    */

	'providers' => [
		'stripe' => [
			'name' => 'Stripe',
			'logo' => 'assets/payments/stripe.png',
			'status' => env('STRIPE_ENABLED', false),
			'redirect_route' => 'user.desktop.index',
			'cancel_route' => 'user.desktop.index',
			'driver' => 'stripe',
			'credentials' => [
				'secret_key' => env('STRIPE_SECRET_KEY'),
				'public_key' => env('STRIPE_PUBLIC_KEY'),
			],
			'webhook' => [
				'secret' => env('STRIPE_WEBHOOK_SECRET'),
				'webhook_tolerance' => env('STRIPE_WEBHOOK_TOLERANCE', 300),
			],
			'payment_method_types' => [
				'card',
				'paypal',
				'link'
			],
		],
		'paypal' => [
			'name' => 'PayPal',
			'logo' => 'assets/payments/paypal.png',
			'status' => env('PAYPAL_ENABLED', false),
			'redirect_route' => 'callback.paypal.success',
			'cancel_route' => 'callback.paypal.cancel',
			'driver' => 'paypal',
			'credentials' => [
				'client_id' => env('PAYPAL_CLIENT_ID'),
				'secret_key' => env('PAYPAL_SECRET_KEY'),
			],
			'webhook' => [
			],
			'mode' => env('PAYPAL_MODE', 'sandbox'),
		],
		'yoo_kassa' => [
			'name' => 'ЮKassa',
			'logo' => 'assets/payments/yookassa.png',
			'status' => env('YOO_KASSA_ENABLED', false),
			'redirect_route' => 'callback.yoo_kassa.success',
			'cancel_route' => 'callback.yoo_kassa.cancel',
			'driver' => 'yoo_kassa',
			// If your provider only some specific currency, you can set it here.
			// in this case, the currency will be used for the payment intent.
			// since we use RUB, we set it here.
			// if not set, the default currency will be used.
			'currency' => 'RUB',
			'credentials' => [
				'shop_id' => env('YOO_KASSA_SHOP_ID'),
				'secret_key' => env('YOO_KASSA_SECRET_KEY'),
			],
			'webhook' => [
			]
		],
		'robokassa' => [
			'name' => 'Robokassa',
			'logo' => 'assets/payments/robokassa.png',
			'status' => env('ROBOKASSA_ENABLED', false),
			'redirect_route' => 'callback.robokassa.success',
			'cancel_route' => 'callback.robokassa.cancel',
			'credentials' => [
				'merchant_login' => env('ROBOKASSA_MERCHANT_LOGIN'),
				'pass_one' => env('ROBOKASSA_PASS_ONE'),
				'pass_two' => env('ROBOKASSA_PASS_TWO'),
			],
			'currency' => 'RUB',
			'mode' => env('ROBOKASSA_MODE', 'sandbox'),
			'webhook' => [
			],
			'driver' => 'robokassa',
		],
	]
];
