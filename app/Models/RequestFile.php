<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestFile extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function request(): BelongsTo
    {
        return $this->belongsTo(PettyCashRequest::class, 'request_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
