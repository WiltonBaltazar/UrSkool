<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role as ModelsRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Role extends ModelsRole
{
    use HasUuids;

    protected $primaryKey = 'id'; // Ensure the primary key is set to 'id'
    protected $keyType = 'string'; // Set the key type to string for UUIDs
    public $incrementing = false; // Disable auto-incrementing for UUIDs

    protected static function booted()
    {
        static::addGlobalScope('exclude_super_admin', function (Builder $builder) {
            $builder->where('name', '!=', 'Super Admin');
        });
    }
}
