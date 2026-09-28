<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Repositories\MenuRepository;
use IRJalali\App\Repositories\OptionRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class HomeController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly PostRepository $posts,
        private readonly OptionRepository $options,
        private readonly MenuRepository $menus,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        $menu = $this->menus->findByLocation('primary');
        $items = $menu !== null ? $this->menus->tree((int) $menu['id']) : [];

        return $this->render('front.home', [
            'siteTitle' => $this->options->get('site_title', 'سایت من'),
            'tagline' => $this->options->get('tagline', ''),
            'menu' => $items,
            'pages' => $this->posts->published('page', 20),
            'posts' => $this->posts->published('post', 6),
        ]);
    }

    public function page(Request $request): Response
    {
        $slug = (string) $request->route('slug');
        $post = $this->posts->findPublishedBySlug('page', $slug)
            ?? $this->posts->findPublishedBySlug('post', $slug);

        if ($post === null) {
            return Response::html($this->view->render('errors.404', [
                'flash' => $this->pullFlash(),
            ]), 404);
        }

        $menu = $this->menus->findByLocation('primary');

        return $this->render('front.page', [
            'siteTitle' => $this->options->get('site_title', 'سایت من'),
            'tagline' => $this->options->get('tagline', ''),
            'menu' => $menu !== null ? $this->menus->tree((int) $menu['id']) : [],
            'post' => $post,
        ]);
    }
}
