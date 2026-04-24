<?php

namespace App\Services\Payment\Drivers;

use Exception;
use Throwable;
use Illuminate\Support\Facades\Http;
use App\Services\Payment\DTO\PaymentIntent;
use App\Services\Payment\DTO\PaymentIntentResult;
use App\Services\Payment\Interfaces\PaymentGatewayInterface;

class PayPalDriver implements PaymentGatewayInterface
{
	private $clientId;
	private $secretKey;
	private $mode;
	private $baseUrl;

	public function __construct()
	{
		$this->clientId = config('payment.providers.paypal.credentials.client_id');
		$this->secretKey = config('payment.providers.paypal.credentials.secret_key');
		$this->mode = config('payment.providers.paypal.mode');
		$this->baseUrl = $this->mode === 'sandbox' ? 'https://api.sandbox.paypal.com' : 'https://api.paypal.com';
	}

	public function fetchAccessToken(): string
	{
		$response = Http::withBasicAuth($this->clientId, $this->secretKey)
            ->asForm()
            ->post("{$this->baseUrl}/v1/oauth2/token", [
                'grant_type' => 'client_credentials',
            ]);
		
		$response = $response->json();

		if (isset($response['access_token'])) {
			return $response['access_token'];
		}

		throw new Exception('Failed to fetch access token');
	}

	public function createPaymentIntent(PaymentIntent $paymentIntent): PaymentIntentResult
	{
		$accessToken = $this->fetchAccessToken();

		$response = Http::withToken($accessToken)
		->post("{$this->baseUrl}/v2/checkout/orders", [
			'intent' => 'CAPTURE',
			'purchase_units' => [
				[
					'amount' => [
						'value' => $paymentIntent->amount,
						'currency_code' => $paymentIntent->currency,
					],
					'title' => $paymentIntent->title,
					'description' => $paymentIntent->description,
					'custom_id' => time(),
				],
			],
			'application_context' => [
				'return_url' => $paymentIntent->returnUrl,
				'cancel_url' => $paymentIntent->cancelUrl,
			],
		]);

		$response = $response->json();

		if (isset($response['id'])) {
			$approvalLinkData = collect($response['links'])->firstWhere('rel', 'approve');

			return new PaymentIntentResult(
				referenceId: $response['id'],
				url: $approvalLinkData['href'],
				success: true
			);
		}

		return new PaymentIntentResult(
			referenceId: null,
			success: false,
			message: $response['message'] ?? 'Failed to create payment intent in PayPal.'
		);
	}

	public function capturePayment(string $orderId): bool
	{
		try {
			$accessToken = $this->fetchAccessToken();
			
			$response = Http::withToken($accessToken)
				->withHeaders([
					'Content-Type' => 'application/json'
				])->post("{$this->baseUrl}/v2/checkout/orders/{$orderId}/capture", null);

			$response = $response->json();
			
			if (isset($response['status']) && $response['status'] === 'COMPLETED') {
				return true;
			}

			return false;
		} catch (Throwable $th) {
			payment_log($th->getMessage());
			return false;
		}
	}
}
