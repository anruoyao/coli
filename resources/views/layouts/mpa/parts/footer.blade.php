<footer class="pb-4 pt-6 flex" style="min-width: 320px;">
    <div class="app-container mx-auto flex-1 px-4 md:px-8">
        <nav class="flex flex-wrap gap-2 md:gap-4">
            <a href="{{ route('document.about.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.about_project') }}
            </a>
            <a href="{{ route('document.help.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.help_center') }}
            </a>
            <a href="{{ route('document.terms.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.terms_of_use') }}
            </a>
            <a href="{{ route('document.privacy.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.privacy_policy') }}
            </a>
            <a href="{{ route('document.cookies.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.cookies_policy') }}
            </a>
            <a href="{{ route('document.developers.index') }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                {{ __('links.developers') }}
            </a>

            @if(theme_name() == 'dark')
                <a href="{{ route('user.theme.switch', ['theme' => 'light']) }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                    {{ __('labels.light_theme') }}
                </a>
            @else
                <a href="{{ route('user.theme.switch', ['theme' => 'dark']) }}" class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                    {{ __('labels.dark_theme') }}
                </a>
            @endif
        </nav>
        <div class="h-px bg-fill-pr my-4"></div>
        <div class="block md:flex flex-wrap gap-4">
            <span class="text-par-s text-lab-pr2 hover:text-brand-900 smoothing">
                Copyright © {{ date('Y') }} {{ config('app.name') }}
            </span>

            <div class="flex md:inline-flex items-center gap-2 ml-auto mt-3 md:mt-0">
                <a href="{{ config('app.mobile_app_links.ios') }}" class="h-7">
                    <x-get-apps.cards.app-store></x-get-apps.cards.app-store>
                </a>
                <a href="{{ config('app.mobile_app_links.android') }}" class="h-7">
                    <x-get-apps.cards.google-play></x-get-apps.cards.google-play>
                </a>
            </div>
        </div>
    </div>
</footer>