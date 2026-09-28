<?php

declare(strict_types=1);

namespace IRJalali\App\Controllers\Admin;

use IRJalali\App\Controllers\Controller;
use IRJalali\App\Repositories\FormRepository;
use IRJalali\Core\Auth\Auth;
use IRJalali\Core\Http\Request;
use IRJalali\Core\Http\Response;
use IRJalali\Core\View\View;

final class FormController extends Controller
{
    public const FIELD_TYPES = ['text', 'email', 'number', 'textarea', 'select', 'checkbox'];

    public function __construct(
        View $view,
        Auth $auth,
        private readonly FormRepository $forms,
    ) {
        parent::__construct($view, $auth);
    }

    public function index(): Response
    {
        if ($denied = $this->denyUnlessCan('forms.view')) {
            return $denied;
        }
        $forms = $this->forms->listForms();
        foreach ($forms as &$form) {
            $form['unread'] = $this->forms->countUnread((int) $form['id']);
        }

        return $this->render('admin.forms.index', [
            'forms' => $forms,
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function create(): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }

        return $this->render('admin.forms.form', [
            'form' => null,
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function store(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $data = $this->validated($request);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect('/admin/forms/create');
        }
        $id = $this->forms->createForm($data);
        $this->withFlash('success', 'فرم ساخته شد؛ حالا فیلدها را اضافه کنید.');

        return $this->redirect('/admin/forms/' . $id . '/fields');
    }

    public function edit(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $form = $this->findOr404((int) $request->route('id'));
        if ($form instanceof Response) {
            return $form;
        }

        return $this->render('admin.forms.form', [
            'form' => $form,
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function update(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        if ($this->forms->findForm($id) === null) {
            return $this->render('errors.404', [], 404);
        }
        $data = $this->validated($request, $id);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect('/admin/forms/' . $id . '/edit');
        }
        $this->forms->updateForm($id, $data);
        $this->withFlash('success', 'ذخیره شد.');

        return $this->redirect('/admin/forms/' . $id . '/edit');
    }

    public function destroy(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.delete')) {
            return $denied;
        }
        $id = (int) $request->route('id');
        if ($this->forms->findForm($id) === null) {
            return $this->render('errors.404', [], 404);
        }
        $this->forms->deleteForm($id);
        $this->withFlash('success', 'فرم و پاسخ‌های آن حذف شد.');

        return $this->redirect('/admin/forms');
    }

    public function fields(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $form = $this->findOr404((int) $request->route('id'));
        if ($form instanceof Response) {
            return $form;
        }

        return $this->render('admin.forms.fields', [
            'form' => $form,
            'fields' => $this->forms->fields((int) $form['id']),
            'fieldTypes' => self::FIELD_TYPES,
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function storeField(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $formId = (int) $request->route('id');
        if ($this->forms->findForm($formId) === null) {
            return $this->render('errors.404', [], 404);
        }
        $data = $this->validatedField($request, $formId);
        if (!is_array($data)) {
            $this->withFlash('error', $data);

            return $this->redirect('/admin/forms/' . $formId . '/fields');
        }
        $this->forms->createField($formId, $data);
        $this->withFlash('success', 'فیلد افزوده شد.');

        return $this->redirect('/admin/forms/' . $formId . '/fields');
    }

    public function destroyField(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $formId = (int) $request->route('id');
        $fieldId = (int) $request->route('field');
        $field = $this->forms->findField($fieldId);
        if ($field === null || (int) $field['form_id'] !== $formId) {
            return $this->render('errors.404', [], 404);
        }
        $this->forms->deleteField($fieldId);
        $this->withFlash('success', 'فیلد حذف شد.');

        return $this->redirect('/admin/forms/' . $formId . '/fields');
    }

    public function moveField(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.manage')) {
            return $denied;
        }
        $formId = (int) $request->route('id');
        $fieldId = (int) $request->route('field');
        $field = $this->forms->findField($fieldId);
        if ($field === null || (int) $field['form_id'] !== $formId) {
            return $this->render('errors.404', [], 404);
        }
        $direction = $request->str('move') === 'down' ? 1 : -1;
        $fields = $this->forms->fields($formId);
        $index = null;
        foreach ($fields as $i => $row) {
            if ((int) $row['id'] === $fieldId) {
                $index = $i;
                break;
            }
        }
        if ($index !== null && isset($fields[$index + $direction])) {
            $other = $fields[$index + $direction];
            $this->forms->updateField($fieldId, ['ordering' => (int) $other['ordering']]);
            $this->forms->updateField((int) $other['id'], ['ordering' => (int) $field['ordering']]);
        }

        return $this->redirect('/admin/forms/' . $formId . '/fields');
    }

    public function submissions(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.view')) {
            return $denied;
        }
        $form = $this->findOr404((int) $request->route('id'));
        if ($form instanceof Response) {
            return $form;
        }
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 20;

        return $this->render('admin.forms.submissions', [
            'form' => $form,
            'submissions' => $this->forms->submissions((int) $form['id'], $perPage, ($page - 1) * $perPage),
            'total' => $this->forms->countSubmissions((int) $form['id']),
            'page' => $page,
            'perPage' => $perPage,
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function showSubmission(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.view')) {
            return $denied;
        }
        $formId = (int) $request->route('id');
        $submission = $this->forms->findSubmission((int) $request->route('submission'));
        if ($submission === null || (int) $submission['form_id'] !== $formId) {
            return $this->render('errors.404', [], 404);
        }
        $this->forms->markSubmissionRead((int) $submission['id']);
        $form = $this->forms->findForm($formId);

        return $this->render('admin.forms.submission', [
            'form' => $form,
            'submission' => $submission,
            'answers' => json_decode((string) $submission['data_json'], true) ?: [],
            'active' => 'forms',
            'user' => $this->auth->user(),
        ]);
    }

    public function destroySubmission(Request $request): Response
    {
        if ($denied = $this->denyUnlessCan('forms.delete')) {
            return $denied;
        }
        $formId = (int) $request->route('id');
        $submission = $this->forms->findSubmission((int) $request->route('submission'));
        if ($submission === null || (int) $submission['form_id'] !== $formId) {
            return $this->render('errors.404', [], 404);
        }
        $this->forms->deleteSubmission((int) $submission['id']);
        $this->withFlash('success', 'پاسخ حذف شد.');

        return $this->redirect('/admin/forms/' . $formId . '/submissions');
    }

    /** @return array<string, mixed>|Response */
    private function findOr404(int $id): array|Response
    {
        $form = $this->forms->findForm($id);
        if ($form === null) {
            return $this->render('errors.404', [], 404);
        }

        return $form;
    }

    /** @return array<string, mixed>|string */
    private function validated(Request $request, ?int $ignoreId = null): array|string
    {
        $title = mb_substr(trim($request->str('title')), 0, 150);
        if ($title === '') {
            return 'عنوان فرم الزامی است.';
        }
        $slug = mb_strtolower(trim($request->str('slug') !== '' ? $request->str('slug') : $title));
        $slug = (string) preg_replace('/[\s_]+/u', '-', $slug);
        $slug = (string) preg_replace('/[^\p{L}\p{N}\-]+/u', '', $slug);
        $slug = mb_substr(trim($slug, '-'), 0, 120);
        if ($slug === '') {
            return 'نامک معتبر نیست.';
        }
        if ($this->forms->slugTaken($slug, $ignoreId)) {
            return 'این نامک قبلاً استفاده شده است.';
        }

        return [
            'title' => $title,
            'slug' => $slug,
            'description' => mb_substr(trim($request->str('description')), 0, 2000) ?: null,
            'is_active' => $request->str('is_active') === '1',
        ];
    }

    /** @return array<string, mixed>|string */
    private function validatedField(Request $request, int $formId): array|string
    {
        $label = mb_substr(trim($request->str('label')), 0, 150);
        if ($label === '') {
            return 'برچسب فیلد الزامی است.';
        }
        $key = mb_strtolower((string) preg_replace('/[^a-z0-9_\-]+/i', '', str_replace(' ', '_', $request->str('key'))));
        $key = mb_substr(trim($key, '_-'), 0, 100);
        if ($key === '') {
            return 'کلید فیلد باید لاتین باشد (حروف، عدد، _ و -).';
        }
        foreach ($this->forms->fields($formId) as $existing) {
            if ($existing['key'] === $key) {
                return 'این کلید در این فرم تکراری است.';
            }
        }
        $type = $request->str('type');
        if (!in_array($type, self::FIELD_TYPES, true)) {
            $type = 'text';
        }
        $settings = ['required' => $request->str('required') === '1'];
        if ($type === 'select') {
            $options = array_values(array_filter(array_map(
                fn (string $line): string => mb_substr(trim($line), 0, 150),
                preg_split('/\r?\n/', $request->str('options')) ?: []
            )));
            $options = array_slice($options, 0, 50);
            if ($options === []) {
                return 'برای فیلد کشویی حداقل یک گزینه بنویسید.';
            }
            $settings['options'] = $options;
        }

        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
        ];
    }
}
