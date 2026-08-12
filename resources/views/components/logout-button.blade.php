@include('taxisnet-oauth::components.partials.button-styles')

@props(['action' => null])

<form method="POST" action="{{ $action ?? route('taxisnet-oauth.logout') }}">
    @csrf
    <button
        type="submit"
        {{ $attributes->merge(['class' => 'gg-taxisnet-btn gg-taxisnet-btn--logout']) }}
    >
        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path
                d="M15 3a1 1 0 0 1 1 1v3a1 1 0 1 1-2 0V5H6v14h8v-2a1 1 0 1 1 2 0v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h10Zm3.3 6.29 2.4 2.4a1 1 0 0 1 0 1.42l-2.4 2.4a1 1 0 0 1-1.42-1.42l.7-.7H10a1 1 0 1 1 0-2h7.58l-.7-.7a1 1 0 0 1 1.42-1.4Z"
                fill="currentColor"
            />
        </svg>
        {{ $slot->isEmpty() ? __('Log out') : $slot }}
    </button>
</form>
