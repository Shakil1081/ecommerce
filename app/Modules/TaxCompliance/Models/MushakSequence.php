<?php

declare(strict_types=1);

namespace App\Modules\TaxCompliance\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $document_type
 * @property string $year_month
 * @property int $last_sequence
 * @property CarbonInterface $created_at
 * @property CarbonInterface $updated_at
 */
class MushakSequence extends Model
{
    use HasFactory;

    protected $table = 'mushak_sequences';

    protected $fillable = [
        'document_type',
        'year_month',
        'last_sequence',
    ];

    protected function casts(): array
    {
        return [
            'last_sequence' => 'integer',
        ];
    }
}
