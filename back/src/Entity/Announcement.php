<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use DateTimeImmutable;
use Yawasla\Core\Model;

final class Announcement extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public ?int $id = null;
    public int $organization_id;
    public ?int $author_id = null;
    public string $type;
    public string $title;
    public string $content;
    public string $status = self::STATUS_DRAFT;
    public ?DateTimeImmutable $published_at = null;

    public static function table(): string
    {
        return 'announcements';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'author_id', 'type', 'title', 'content', 'status', 'published_at'];
    }
}
