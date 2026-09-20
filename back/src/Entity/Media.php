<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use Yawasla\Core\Model;

final class Media extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public static function table(): string
    {
        return 'media';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'title', 'description', 'audio_path', 'status', 'published_at'];
    }
}
