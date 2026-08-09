<?php

namespace App\Http\Requests\Admin;

use App\Models\Worker;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class WorkerStoreAvailabilityRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $workers = $this->input('workers', []);

        if (is_array($workers)) {
            foreach ($workers as $key => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                foreach (['morning_shift', 'afternoon_shift'] as $field) {
                    if (! array_key_exists($field, $entry)
                        || $entry[$field] === null
                        || $entry[$field] === '') {
                        $workers[$key][$field] = false;
                    } elseif ($entry[$field] === 'on') {
                        $workers[$key][$field] = true;
                    }
                }
            }
        }

        $this->merge([
            '_route_date' => (string) $this->route('date'),
            'workers' => $workers,
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            '_route_date' => ['required', Rule::date()->format('Y-m-d')],
            'workers' => ['required', 'array'],
            'workers.*.worker_id' => ['bail', 'required', 'integer', 'distinct'],
            'workers.*.morning_shift' => ['present', 'boolean'],
            'workers.*.afternoon_shift' => ['present', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $entries = is_array($this->input('workers')) ? $this->input('workers') : [];
            $validEntries = [];

            foreach ($entries as $key => $entry) {
                if (! is_array($entry) || $validator->errors()->has("workers.{$key}.worker_id")) {
                    continue;
                }

                $validEntries[$key] = (int) $entry['worker_id'];
            }

            if ($validEntries === []) {
                return;
            }

            $knownWorkerIds = Worker::query()
                ->whereKey(array_values(array_unique($validEntries)))
                ->pluck('id')
                ->all();
            $knownWorkerLookup = array_fill_keys($knownWorkerIds, true);

            foreach ($validEntries as $key => $workerId) {
                if (! isset($knownWorkerLookup[$workerId])) {
                    $validator->errors()->add("workers.{$key}.worker_id", 'Wybrany pracownik jest nieprawidłowy.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'worker_id.required' => 'ID pracownika jest wymagane.',
        ];
    }
}
