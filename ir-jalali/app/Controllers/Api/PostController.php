<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Api;

use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;

final class PostController
{
    public function __construct(private readonly PostRepository $posts)
    {
    }

    public function index(Request $request): Response
    {
        $type = $request->str('type', 'post');
        if (!in_array($type, ['post', 'page'], true)) {
            $type = 'post';
        }
        $limit = min(50, max(1, (int) $request->input('limit', 10)));
        $page = max(1, (int) $request->input('page', 1));

        $items = array_map(
            fn($post) => $post->toPublicArray(),
            $this->posts->published($type, $limit, ($page - 1) * $limit)
        );

        return Response::json([
            'ok' => true,
            'type' => $type,
            'page' => $page,
            'data' => $items,
        ]);
    }
}
