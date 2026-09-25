<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\MediaRepository;
use IRJalali\App\Services\AuditService;
use IRJalali\App\Services\MediaService;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class MediaController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly MediaRepository $media,
        private readonly MediaService $service,
        private readonly AuditService $audit,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('media.view')) {
            return $denied;
        }
        $items = $this->media->latest();
        foreach ($items as &$item) {
            $item['url'] = $this->service->publicUrl($item);
            $item['thumb'] = $this->service->publicUrl($item, 'thumb');
        }

        return $this->render('admin.media', [
            'items' => $items,
            'total' => $this->media->count(),
            'user' => $this->auth->user(),
        ]);
    }

    public function upload(Request $request): Response
    {
        if (!$this->auth->can('media.upload')) {
            return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
        }
        $files = $request->files('files');
        if ($files === []) {
            $single = $request->file('file');
            $files = $single !== null ? [$single] : [];
        }
        if ($files === []) {
            return Response::json(['ok' => false, 'error' => 'no_file'], 422);
        }

        $uploaded = [];
        $errors = [];
        foreach (array_slice($files, 0, 10) as $file) {
            $result = $this->service->upload($file, $this->auth->id());
            if ($result['ok']) {
                $media = $result['media'];
                $media['url'] = $this->service->publicUrl($media);
                $media['thumb'] = $this->service->publicUrl($media, 'thumb');
                $uploaded[] = $media;
            } else {
                $errors[] = ($file['name'] ?? 'file') . ': ' . $result['error'];
            }
        }

        if ($uploaded !== []) {
            $this->audit->audit($this->auth->id(), 'media.upload', 'media', null, [], ['count' => count($uploaded)]);
        }

        return Response::json(['ok' => $errors === [], 'uploaded' => $uploaded, 'errors' => $errors]);
    }

    public function destroy(Request $request): Response
    {
        if (!$this->auth->can('media.delete')) {
            if ($request->wantsJson()) {
                return Response::json(['ok' => false, 'error' => 'forbidden'], 403);
            }

            return Response::text('Forbidden', 403);
        }
        $id = (int) $request->route('id');
        $ok = $this->service->delete($id);
        if ($ok) {
            $this->audit->audit($this->auth->id(), 'media.delete', 'media', $id);
        }

        if ($request->wantsJson()) {
            return Response::json(['ok' => $ok]);
        }
        $this->withFlash($ok ? 'success' : 'error', $ok ? 'فایل حذف شد.' : 'فایل یافت نشد.');

        return $this->redirect('/admin/media');
    }
}
