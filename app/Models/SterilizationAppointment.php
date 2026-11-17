<?php

namespace App\Models;

use App\Services\Campaign\CaseFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Programare din calendarul campaniei (tabela hv_st_appointments din plugin). */
class SterilizationAppointment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['source_row' => 'array', 'appointment_date' => 'date', 'reminder_sent_at' => 'datetime', 'reminder_manual_sent_at' => 'datetime',
            'email_last_at' => 'datetime', 'reminder_days' => 'integer'];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SterilizationCase::class, 'sterilization_case_id');
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(SterilizationEmailLog::class, 'appointment_id')->latest('id');
    }

    /** Proprietar · pacient, ca appointment_calendar_label() din plugin. */
    public function label(): string
    {
        $parts = array_filter([trim((string) $this->owner_name), trim((string) $this->patient_name)]);

        return $parts ? implode(' · ', $parts) : (string) $this->title;
    }

    public function endTime(): string
    {
        $start = CaseFields::minutes($this->start_time);
        $end = CaseFields::minutes($this->end_time);

        return CaseFields::timeFromMinutes($end > $start ? $end : $start + 60);
    }
}
