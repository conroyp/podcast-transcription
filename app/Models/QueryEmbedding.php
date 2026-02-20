<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueryEmbedding extends Model
{
    use HasFactory;

    protected $fillable = [
        'query_hash',
        'query_text',
        'model',
        'dimensions',
        'use_count',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'use_count' => 'integer',
            'dimensions' => 'integer',
        ];
    }
}
