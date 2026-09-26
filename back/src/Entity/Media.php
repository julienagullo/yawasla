<?php

declare(strict_types=1);

namespace Yawasla\Entity;

use DateTimeImmutable;
use Yawasla\Core\Model;

final class Media extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';

    public ?int $id = null;
    public int $organization_id;
    public ?int $author_id = null;
    public string $title;
    public ?string $description = null;
    public string $media_path;
    public string $status = self::STATUS_DRAFT;
    public ?DateTimeImmutable $published_at = null;

    public static function table(): string
    {
        return 'media';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'author_id', 'title', 'description', 'media_path', 'status', 'published_at'];
    }
}
