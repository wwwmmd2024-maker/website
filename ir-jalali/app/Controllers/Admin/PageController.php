<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

final class PageController extends ContentController
{
    protected function type(): string
    {
        return 'page';
    }

    protected function permBase(): string
    {
        return 'pages';
    }

    protected function baseUrl(): string
    {
        return '/admin/pages';
    }

    protected function activeKey(): string
    {
        return 'pages';
    }
}
