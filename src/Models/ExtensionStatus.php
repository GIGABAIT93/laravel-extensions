<?php

namespace Gigabait93\Extensions\Models;

use Illuminate\Database\Eloquent\Model;

class ExtensionStatus extends Model
{
    protected $table = 'extensions';

    public $timestamps = false;

    protected $primaryKey = 'name';
    public $incrementing = false;
    protected $keyType = 'string';
    
    protected $fillable = [
        'name',
        'enabled',
    ];
}
