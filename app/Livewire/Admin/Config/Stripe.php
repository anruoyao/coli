<?php

namespace App\Livewire\Admin\Config;

use App\Settings\Acquiring\StripeSettings;
use App\Support\Views\Flash;
use Livewire\Component;

class Stripe extends Component
{
    public array $formData = [];
    public array $providerInfo = [];

    public function mount()
    {
        $stripeSettings = app(StripeSettings::class);

        $this->formData = [
            'name' => $stripeSettings->stripe_name,
            'status' => $stripeSettings->stripe_status,
            'secret_key' => $stripeSettings->stripe_secret_key,
            'public_key' => $stripeSettings->stripe_public_key,
            'webhook_secret' => $stripeSettings->stripe_webhook_secret,
            'webhook_tolerance' => $stripeSettings->stripe_webhook_tolerance,
            'payment_method_types' => $stripeSettings->stripe_payment_method_types,
        ];

        $this->providerInfo = [
            'name' => $stripeSettings->stripe_name,
            'logo' => $stripeSettings->getLogo(),
        ];
    }

    public function render()
    {
        return view('livewire.admin.config.stripe');
    }

    public function submitForm()
    {
        $this->validate([
            'formData.name' => ['required', 'string', 'max:255'],
            'formData.status' => ['required', 'boolean'],
            'formData.secret_key' => ['nullable', 'string', 'max:1200'],
            'formData.public_key' => ['nullable', 'string', 'max:1200'],
            'formData.webhook_secret' => ['nullable', 'string', 'max:1200'],
            'formData.payment_method_types' => ['required', 'string', 'max:255'],
        ], attributes: [
            'formData.name' => __('admin/config.form.provider_name'),
            'formData.status' => __('admin/config.form.provider_status'),
            'formData.secret_key' => __('admin/config.form.secret_key'),
            'formData.public_key' => __('admin/config.form.public_key'),
            'formData.webhook_secret' => __('admin/config.form.webhook_secret'),
            'formData.payment_method_types' => __('admin/config.form.payment_method_types'),
        ]);

        $stripeSettings = app(StripeSettings::class);
        $stripeSettings->stripe_name = $this->formData['name'];
        $stripeSettings->stripe_status = $this->formData['status'];
        $stripeSettings->stripe_secret_key = $this->formData['secret_key'];
        $stripeSettings->stripe_public_key = $this->formData['public_key'];
        $stripeSettings->stripe_webhook_secret = $this->formData['webhook_secret'];
        $stripeSettings->stripe_payment_method_types = $this->formData['payment_method_types'];
        $stripeSettings->save();

        return redirect()->with('flashMessage', (new Flash(content: __('admin/flash.config.settings_success')))->get())
            ->route('admin.acquiring.edit', $stripeSettings->getDriver());
    }
}
