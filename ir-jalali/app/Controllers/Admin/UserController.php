<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\UserRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class UserController extends Controller
{
    public function __construct(
        View $view,
        Auth $auth,
        private readonly UserRepository $users,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('users.view')) {
            return $denied;
        }
        $page = max(1, (int) $request->input('page', 1));

        return $this->render('admin.users', [
            'users' => $this->users->paginate($page),
            'total' => $this->users->count(),
            'page' => $page,
            'user' => $this->auth->user(),
        ]);
    }
}
