<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

/**
 * Everything the renderer may need — resolved ONCE per page render
 * (no per-node queries).
 *
 * @phpstan-type UserRow array<string, mixed>|null
 * @phpstan-type PostRow array<string, mixed>|null
 */
final class RenderContext
{
    /** @param array<string, mixed>|null $user @param array<string, mixed>|null $post @param list<string> $userRoles */
    public function __construct(
        public readonly ?array $user,
        public readonly ?array $post,
        public readonly array $userRoles = [],
        public readonly string $device = 'desktop',
        public readonly ?\DateTimeImmutable $now = null,
        public readonly bool $isPreview = false,
    ) {
    }

    public function timestamp(): \DateTimeImmutable
    {
        return $this->now ?? new \DateTimeImmutable();
    }

    /** @param array<string, mixed>|null $post */
    public function withPost(?array $post): self
    {
        return new self($this->user, $post, $this->userRoles, $this->device, $this->now, $this->isPreview);
    }
}
