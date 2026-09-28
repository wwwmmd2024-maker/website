<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

/**
 * Evaluates node display conditions. Pure logic over RenderContext —
 * no SQL, no I/O. ALL conditions must pass (AND semantics).
 */
final class ConditionEngine
{
    /** @param list<array<string, mixed>> $conditions */
    public function passes(array $conditions, RenderContext $ctx): bool
    {
        foreach ($conditions as $condition) {
            if (!$this->check($condition, $ctx)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string, mixed> $condition */
    private function check(array $condition, RenderContext $ctx): bool
    {
        return match ($condition['rule'] ?? '') {
            'logged_in' => $ctx->user !== null,
            'logged_out' => $ctx->user === null,
            'user_role' => $this->hasRole($condition['value'] ?? [], $ctx),
            'device' => $this->deviceMatches($condition['value'] ?? '', $ctx),
            'post_type' => $ctx->post !== null && ($ctx->post['post_type'] ?? '') === (string) ($condition['value'] ?? ''),
            'date_range' => $this->inDateRange($condition['value'] ?? '', $ctx),
            default => true,
        };
    }

    private function hasRole(mixed $roles, RenderContext $ctx): bool
    {
        if ($ctx->user === null) {
            return false;
        }
        $roles = is_array($roles) ? $roles : [$roles];
        $userRoles = $ctx->userRoles;

        return count(array_intersect($roles, $userRoles)) > 0;
    }

    private function deviceMatches(mixed $device, RenderContext $ctx): bool
    {
        $device = (string) $device;
        if ($device === '' || $device === 'all') {
            return true;
        }

        return $ctx->device === $device;
    }

    private function inDateRange(mixed $value, RenderContext $ctx): bool
    {
        // value: "YYYY-MM-DD..YYYY-MM-DD" (Gregorian storage dates).
        if (!is_string($value) || !str_contains($value, '..')) {
            return true;
        }
        [$from, $to] = explode('..', $value, 2);
        $now = $ctx->now->format('Y-m-d');

        if (trim($from) !== '' && $now < trim($from)) {
            return false;
        }
        if (trim($to) !== '' && $now > trim($to)) {
            return false;
        }

        return true;
    }
}
