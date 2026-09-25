<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Database\Database;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\Logging\Logger;
use IRJalali\Core\View\View;

final class LogController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly Logger $logger,
        private readonly Database $db,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('logs.view')) {
            return $denied;
        }

        $tab = $request->str('tab', 'security');
        if (!in_array($tab, ['security', 'audit', 'files'], true)) {
            $tab = 'security';
        }

        $data = ['tab' => $tab, 'user' => $this->auth->user()];

        if ($tab === 'security') {
            $data['rows'] = $this->db->select('SELECT * FROM security_logs ORDER BY id DESC LIMIT 100');
        } elseif ($tab === 'audit') {
            $data['rows'] = $this->db->select('SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100');
        } else {
            $channels = $this->logger->channels();
            $channel = $request->str('channel', $channels[0] ?? 'app');
            $data['channels'] = $channels;
            $data['channel'] = $channel;
            $data['lines'] = $this->logger->tail($channel, 200);
        }

        return $this->render('admin.logs', $data);
    }
}
