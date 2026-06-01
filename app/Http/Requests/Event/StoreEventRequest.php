<?php

namespace App\Http\Requests\Event;

use App\Enums\LocationType;
use App\Models\Location;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role->canManageEvents() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location_id' => ['required', 'uuid', 'exists:locations,id'],
            'name' => ['required', 'string'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string'],
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.user_id' => ['required', 'uuid', 'exists:users,id'],
            'assignments.*.role_in_event' => ['required', 'in:pic_bazar,sales'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->rejectCentralWarehouseLocation($validator, $this->input('location_id'));
        });
    }

    private function rejectCentralWarehouseLocation(Validator $validator, mixed $locationId): void
    {
        if (! is_string($locationId) || $locationId === '') {
            return;
        }

        $location = Location::query()->find($locationId);

        if ($location !== null && $location->location_type === LocationType::CENTRAL_WAREHOUSE) {
            $validator->errors()->add(
                'location_id',
                'Lokasi gudang pusat tidak dapat dijadikan event.',
            );
        }
    }
}
