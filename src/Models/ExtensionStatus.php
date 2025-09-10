<?php

declare(strict_types=1);

namespace Gigabait93\Extensions\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
class ExtensionStatus extends Model
{
    protected $table = 'extensions';

    public $timestamps = false;

    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'type',
        'enabled',
    ];
}
