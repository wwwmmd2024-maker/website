<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers;

use IRJalali\App\Models\Post;
use IRJalali\App\Repositories\MenuRepository;
use IRJalali\App\Repositories\OptionRepository;
use IRJalali\App\Repositories\PostRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Builder\GlobalStyles;
use IRJalali\Core\Builder\RenderContext;
use IRJalali\Core\Builder\Renderer;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Hooks\Hooks;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Themes\Theme;
use IRJalali\Core\Themes\ThemeManager;
use IRJalali\Core\Themes\TemplateResolver;
use IRJalali\Core\View\View;
use IRJalali\Core\Widgets\WidgetRegistry;

/**
 * Theme-driven frontend: home, singular, search and 404 through the
 * active theme's template hierarchy. Falls back to classic views only
 * when no usable theme exists.
 */
final class FrontController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly PostRepository $posts,
        private readonly OptionRepository $options,
        private readonly MenuRepository $menus,
        private readonly ThemeManager $themes,
        private readonly TemplateResolver $templates,
        private readonly Renderer $renderer,
        private readonly WidgetRegistry $widgets,
        private readonly GlobalStyles $styles,
        private readonly Database $db,
        private readonly Hooks $hooks,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        $theme = $this->themes->active();
        if ($theme === null) {
            return $this->classicHome();
        }

        $ctx = $this->renderContext($request);
        $showOnFront = (string) $this->options->get('show_on_front', 'posts');

        if ($showOnFront === 'page') {
            $pageId = (int) $this->options->get('page_on_front', 0);
            $post = $pageId > 0 ? $this->posts->find($pageId) : null;
            if ($post !== null && $post->status === 'published') {
                return $this->singular($request, $theme, $ctx, $post, 'page');
            }
        }

        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(1, min(50, (int) $this->options->get('posts_per_page', 9)));
        $posts = $this->posts->published('post', $perPage, ($page - 1) * $perPage);
        $total = $this->posts->countByType('post', 'published');

        $file = $this->templates->resolve($theme->slug, 'home')
            ?? $this->templates->resolve($theme->slug, 'index');
        if ($file === null) {
            return $this->classicHome();
        }

        $data = $this->baseData($theme, $ctx, (string) $this->options->get('site_title', 'سایت من'));
        $data['posts'] = array_map(fn (Post $p): array => $this->postArray($p), $posts);
        $data['pagination'] = [
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => max(1, (int) ceil($total / $perPage)),
            'base_url' => '/',
        ];

        return Response::html($this->themes->renderFile($file, $data));
    }

    public function page(Request $request): Response
    {
        $theme = $this->themes->active();
        $slug = (string) $request->route('slug', '');
        $post = $this->posts->findPublishedBySlug('page', $slug)
            ?? $this->posts->findPublishedBySlug('post', $slug)
            ?? $this->findCustomPostBySlug($slug);

        if ($theme === null) {
            return $this->classicPage($post);
        }
        if ($post === null) {
            /** Extension point: plugins (IR-SEO redirects) may claim the missing slug. */
            $redirect = $this->hooks->applyFilters('front.redirect', null, $slug, $request);
            if (is_string($redirect) && $redirect !== '') {
                return Response::redirect($redirect, 301);
            }
            $this->hooks->doAction('front.not_found', $slug, $request);

            return $this->notFound($theme, $this->renderContext($request));
        }

        return $this->singular($request, $theme, $this->renderContext($request), $post, $post->type === 'page' ? 'page' : 'single');
    }

    /** Custom post types registered by plugins/modules resolve here (no core hardcoding). */
    private function findCustomPostBySlug(string $slug): ?Post
    {
        if ($slug === '') {
            return null;
        }
        try {
            $row = $this->db->first(
                'SELECT * FROM posts WHERE slug = :slug AND status = :status AND deleted_at IS NULL AND post_type NOT IN (:a, :b) ORDER BY published_at DESC, id DESC LIMIT 1',
                ['slug' => $slug, 'status' => 'published', 'a' => 'page', 'b' => 'post']
            );
        } catch (\Throwable) {
            return null;
        }

        return $row !== null ? Post::fromRow($row) : null;
    }

    public function search(Request $request): Response
    {
        $theme = $this->themes->active();
        if ($theme === null) {
            return $this->classicHome();
        }

        $query = mb_substr(trim($request->str('q', '')), 0, 120);
        $posts = [];
        if ($query !== '') {
            $like = '%' . $query . '%';
            $rows = $this->db->select(
                'SELECT * FROM posts WHERE post_type = :t AND status = :s AND deleted_at IS NULL AND (title LIKE :q OR excerpt LIKE :q) ORDER BY published_at DESC, id DESC LIMIT 20',
                ['t' => 'post', 's' => 'published', 'q' => $like]
            );
            foreach ($rows as $row) {
                $posts[] = Post::fromRow($row);
            }
        }

        $file = $this->templates->resolve($theme->slug, 'search')
            ?? $this->templates->resolve($theme->slug, 'archive')
            ?? $this->templates->resolve($theme->slug, 'index');
        if ($file === null) {
            return $this->classicHome();
        }

        $data = $this->baseData($theme, $this->renderContext($request), 'جستجو: ' . $query);
        $data['posts'] = array_map(fn (Post $p): array => $this->postArray($p), $posts);
        $data['query'] = $query;

        return Response::html($this->themes->renderFile($file, $data));
    }

    // ── Singular ─────────────────────────────────────────────────
    private function singular(Request $request, Theme $theme, RenderContext $ctx, Post $post, string $kind): Response
    {
        // Explicit per-post template choice wins over the hierarchy.
        $file = null;
        if ($post->template !== null && $post->template !== '') {
            $file = $this->themes->file($theme->slug, 'templates/' . basename($post->template));
        }
        $file ??= $this->templates->resolve($theme->slug, $kind, $post->slug);
        if ($file === null) {
            return $this->classicPage($post);
        }

        $data = $this->baseData($theme, $ctx, $post->title);
        $data['post'] = $this->postArray($post);
        $rendered = $this->postContent($post, $ctx->withPost($data['post']));
        $data['content'] = $rendered['html'];
        if ($rendered['css'] !== '') {
            $data['headCss'] .= "\n" . $rendered['css'];
        }

        return Response::html($this->themes->renderFile($file, $data));
    }

    /** @return array{html: string, css: string} */
    private function postContent(Post $post, RenderContext $ctx): array
    {
        $tree = $this->decodeTree((string) ($post->row['content_json'] ?? ''));
        if ($tree !== null) {
            $output = $this->renderer->render($tree, $ctx);

            return ['html' => $output->html, 'css' => $output->css];
        }

        $html = (string) ($post->content ?? '');
        $html = $this->hooks->applyFilters('the_content', $html, $post);

        return ['html' => $html !== '' ? '<div class="ij-classic-content">' . $html . '</div>' : '', 'css' => ''];
    }

    // ── Theme data contract ──────────────────────────────────────
    /** @return array<string, mixed> */
    private function baseData(Theme $theme, RenderContext $ctx, string $pageTitle): array
    {
        $siteTitle = (string) $this->options->get('site_title', 'سایت من');
        $data = [
            'lang' => 'fa',
            'dir' => 'rtl',
            'pageTitle' => $pageTitle,
            'siteTitle' => $siteTitle,
            'tagline' => (string) $this->options->get('tagline', ''),
            'headCss' => $this->styles->cssVariables(),
            'themeCssUrls' => $this->themeCssUrls($theme),
            'frontCssUrl' => '/assets/css/builder-front.css',
            'frontJsUrl' => '/assets/js/builder-front.js',
            'headerHtml' => '',
            'footerHtml' => '',
            'content' => '',
            'sidebar' => '',
            'post' => null,
            'posts' => [],
            'pagination' => null,
            'menu' => $this->primaryMenu(),
            'theme' => $theme,
            'themeManager' => $this->themes,
            'flash' => $this->pullFlash(),
        ];
        $data['headerHtml'] = $this->sitePart('header', $theme, $ctx, $data);
        $data['footerHtml'] = $this->sitePart('footer', $theme, $ctx, $data);
        if (!empty($theme->settings['sidebar'])) {
            $data['sidebar'] = $this->widgets->renderSidebar('primary', $ctx);
        }
        $data['headCss'] = $this->hooks->applyFilters('theme.head_css', $data['headCss'], $theme);
        /** Extension point: SEO meta, analytics beacons, PWA manifest links, … */
        $data['headMeta'] = $this->hooks->applyFilters('front.head_meta', '', $data, $ctx);
        /** Extension point: content injected right after <body>. */
        $data['bodyOpen'] = $this->hooks->applyFilters('front.body_open', '', $data, $ctx);
        /** Extension point: content injected right before </body>. */
        $data['bodyClose'] = $this->hooks->applyFilters('front.body_close', '', $data, $ctx);

        return $data;
    }

    /** DB builder header/footer win; theme parts are the fallback. */
    private function sitePart(string $part, Theme $theme, RenderContext $ctx, array $data): string
    {
        $source = (string) $this->options->get('site_' . $part . '_source', 'db');
        if ($source !== 'theme') {
            $row = $this->db->table('templates')->where('slug', 'site-' . $part)->first();
            if ($row !== null) {
                $tree = $this->decodeTree((string) ($row['content_json'] ?? ''));
                if ($tree !== null) {
                    return $this->renderer->render($tree, $ctx)->html;
                }
            }
        }

        $file = $this->themes->file($theme->slug, 'parts/' . $part . '.php');

        return $file !== null ? $this->themes->renderFile($file, $data) : '';
    }

    /** @return list<string> parent-first so child CSS overrides. */
    private function themeCssUrls(Theme $theme): array
    {
        $urls = [];
        foreach (array_reverse($this->themes->chain($theme->slug)) as $chainTheme) {
            if (is_file($chainTheme->path . '/style.css')) {
                $urls[] = $this->themes->assetUrl($chainTheme->slug, 'style.css');
            }
        }

        return $urls;
    }

    private function primaryMenu(): array
    {
        $menu = $this->menus->findByLocation('primary');

        return $menu !== null ? $this->menus->tree((int) $menu['id']) : [];
    }

    /** @return array<string, mixed> */
    private function postArray(Post $post): array
    {
        return [
            'id' => $post->id,
            'type' => $post->type,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt ?? '',
            'url' => '/' . $post->slug,
            'author_id' => $post->authorId,
            'featured_image' => $post->featuredImage,
            'published_at' => $post->publishedAt,
        ];
    }

    private function renderContext(Request $request): RenderContext
    {
        return new RenderContext(
            user: $this->auth->user(),
            post: null,
            userRoles: $this->auth->check() ? $this->auth->roles() : [],
            device: Renderer::detectDevice($request->userAgent()),
            now: new \DateTimeImmutable(),
            isPreview: false,
        );
    }

    private function decodeTree(string $json): ?array
    {
        if ($json === '') {
            return null;
        }
        $decoded = json_decode($json, true);
        $root = is_array($decoded) ? ($decoded['root'] ?? null) : null;
        if (!is_array($root) || ($root['type'] ?? '') === '') {
            return null;
        }

        return $root;
    }

    private function notFound(Theme $theme, RenderContext $ctx): Response
    {
        $file = $this->templates->resolve($theme->slug, '404');
        if ($file === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }
        $data = $this->baseData($theme, $ctx, 'صفحه یافت نشد');

        return Response::html($this->themes->renderFile($file, $data), 404);
    }

    // ── Classic fallbacks (no usable theme) ──────────────────────
    private function classicHome(): Response
    {
        return $this->render('front.home', [
            'siteTitle' => $this->options->get('site_title', 'سایت من'),
            'tagline' => $this->options->get('tagline', ''),
            'menu' => $this->primaryMenu(),
            'pages' => $this->posts->published('page', 20),
            'posts' => $this->posts->published('post', 6),
        ]);
    }

    private function classicPage(?Post $post): Response
    {
        if ($post === null) {
            return Response::html($this->view->render('errors.404', ['flash' => $this->pullFlash()]), 404);
        }

        return $this->render('front.page', [
            'siteTitle' => $this->options->get('site_title', 'سایت من'),
            'tagline' => $this->options->get('tagline', ''),
            'menu' => $this->primaryMenu(),
            'post' => $post,
        ]);
    }
}
