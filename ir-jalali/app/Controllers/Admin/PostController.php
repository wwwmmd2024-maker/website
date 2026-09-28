<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

final class PostController extends ContentController
{
    protected function type(): string
    {
        return 'post';
    }

    protected function permBase(): string
    {
        return 'posts';
    }

    protected function baseUrl(): string
    {
        return '/admin/posts';
    }

    protected function activeKey(): string
    {
        return 'posts';
    }
}
