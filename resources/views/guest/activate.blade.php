<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aktywacja konta</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if($errors->any())
        <meta name="flash-error" content="{{ $errors->first() }}">
    @endif
    @vite(['resources/css/account-activation.css', 'resources/js/activate.js', 'resources/js/password-toggle.js'])
</head>
<body>

<main class="login-page activation-page" aria-labelledby="activation-title">
    <section class="login-card activation-card">
        <header class="login-header">
            <p class="activation-step">Krok {{ $step === 'verify' ? '1 z 2' : '2 z 2' }}</p>
            <h1 id="activation-title">Aktywacja konta</h1>
            <p>
                {{ $step === 'verify'
                    ? 'Potwierdź swoją tożsamość, podając datę urodzenia.'
                    : 'Tożsamość potwierdzona. Ustaw bezpieczne hasło do konta.' }}
            </p>
        </header>

    @if($step === 'verify')

        <form action="{{ route('account.verify', $token) }}" method="post">
            @csrf
            <div class="input-group">
                <label for="date-picker-trigger">Data urodzenia</label>
                <button
                    type="button"
                    id="date-picker-trigger"
                    class="date-picker-trigger"
                    aria-haspopup="dialog"
                    aria-controls="date-picker-dialog"
                    aria-expanded="false"
                >
                    <span class="date-picker-value">Wybierz datę urodzenia</span>
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <rect x="3" y="5" width="18" height="16" rx="2" />
                        <path d="M16 3v4M8 3v4M3 10h18" />
                    </svg>
                </button>
                <input type="hidden" id="date_of_birth" name="date_of_birth" value="{{ old('date_of_birth') }}">
                <p id="date-picker-error" class="field-error" hidden>Wybierz datę urodzenia.</p>
            </div>
            <button type="submit" class="login-btn">Potwierdź</button>
        </form>
    @else

        <form action="{{ route('account.activate.store', $token) }}" method="post">
            @csrf
            @include('guest.partials.password-fields')
            <button type="submit" class="login-btn">Aktywuj konto</button>
        </form>
    @endif
    </section>

    @if($step === 'verify')
        <dialog
            id="date-picker-dialog"
            class="date-picker-dialog"
            aria-labelledby="date-picker-title"
            aria-describedby="date-picker-help"
            data-month-title="Wybierz miesiąc"
            data-day-title="Wybierz dzień"
        >
            <div class="date-picker-panel">
                <header class="date-picker-header">
                    <div>
                        <span id="date-picker-progress" class="date-picker-progress">Krok 1 z 3</span>
                        <h2 id="date-picker-title">Wybierz rok</h2>
                        <p id="date-picker-help">Najpierw wybierz rok urodzenia.</p>
                    </div>
                    <button type="button" class="date-picker-close" aria-label="Zamknij wybór daty">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="m6 6 12 12M18 6 6 18" />
                        </svg>
                    </button>
                </header>

                <div class="date-picker-steps" aria-hidden="true">
                    <span class="is-active"></span>
                    <span></span>
                    <span></span>
                </div>

                <div id="date-picker-options" class="date-picker-options year-options"></div>

                <footer class="date-picker-actions">
                    <button type="button" class="date-picker-back" hidden>Wstecz</button>
                    <button type="button" class="date-picker-cancel">Anuluj</button>
                </footer>
            </div>
        </dialog>
    @endif
</main>

</body>
</html>
