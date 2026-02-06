<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $type
 * @property string $extension_id
 * @property string $status
 * @property int $progress
 * @property string|null $message
 * @property array<string,mixed>|null $context
 * @property array<string,mixed>|null $result
 * @property string|null $error
 * @property \Illuminate\Support\Carbon|null $started_at
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class ExtensionOperation extends Model
{
    protected $table = 'extension_operations';

    protected $primaryKey = 'id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'type',
        'extension_id',
        'status',
        'progress',
        'message',
        'context',
        'result',
        'error',
        'started_at',
        'completed_at',
        'created_at',
        'updated_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'result' => 'array',
            'progress' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }
}
