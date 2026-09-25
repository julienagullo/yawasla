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
    public string $title;
    public ?string $description = null;
    public string $audio_path;
    public string $status = self::STATUS_DRAFT;
    public ?DateTimeImmutable $published_at = null;

    public static function table(): string
    {
        return 'media';
    }

    public static function fillable(): array
    {
        return ['organization_id', 'title', 'description', 'audio_path', 'status', 'published_at'];
    }
}
