@php
    $user = $worker->relationLoaded('user') ? $worker->user : null;
    $accountState = match (true) {
        $user === null => 'missing',
        !$user->is_active && filled($user->activation_token) => 'pending',
        $user->is_active => 'active',
        default => 'inactive',
    };

    $workerData = [
        'id' => $worker->id,
        'firstName' => $worker->first_name,
        'lastName' => $worker->last_name,
        'phone' => $worker->phone,
        'address' => $worker->address,
        'dateOfBirth' => $worker->date_of_birth?->format('Y-m-d'),
        'isStudent' => (bool) $worker->is_student,
        'isEmployed' => (bool) $worker->is_employed,
        'contractFrom' => $worker->contract_from?->format('Y-m-d'),
        'contractTo' => $worker->contract_to?->format('Y-m-d'),
        'updateUrl' => route('workers.update', $worker),
        'deleteUrl' => route('workers.destroy', $worker),
        'account' => [
            'state' => $accountState,
            'email' => $user?->email,
            'createUrl' => route('workers.account.store', $worker),
            'regenerateUrl' => route('workers.account.regenerate', $worker),
            'passwordResetUrl' => route('workers.account.password-reset', $worker),
            'toggleUrl' => route('workers.account.toggle', $worker),
        ],
    ];
@endphp

<tr class="worker-row" data-worker-id="{{ $worker->id }}">
    <td class="worker-person-cell" data-label="Pracownik">
        <div>
            <strong>{{ $worker->first_name }} {{ $worker->last_name }}</strong>
            <small>ur. {{ $worker->date_of_birth?->format('d.m.Y') ?? '—' }}</small>
        </div>
    </td>
    <td class="worker-phone-cell" data-label="Telefon">{{ $worker->phone ?? '—' }}</td>
    <td data-label="Zatrudnienie">
        <span class="worker-status-badge {{ $worker->is_employed ? 'is-employed' : 'is-unemployed' }}">
            {{ $worker->is_employed ? 'Zatrudniony' : 'Niezatrudniony' }}
        </span>
    </td>
    <td data-label="Uczeń">
        <span class="worker-student-badge {{ $worker->is_student ? 'is-student' : '' }}">
            {{ $worker->is_student ? 'Tak' : 'Nie' }}
        </span>
    </td>
    <td class="worker-contract-cell" data-label="Umowa">
        <strong>{{ $worker->contract_from?->format('d.m.Y') ?? '—' }}</strong>
        <small>{{ $worker->contract_to ? 'do '.$worker->contract_to->format('d.m.Y') : 'bezterminowo' }}</small>
    </td>
    <td data-label="Konto">
        @switch($accountState)
            @case('active')
                <span class="worker-account-badge is-active">Aktywne</span>
                @break
            @case('pending')
                <span class="worker-account-badge is-pending">Oczekuje</span>
                @break
            @case('inactive')
                <span class="worker-account-badge is-inactive">Nieaktywne</span>
                @break
            @default
                <button type="button" class="workers-ghost-button js-account-direct">Generuj konto</button>
        @endswitch
    </td>
    <td class="worker-actions-cell" data-label="Akcje">
        <button type="button" class="workers-ghost-button js-edit-worker">Edytuj</button>
    </td>
    <td class="worker-data-cell" hidden>
        <script type="application/json" class="worker-json">@json($workerData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)</script>
    </td>
</tr>
