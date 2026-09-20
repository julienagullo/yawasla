<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use Yawasla\Core\Model;

final class Announcement extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public static function table(): string
    {
        return 'announcements';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'author_id', 'type', 'title', 'content', 'status', 'published_at'];
    }
}
