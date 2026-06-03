<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Support\TimezoneQuery;

class Event extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'location_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user')
            ->withPivot('role_in_event')
            ->withTimestamps();
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Active events whose date range includes today (Asia/Jakarta).
     *
     * @param  Builder<Event>  $query
     * @return Builder<Event>
     */
    public function scopeCurrentlyRunning(Builder $query): Builder
    {
        $today = TimezoneQuery::todayDateString();

        $query->where('status', 'active');
        TimezoneQuery::whereDateColumnTo($query, 'start_date', $today);
        TimezoneQuery::whereDateColumnFrom($query, 'end_date', $today);

        return $query;
    }
}
