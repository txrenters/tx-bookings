<?php

namespace App\Http\Requests\Scheduling;

use App\Enums\DateRangeType;
use App\Enums\EventTypeKind;
use App\Enums\LocationType;
use App\Enums\QuestionType;
use App\Models\EventType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveEventTypeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $eventType = $this->route('event_type');
        $teamId = $eventType instanceof EventType ? $eventType->team_id : $this->user()->current_team_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('event_types', 'slug')
                    ->where('team_id', $teamId)
                    ->ignore($eventType instanceof EventType ? $eventType->id : null)
                    ->whereNull('deleted_at'),
                Rule::notIn(['book', 'bookings', 'settings', 'dashboard', 'login', 'register']),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'kind' => ['required', Rule::enum(EventTypeKind::class)],
            'color' => ['nullable', 'string', 'max:20'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:1440'],
            'slot_interval_minutes' => ['nullable', 'integer', 'min:5', 'max:1440'],
            'buffer_before_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'buffer_after_minutes' => ['required', 'integer', 'min:0', 'max:720'],
            'minimum_notice_minutes' => ['required', 'integer', 'min:0', 'max:100000'],
            'daily_booking_limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'seats_per_slot' => ['required', 'integer', 'min:1', 'max:500'],
            'date_range_type' => ['required', Rule::enum(DateRangeType::class)],
            'rolling_days' => ['required', 'integer', 'min:1', 'max:730'],
            'range_starts_on' => ['nullable', 'date', 'required_if:date_range_type,fixed_range'],
            'range_ends_on' => ['nullable', 'date', 'after_or_equal:range_starts_on', 'required_if:date_range_type,fixed_range'],
            'location_type' => ['required', Rule::enum(LocationType::class)],
            'location_detail' => ['nullable', 'string', 'max:500'],
            'availability_schedule_id' => [
                'nullable',
                Rule::exists('availability_schedules', 'id')->where('user_id', $this->user()->id),
            ],
            'is_active' => ['boolean'],
            'is_hidden' => ['boolean'],

            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $teamId),
            ],

            'group_id' => [
                'nullable',
                'integer',
                Rule::exists('groups', 'id')->where('team_id', $teamId),
            ],

            'host_ids' => ['array'],
            'host_ids.*' => [
                'integer',
                Rule::exists('team_members', 'user_id')->where('team_id', $teamId),
            ],

            'questions' => ['array', 'max:20'],
            'questions.*.id' => ['nullable', 'integer'],
            'questions.*.type' => ['required', Rule::enum(QuestionType::class)],
            'questions.*.label' => ['required', 'string', 'max:255'],
            'questions.*.help_text' => ['nullable', 'string', 'max:255'],
            'questions.*.options' => ['nullable', 'array', 'max:50'],
            'questions.*.options.*' => ['string', 'max:255'],
            'questions.*.is_required' => ['boolean'],
        ];
    }

    /**
     * Get the custom validation rules that run after the basic rules pass.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                $kind = EventTypeKind::tryFrom((string) $this->input('kind'));
                $location = LocationType::tryFrom((string) $this->input('location_type'));

                if ($kind?->hasHostPool()
                    && blank($this->input('group_id'))
                    && count($this->input('host_ids', [])) < 1
                ) {
                    $validator->errors()->add('host_ids', 'Pick a group, or at least one host, for this event type.');
                }

                if ($location?->requiresHostDetail() && blank($this->input('location_detail'))) {
                    $validator->errors()->add('location_detail', 'Add the '.strtolower($location->detailLabel()).'.');
                }
            },
        ];
    }
}
