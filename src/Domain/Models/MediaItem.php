<?php

namespace SprayMedia\Domain\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model representing a stored MediaItem record.
 *
 * @property int $id
 * @property string $path
 * @property string $disk
 * @property string $filename
 * @property string $formatted_filename
 * @property string $extension
 * @property string|null $mime_type
 * @property int $size
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class MediaItem extends Model
{
    protected $table = 'media_items';

    protected $fillable = [
        'path',
        'disk',
        'filename',
        'formatted_filename',
        'extension',
        'mime_type',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
