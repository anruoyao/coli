<div class="flex flex-col gap-6">
	<x-config.env
		name="MAIL_MAILER"
		description="{{ __('admin/config.captions.email_driver') }}"
	value="{{ config('mail.default') }}"/>

	<x-config.env
		name="MAIL_HOST"
		description="{{ __('admin/config.captions.email_host') }}"
	value="{{ config('mail.mailers.smtp.host') }}"/>

	<x-config.env
		name="MAIL_PORT"
		description="{{ __('admin/config.captions.email_port') }}"
	value="{{ config('mail.mailers.smtp.port') }}"/>

	<x-config.env
		name="MAIL_USERNAME"
		description="{{ __('admin/config.captions.email_username') }}"
	value="{{ config('mail.mailers.smtp.username') }}"/>

	<x-config.env
		name="MAIL_PASSWORD"
		description="{{ __('admin/config.captions.email_password') }}"
	value="{{ config('mail.mailers.smtp.password') }}"/>

	<x-config.env
		name="MAIL_ENCRYPTION"
		description="{{ __('admin/config.captions.email_encryption') }}"
	value="{{ config('mail.mailers.smtp.encryption') }}"/>

	<x-config.env
		name="MAIL_FROM_ADDRESS"
		description="{{ __('admin/config.captions.email_from_address') }}"
	value="{{ config('mail.mailers.smtp.from_address') }}"/>

	<x-config.env
		name="MAIL_FROM_NAME"
		description="{{ __('admin/config.captions.email_from_name') }}"
	value="{{ config('mail.mailers.smtp.from_name') }}"/>
</div>