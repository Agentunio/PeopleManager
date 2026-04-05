<div class="settings-container" data-worker-id="{{ $worker->id }}">
    <div class="settings-section">
        <div class="package-header-row">
            <div class="worker-name-status">
                <h2>{{ $worker->first_name }} {{ $worker->last_name }}</h2>
                @if($worker->relationLoaded('user') && $worker->user)
                    @if(!$worker->user->is_active && $worker->user->activation_token)
                        <span class="account-badge badge-pending">
                            <i class="fas fa-clock"></i> Konto oczekuje aktywacji
                        </span>
                    @elseif($worker->user->is_active)
                        <span class="account-badge badge-active">
                            <i class="fas fa-check-circle"></i> Konto aktywne
                        </span>
                    @else
                        <span class="account-badge badge-inactive">
                            <i class="fas fa-ban"></i> Konto dezaktywowane
                        </span>
                    @endif
                @endif
            </div>
            <div class="package-actions">
                <form action="{{ route('workers.destroy', $worker) }}" method="post" class="delete-form" data-name="{{ $worker->first_name }} {{ $worker->last_name }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-delete" type="submit">
                        <i class="fas fa-trash-alt"></i>
                        Usuń
                    </button>
                </form>
            </div>
        </div>

        <div class="worker-info-grid">
            <div class="worker-info-item">
                <span class="label">Telefon</span>
                <span class="value">{{ $worker->phone ?? 'Brak' }}</span>
            </div>
            <div class="worker-info-item">
                <span class="label">Adres</span>
                <span class="value">{{ $worker->address ?? 'Brak' }}</span>
            </div>
            <div class="worker-info-item">
                <span class="label">Status ucznia</span>
                <span class="value">{{ $worker->is_student ? 'Tak' : 'Nie' }}</span>
            </div>
            <div class="worker-info-item">
                <span class="label">Status zatrudnienia</span>
                <span class="value">{{ $worker->is_employed ? 'Tak' : 'Nie' }}</span>
            </div>
            <div class="worker-info-item">
                <span class="label">Data urodzenia</span>
                <span class="value">{{ $worker->date_of_birth?->format('Y-m-d') ?? 'Brak' }}</span>
            </div>
            <div class="worker-info-item">
                <span class="label">Umowa</span>
                <span class="value">
                    {{ $worker->contract_from && $worker->contract_to
                        ? $worker->contract_from->format('Y-m-d') . ' do ' . $worker->contract_to->format('Y-m-d')
                        : 'Brak' }}
                </span>
            </div>
        </div>

        <div class="worker-action-buttons">
            <label for="toggle-edit-worker-{{ $worker->id }}" class="btn btn-change">
                <i class="fas fa-edit"></i>
                Edytuj Dane
            </label>

            @if($worker->relationLoaded('user') && $worker->user)
                @if(!$worker->user->is_active && $worker->user->activation_token)
                    <button class="btn btn-account btn-pending-account"
                            data-url="{{ route('workers.account.regenerate', $worker) }}"
                            data-email="{{ $worker->user->email }}">
                        <i class="fas fa-redo"></i>
                        Wyślij ponownie
                    </button>
                @elseif($worker->user->is_active)
                    <button class="btn btn-account btn-deactivate" data-url="{{ route('workers.account.toggle', $worker) }}">
                        <i class="fas fa-user-slash"></i>
                        Dezaktywuj
                    </button>
                @else
                    <button class="btn btn-account btn-activate" data-url="{{ route('workers.account.toggle', $worker) }}">
                        <i class="fas fa-user-check"></i>
                        Aktywuj
                    </button>
                @endif
            @else
                @if($worker->date_of_birth)
                    <button class="btn btn-account btn-generate" data-url="{{ route('workers.account.store', $worker) }}">
                        <i class="fas fa-user-plus"></i>
                        Generuj konto
                    </button>
                @else
                    <button class="btn btn-account btn-disabled" disabled title="Najpierw dodaj datę urodzenia">
                        <i class="fas fa-user-plus"></i>
                        Generuj konto
                    </button>
                @endif
            @endif
        </div>

        <hr class="section-separator">

        <div class="current-amount">
            <div class="financial-stats-stack">
                <div class="amount-info">
                    <span class="amount-label">Godziny (w zakresie):</span>
                    <span class="amount-value" id="hours-value-{{ $worker->id }}">{{ $worker->stats['hours'] ?? '0' }}</span>
                </div>
                <div class="amount-info">
                    <span class="amount-label">Wynagrodzenie (w zakresie):</span>
                    <span class="amount-value" id="salary-value-{{ $worker->id }}">{{ number_format($worker->stats['salary'] ?? 0, 2, ',', '') }}</span>
                    <span class="currency">PLN</span>
                </div>
            </div>
            <div class="financial-controls">
                <label for="toggle-range-{{ $worker->id }}" class="btn btn-change">
                    <i class="fas fa-calendar-alt"></i>
                    Zmień zakres
                </label>
            </div>
        </div>

        <input type="checkbox" id="toggle-range-{{ $worker->id }}">

        <div class="edit-form date-range-form">
            <form class="date-range-form-inner" data-worker-id="{{ $worker->id }}" data-stats-url="{{ route('workers.stats', $worker) }}">
                <div class="form-group flatpickr-form-group">
                    <label for="flatpickr-range-{{ $worker->id }}" class="form-label">Wybierz zakres dat</label>
                    <input type="text" id="flatpickr-range-{{ $worker->id }}" class="form-input flatpickr-range-input"
                           placeholder="Kliknij, aby wybrać zakres..." readonly="readonly">
                </div>
                <input type="date" id="date-from-{{ $worker->id }}" name="dateFrom" class="form-input hidden-date-input" required>
                <input type="date" id="date-to-{{ $worker->id }}" name="dateTo" class="form-input hidden-date-input" required>
                <div class="form-actions">
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-filter"></i>
                        Filtruj
                    </button>
                </div>
            </form>
        </div>

        <input type="checkbox" id="toggle-edit-worker-{{ $worker->id }}">

        <div class="edit-form">
            <form class="edit-worker-form" action="{{ route('workers.update', $worker) }}" method="post">
                @csrf
                @method('PUT')

                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit-workerFirstName-{{ $worker->id }}" class="form-label">Imię</label>
                        <input type="text" id="edit-workerFirstName-{{ $worker->id }}" name="first_name" class="form-input" value="{{ $worker->first_name }}" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-workerLastName-{{ $worker->id }}" class="form-label">Nazwisko</label>
                        <input type="text" id="edit-workerLastName-{{ $worker->id }}" name="last_name" class="form-input" value="{{ $worker->last_name }}" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-workerPhone-{{ $worker->id }}" class="form-label">Numer telefonu</label>
                        <input type="tel" id="edit-workerPhone-{{ $worker->id }}" name="phone" class="form-input" value="{{ $worker->phone }}">
                    </div>
                    <div class="form-group">
                        <label for="edit-workerAddress-{{ $worker->id }}" class="form-label">Miejsce zamieszkania</label>
                        <input type="text" id="edit-workerAddress-{{ $worker->id }}" name="address" class="form-input" value="{{ $worker->address }}">
                    </div>
                    <div class="form-group">
                        <label for="edit-workerDob-{{ $worker->id }}" class="form-label">Data urodzenia</label>
                        <input type="date" id="edit-workerDob-{{ $worker->id }}" name="date_of_birth" class="form-input" value="{{ $worker->date_of_birth?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label for="edit-workerStudentStatus-{{ $worker->id }}" class="form-label">Status ucznia</label>
                        <select id="edit-workerStudentStatus-{{ $worker->id }}" name="is_student" class="form-input">
                            <option value="0" @selected(!$worker->is_student)>Nie</option>
                            <option value="1" @selected($worker->is_student)>Tak</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-workerEmploymentStatus-{{ $worker->id }}" class="form-label">Status zatrudnienia</label>
                        <select id="edit-workerEmploymentStatus-{{ $worker->id }}" name="is_employed" class="form-input">
                            <option value="1" @selected($worker->is_employed)>Tak</option>
                            <option value="0" @selected(!$worker->is_employed)>Nie</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-workerContractFrom-{{ $worker->id }}" class="form-label">Umowa od daty</label>
                        <input type="date" id="edit-workerContractFrom-{{ $worker->id }}" name="contract_from" class="form-input" value="{{ $worker->contract_from?->format('Y-m-d') }}">
                    </div>
                    <div class="form-group">
                        <label for="edit-workerContractTo-{{ $worker->id }}" class="form-label">Umowa do daty</label>
                        <input type="date" id="edit-workerContractTo-{{ $worker->id }}" name="contract_to" class="form-input" value="{{ $worker->contract_to?->format('Y-m-d') }}">
                    </div>
                </div>

                <div class="form-actions">
                    <label for="toggle-edit-worker-{{ $worker->id }}" class="btn btn-cancel">Anuluj</label>
                    <button type="submit" class="btn btn-submit">Zatwierdź Zmiany</button>
                </div>
            </form>
        </div>
    </div>
</div>
