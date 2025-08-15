<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    /**
     * Os atributos que podem ser atribuídos em massa.
     *
     * @var array
     */
    protected $fillable = [
        'operation',
        'entity',
        'entity_id',
        'ou',
        'actor_uid',
        'actor_role',
        'result',
        'error_message',
        'changes_summary',
        'changes',
        'description',
    ];

    /**
     * Attribute casting configuration.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'changes' => 'array',
    ];
} 