@include('taxisnet-oauth::components.partials.button-styles')

@props(['href' => null])

<a
    href="{{ $href ?? route('taxisnet-oauth.redirect') }}"
    {{ $attributes->merge(['class' => 'gg-taxisnet-btn gg-taxisnet-btn--login']) }}
>
    <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <path
            d="M12 2a5 5 0 0 0-5 5v3H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-1V7a5 5 0 0 0-5-5Zm0 2a3 3 0 0 1 3 3v3H9V7a3 3 0 0 1 3-3Z"
            fill="currentColor"
        />
    </svg>
    {{ $slot->isEmpty() ? __('Login with TAXISnet') : $slot }}
</a>
