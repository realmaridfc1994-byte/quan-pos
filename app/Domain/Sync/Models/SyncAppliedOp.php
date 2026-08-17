<?php

declare(strict_types=1);

namespace App\Domain\Sync\Models;

use App\Models\BaseModel;

final class SyncAppliedOp extends BaseModel
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'op_uuid';

    public $timestamps = false;

    protected $fillable = [
        'op_uuid',
        'op_type',
        'device_id',
        'result_payload',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'result_payload' => 'array',
            'applied_at' => 'datetime',
        ];
    }
}
