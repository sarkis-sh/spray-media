<?php

namespace SprayMedia\Domain\Enums;

/**
 * Available actions for media links.
 */
enum MediaAction: string
{
    case VIEW = 'view';
    case DOWNLOAD = 'download';

    /**
     * Safely create enum from string; returns null on invalid input.
     */
    public static function fromString(string $value): ?self
    {
        return match ($value) {
            'view' => self::VIEW,
            'download' => self::DOWNLOAD,
            default => null,
        };
    }
}
