<div class="workers-table-shell">
    <table class="workers-table">
        <thead>
            <tr>
                <th>Pracownik</th>
                <th>Telefon</th>
                <th>Status zatrudnienia</th>
                <th>Status ucznia</th>
                <th>Umowa</th>
                <th>Konto</th>
                <th>Akcje</th>
            </tr>
        </thead>
        <tbody id="workersTableBody">
            @forelse($workers as $worker)
                @include('admin.workers.partials.card', ['worker' => $worker])
            @empty
                <tr class="workers-empty-row">
                    <td colspan="7">
                        <span class="workers-mono-label">[ brak wyników ]</span>
                        <p>Zmień zapytanie lub ustawienia filtrów.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
