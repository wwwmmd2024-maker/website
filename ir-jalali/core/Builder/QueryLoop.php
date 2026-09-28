<?php

declare(strict_types=1);

namespace IRJalali\Core\Builder;

use IRJalali\Core\Database\Database;

/**
 * Safe query builder for postlist/postgrid/queryloop nodes.
 * Every parameter is whitelisted — no raw SQL from the editor reaches here.
 */
final class QueryLoop
{
    public function __construct(private readonly Database $db)
    {
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public static function sanitizeQuery(array $query): array
    {
        $type = (string) ($query['post_type'] ?? 'post');
        if (!preg_match('/^[a-z0-9_\-]{1,40}$/i', $type)) {
            $type = 'post';
        }

        $orderBy = strtoupper((string) ($query['order_by'] ?? 'published_at'));
        if (!in_array($orderBy, ['PUBLISHED_AT', 'CREATED_AT', 'TITLE', 'ID', 'MENU_ORDER'], true)) {
            $orderBy = 'PUBLISHED_AT';
        }
        $order = strtoupper((string) ($query['order'] ?? 'DESC')) === 'ASC' ? 'ASC' : 'DESC';

        $out = [
            'post_type' => $type,
            'limit' => min(50, max(1, (int) ($query['limit'] ?? 6))),
            'offset' => min(1000, max(0, (int) ($query['offset'] ?? 0))),
            'order_by' => $orderBy,
            'order' => $order,
        ];

        if (isset($query['author_id']) && (int) $query['author_id'] > 0) {
            $out['author_id'] = (int) $query['author_id'];
        }
        if (isset($query['term_id']) && (int) $query['term_id'] > 0) {
            $out['term_id'] = (int) $query['term_id'];
        }
        if (isset($query['taxonomy'], $query['term_slug'])
            && is_string($query['taxonomy']) && is_string($query['term_slug'])
            && preg_match('/^[a-z0-9_\-]{1,40}$/i', $query['taxonomy'])
            && preg_match('/^[\p{L}\p{N}\-]{1,120}$/u', $query['term_slug'])) {
            $out['taxonomy'] = $query['taxonomy'];
            $out['term_slug'] = $query['term_slug'];
        }
        if (isset($query['meta_key'], $query['meta_value'])
            && is_string($query['meta_key']) && preg_match('/^[a-z0-9_\-]{1,60}$/i', $query['meta_key'])) {
            $out['meta_key'] = $query['meta_key'];
            $out['meta_value'] = mb_substr((string) $query['meta_value'], 0, 200);
        }

        return $out;
    }

    /** @param array<string, mixed> $query @return list<array<string, mixed>> */
    public function fetch(array $query): array
    {
        $query = self::sanitizeQuery($query);

        $sql = 'SELECT p.* FROM posts p';
        $params = [
            'type' => $query['post_type'],
        ];
        $wheres = ['p.post_type = :type', "p.status = 'published'", 'p.deleted_at IS NULL'];

        if (isset($query['author_id'])) {
            $wheres[] = 'p.author_id = :author';
            $params['author'] = $query['author_id'];
        }
        if (isset($query['term_id'])) {
            $sql .= ' INNER JOIN term_relationships tr ON tr.post_id = p.id AND tr.term_id = :term';
            $params['term'] = $query['term_id'];
        } elseif (isset($query['taxonomy'], $query['term_slug'])) {
            $sql .= ' INNER JOIN term_relationships tr ON tr.post_id = p.id'
                . ' INNER JOIN terms t ON t.id = tr.term_id AND t.slug = :tslug'
                . ' INNER JOIN taxonomies tx ON tx.id = t.taxonomy_id AND tx.slug = :txslug';
            $params['tslug'] = $query['term_slug'];
            $params['txslug'] = $query['taxonomy'];
        }
        if (isset($query['meta_key'])) {
            $sql .= ' INNER JOIN post_meta pm ON pm.post_id = p.id AND pm.key = :mkey AND pm.value = :mval';
            $params['mkey'] = $query['meta_key'];
            $params['mval'] = $query['meta_value'];
        }

        $column = match ($query['order_by']) {
            'TITLE' => 'p.title',
            'CREATED_AT' => 'p.created_at',
            'ID' => 'p.id',
            'MENU_ORDER' => 'p.menu_order',
            default => 'p.published_at',
        };
        $sql .= ' WHERE ' . implode(' AND ', $wheres)
            . " ORDER BY {$column} {$query['order']}"
            . ' LIMIT ' . $query['limit'] . ' OFFSET ' . $query['offset'];

        return $this->db->select($sql, $params);
    }
}
