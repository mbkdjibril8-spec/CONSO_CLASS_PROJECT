<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Models\Subsidiary;
use App\Repositories\CurrencyRepository;
use App\Repositories\SubsidiaryRepository;
use App\Services\SubsidiaryService;

/**
 * Structure de groupe : liste, arbre de hiérarchie et fiche filiale sont
 * accessibles à tous les rôles autorisés par la route (portée filiale
 * appliquée par AuthorizationMiddleware). La création/modification est
 * réservée à l'administrateur groupe (restriction posée au niveau des routes).
 */
class SubsidiaryController extends Controller
{
    private SubsidiaryRepository $subsidiaries;

    public function __construct()
    {
        $this->subsidiaries = new SubsidiaryRepository();
    }

    public function index(Request $request): void
    {
        $this->view('subsidiaries/index', [
            'title' => 'Filiales',
            'subsidiaries' => $this->subsidiaries->all(),
        ]);
    }

    public function tree(Request $request): void
    {
        $this->view('subsidiaries/tree', [
            'title' => 'Hiérarchie de groupe',
            'tree' => (new SubsidiaryService())->tree(),
        ]);
    }

    public function show(Request $request, string $id): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $id);

        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        $this->view('subsidiaries/show', [
            'title' => $subsidiary->name,
            'subsidiary' => $subsidiary,
            'parent' => $subsidiary->parentId ? $this->subsidiaries->findById($subsidiary->parentId) : null,
        ]);
    }

    public function createForm(Request $request): void
    {
        $this->renderForm(null, $this->defaultFormValues(), []);
    }

    public function store(Request $request): void
    {
        $input = $request->all();
        $service = new SubsidiaryService();
        [$valid, $errors] = $service->validate($input);

        if (!$valid) {
            $this->renderForm(null, $input, $errors);
            return;
        }

        $id = $service->create($input, $this->currentUser(), $request);
        Session::flash('success', 'Filiale créée avec succès.');
        $this->redirect('/subsidiaries/' . $id);
    }

    public function editForm(Request $request, string $id): void
    {
        $subsidiary = $this->subsidiaries->findById((int) $id);
        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        $this->renderForm($subsidiary, $this->formValuesFromModel($subsidiary), []);
    }

    public function update(Request $request, string $id): void
    {
        $subsidiaryId = (int) $id;
        $subsidiary = $this->subsidiaries->findById($subsidiaryId);
        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        $input = $request->all();
        $service = new SubsidiaryService();
        [$valid, $errors] = $service->validate($input, $subsidiaryId);

        if (!$valid) {
            $this->renderForm($subsidiary, $input, $errors);
            return;
        }

        $service->update($subsidiaryId, $input, $this->currentUser(), $request);
        Session::flash('success', 'Filiale modifiée avec succès.');
        $this->redirect('/subsidiaries/' . $subsidiaryId);
    }

    public function toggleActive(Request $request, string $id): void
    {
        $subsidiaryId = (int) $id;
        $subsidiary = $this->subsidiaries->findById($subsidiaryId);
        if (!$subsidiary) {
            http_response_code(404);
            $this->view('errors/404', ['title' => 'Filiale introuvable']);
            return;
        }

        (new SubsidiaryService())->setActive($subsidiaryId, !$subsidiary->isActive, $this->currentUser(), $request);
        Session::flash('success', $subsidiary->isActive ? 'Filiale désactivée.' : 'Filiale réactivée.');
        $this->redirect('/subsidiaries/' . $subsidiaryId);
    }

    private function renderForm(?Subsidiary $editing, array $values, array $errors): void
    {
        $this->view('subsidiaries/form', [
            'title' => $editing ? 'Modifier ' . $editing->name : 'Nouvelle filiale',
            'editingId' => $editing?->id,
            'values' => $values,
            'errors' => $errors,
            'currencies' => (new CurrencyRepository())->all(),
            'parents' => array_filter($this->subsidiaries->all(), fn ($s) => $s->id !== $editing?->id),
        ]);
    }

    private function defaultFormValues(): array
    {
        return [
            'code' => '', 'name' => '', 'country' => '', 'zone' => '', 'activity' => '',
            'currency_code' => '', 'parent_id' => '', 'ownership_pct' => '100', 'control_pct' => '100',
            'consolidation_method' => 'full',
        ];
    }

    private function formValuesFromModel(Subsidiary $s): array
    {
        return [
            'code' => $s->code, 'name' => $s->name, 'country' => $s->country,
            'zone' => $s->zone ?? '', 'activity' => $s->activity ?? '',
            'currency_code' => $s->currencyCode, 'parent_id' => $s->parentId ?? '',
            'ownership_pct' => $s->ownershipPct, 'control_pct' => $s->controlPct,
            'consolidation_method' => $s->consolidationMethod,
        ];
    }
}
