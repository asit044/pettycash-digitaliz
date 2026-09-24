<?php

namespace App\Models;

use App\Enums\RequestFileType;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashRequest extends Model
{
    use HasFactory;

    protected $table = 'requests';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(RequestFile::class, 'request_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RequestEvent::class, 'request_id');
    }

    public function filesOf(?RequestFileType $type = null): HasMany
    {
        return $this->files();
    }

    public function status(): RequestStatus
    {
        return RequestStatus::from($this->attributes['status'] ?? 'pending_review');
    }

    public function scopeWhereRequester(Builder $query, int $requesterId): Builder
    {
        return $query->where('requester_id', $requesterId);
    }

    public function scopeWhereStatus(Builder $query, ?string $status): Builder
    {
        return $status ? $query->where('status', $status) : $query;
    }

    public function hasBudgetCode(): bool
    {
        return filled($this->budget_code) && filled($this->budget_description);
    }

    public function hasOfficialReceipt(): bool
    {
        return $this->files()->where('type', RequestFileType::OfficialReceipt->value)->exists();
    }
}
