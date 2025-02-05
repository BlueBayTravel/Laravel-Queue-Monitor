<?php

namespace romanzipp\QueueMonitor\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class Monitor extends Model
{
    protected $fillable = [
        'job_id',
        'name',
        'queue',
        'started_at',
        'started_at_exact',
        'finished_at',
        'finished_at_exact',
        'time_elapsed',
        'failed',
        'attempt',
        'exception',
        'progress',
        'data',
        'tags',
    ];

    protected $casts = [
        'failed' => 'boolean',
    ];

    protected $dates = [
        'started_at',
        'finished_at',
    ];

    public $timestamps = false;

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(config('queue-monitor.table'));
    }

    /**
     * Scopes
     */

    public function scopeWhereJob($query, $jobId)
    {
        return $query->where('job_id', $jobId);
    }

    public function scopeOrdered($query)
    {
        return $query
            ->orderBy('started_at', 'desc')
            ->orderBy('started_at_exact', 'desc');
    }

    public function scopeLastHour($query)
    {
        return $query->where('started_at', '>', Carbon::now()->subHours(1));
    }

    public function scopeToday($query)
    {
        return $query->whereRaw('DATE(started_at) = ?', [Carbon::now()->subHours(1)->format('Y-m-d')]);
    }

    public function scopeFailed($query)
    {
        return $query->where('failed', true);
    }

    public function scopeSucceeded($query)
    {
        return $query->where('failed', false);
    }

    /**
     * Methods
     */

    public function startedAtExact(): Carbon
    {
        return Carbon::parse($this->started_at_exact);
    }

    public function finishedAtExact(): Carbon
    {
        return Carbon::parse($this->finished_at_exact);
    }

    public function getBasenameAttribute()
    {
        return Arr::last(explode('\\', $this->name));
    }

    public function getParsedDataAttribute(): array
    {
        return json_decode($this->data, true) ?? [];
    }

    public function getRemainingSecondsAttribute():  ? float
    {
        if ($this->isFinished()) {
            return null;
        }

        if ($this->progress === null) {
            return null;
        }

        if ( ! $this->started_at) {
            return null;
        }

        $secondsRunning = now()->getTimestamp() - $this->started_at->getTimestamp();

        return (float) ($secondsRunning - ($secondsRunning * $this->progress / 100));
    }

    public function basename()
    {
        return $this->basename;
    }

    /**
     * Determine wether job is finished
     * @return boolean
     */
    public function isFinished() : bool
    {
        if ($this->failed) {
            return true;
        }

        return $this->finished_at !== null;
    }

    /**
     * Determine wether job has succeeded
     * @return boolean
     */
    public function isSucceeded(): bool
    {
        if ( ! $this->isFinished()) {
            return false;
        }

        return $this->failed == false;
    }
}
