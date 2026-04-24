<?php

namespace App\Services\Payment\Drivers;

use Exception;
use Stripe\StripeClient;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class StripeDriver implements PaymentGatewayInterface
{
	protected $stripeClient;

	public function __construct()
	{
		$this->stripeClient = new StripeClient(config('payment.providers.stripe.credentials.secret_key'));
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$lineItems = [[
            'price_data' => [
                'currency' => $paymentIntent->currency,
                'product_data' => [
                    'name' => $paymentIntent->title,
					'description' => $paymentIntent->description,
                ],
                'unit_amount' => ($paymentIntent->amount * 100)
            ],
            'quantity' => 1
        ]];

        try {
            $session = $this->stripeClient->checkout->sessions->create([
                'payment_method_types' => config('payment.providers.stripe.payment_method_types'),
                'line_items' => $lineItems,
                'mode' => 'payment',
                'success_url' => $paymentIntent->returnUrl,
                'cancel_url' => $paymentIntent->cancelUrl
            ]);

            return new PaymentIntentResult(
				referenceId: $session->id,
				url: $session->url,
				success: true
			);
        }
        
        catch (Exception $e) {
			return new PaymentIntentResult(
				referenceId: null,
				success: false,
				message: $e->getMessage()
			);
        }
	}
}
