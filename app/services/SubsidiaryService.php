<?php

namespace App\Services;

use App\Core\Request;
use App\Models\Subsidiary;
use App\Models\User;
use App\Repositories\CurrencyRepository;
use App\Repositories\SubsidiaryRepository;

/**
 * Règles de gestion de la structure de groupe : validation des filiales
 * (unicité du code, devise existante, absence de cycle dans la hiérarchie)
 * et traçabilité de toute création/modification.
 */
class SubsidiaryService
{
    private SubsidiaryRepository $subsidiaries;
    private CurrencyRepository $currencies;
    private AuditService $audit;

    public function __construct()
    {
        $this->subsidiaries = new SubsidiaryRepository();
        $this->currencies = new CurrencyRepository();
        $this->audit = new AuditService();
    }

    /**
     * @return array{0: bool, 1: array<string,string>} [succès, erreurs par champ]
     */
    public function validate(array $input, ?int $editingId = null): array
    {
        $errors = [];

        $code = trim((string) ($input['code'] ?? ''));
        if ($code === '') {
            $errors['code'] = 'Le code est obligatoire.';
        } elseif (!preg_match('/^[A-Z0-9\-]{2,20}$/', $code)) {
            $errors['code'] = 'Le code doit être en majuscules, chiffres et tirets (2 à 20 caractères).';
        } else {
            $existing = $this->subsidiaries->findByCode($code);
            if ($existing && $existing->id !== $editingId) {
                $errors['code'] = 'Ce code est déjà utilisé par une autre filiale.';
            }
        }

        if (trim((string) ($input['name'] ?? '')) === '') {
            $errors['name'] = 'Le nom est obligatoire.';
        }
        if (trim((string) ($input['country'] ?? '')) === '') {
            $errors['country'] = 'Le pays est obligatoire.';
        }

        $currencyCode = trim((string) ($input['currency_code'] ?? ''));
        if ($currencyCode === '' || !$this->currencies->exists($currencyCode)) {
            $errors['currency_code'] = 'La devise sélectionnée est invalide.';
        }

        $parentId = $input['parent_id'] !== '' ? (int) $input['parent_id'] : null;
        if ($parentId !== null) {
            if ($editingId !== null && $parentId === $editingId) {
                $errors['parent_id'] = 'Une filiale ne peut pas être sa propre société mère.';
            } elseif (!$this->subsidiaries->findById($parentId)) {
                $errors['parent_id'] = 'La société mère sélectionnée est introuvable.';
            } elseif ($editingId !== null && in_array($editingId, $this->subsidiaries->ancestorIds($parentId), true)) {
                // Le parent choisi a editingId parmi ses propres ancêtres : rattacher créerait un cycle.
                $errors['parent_id'] = 'Ce rattachement créerait une boucle dans la hiérarchie.';
            }
        }

        $ownership = $input['ownership_pct'] ?? '';
        if (!is_numeric($ownership) || $ownership < 0 || $ownership > 100) {
            $errors['ownership_pct'] = 'Le pourcentage de détention doit être compris entre 0 et 100.';
        }

        $control = $input['control_pct'] ?? '';
        if (!is_numeric($control) || $control < 0 || $control > 100) {
            $errors['control_pct'] = 'Le pourcentage de contrôle doit être compris entre 0 et 100.';
        }

        $method = $input['consolidation_method'] ?? '';
        if (!in_array($method, ['full', 'equity', 'excluded'], true)) {
            $errors['consolidation_method'] = 'Méthode de consolidation invalide.';
        }

        return [empty($errors), $errors];
    }

    private function normalize(array $input): array
    {
        return [
            'code'                 => trim((string) $input['code']),
            'name'                 => trim((string) $input['name']),
            'country'              => trim((string) $input['country']),
            'zone'                 => trim((string) ($input['zone'] ?? '')) ?: null,
            'activity'             => trim((string) ($input['activity'] ?? '')) ?: null,
            'currency_code'        => trim((string) $input['currency_code']),
            'parent_id'            => $input['parent_id'] !== '' ? (int) $input['parent_id'] : null,
            'ownership_pct'        => (float) $input['ownership_pct'],
            'control_pct'          => (float) $input['control_pct'],
            'consolidation_method' => $input['consolidation_method'],
        ];
    }

    public function create(array $input, User $actor, Request $request): int
    {
        $data = $this->normalize($input);
        $id = $this->subsidiaries->create($data);
        $this->audit->logChange($actor, 'create', 'subsidiary', $id, null, $data, $request);
        return $id;
    }

    public function update(int $id, array $input, User $actor, Request $request): void
    {
        $before = $this->subsidiaries->findById($id);
        $data = $this->normalize($input);
        $this->subsidiaries->update($id, $data);
        $this->audit->logChange($actor, 'update', 'subsidiary', $id, (array) $before, $data, $request);
    }

    public function setActive(int $id, bool $active, User $actor, Request $request): void
    {
        $this->subsidiaries->setActive($id, $active);
        $this->audit->logChange(
            $actor,
            $active ? 'activate' : 'deactivate',
            'subsidiary',
            $id,
            ['is_active' => !$active],
            ['is_active' => $active],
            $request
        );
    }

    /**
     * Construit l'arbre de hiérarchie (racine = filiales sans parent).
     * @return array<int, array{subsidiary: Subsidiary, children: array}>
     */
    public function tree(): array
    {
        $all = $this->subsidiaries->all();
        $byParent = [];
        foreach ($all as $s) {
            $byParent[$s->parentId ?? 0][] = $s;
        }

        $build = function (int $parentId) use (&$build, $byParent): array {
            $nodes = [];
            foreach ($byParent[$parentId] ?? [] as $s) {
                $nodes[] = ['subsidiary' => $s, 'children' => $build($s->id)];
            }
            return $nodes;
        };

        return $build(0);
    }
}
