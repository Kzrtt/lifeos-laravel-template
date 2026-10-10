<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Illuminate\Validation\Rule;
use Modules\Agenda\Models\Evento;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;

final class AtualizarEvento extends AbstractEventoAction
{
    public function __construct(ProjetoReadModel $projetos, private readonly VocabularioReadModel $vocabulario)
    {
        parent::__construct($projetos);
    }

    protected function sanitize(array $input): array
    {
        $patch = $input['patch'] ?? null;

        if (is_array($patch)) {
            if (array_key_exists('name', $patch)) {
                $patch['name'] = $this->texto($patch['name']);
            }
            if (array_key_exists('date', $patch)) {
                $patch['date'] = (string) ($patch['date'] ?? '');
            }
            if (array_key_exists('date_fim', $patch)) {
                $v = $patch['date_fim'];
                $patch['date_fim'] = ($v === null || $v === '') ? null : (string) $v;
            }
            if (array_key_exists('tipo', $patch)) {
                $patch['tipo'] = (string) ($patch['tipo'] ?? '');
            }
            if (array_key_exists('projeto_id', $patch)) {
                $patch['projeto_id'] = is_string($patch['projeto_id']) ? $this->textoOuNull($patch['projeto_id']) : null;
            }
        }

        return ['id' => $this->texto($input['id'] ?? null), 'patch' => is_array($patch) ? $patch : null];
    }

    protected function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'patch' => ['required', 'array', 'min:1'],
            'patch.name' => ['sometimes', 'required', 'string'],
            'patch.date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'patch.date_fim' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'patch.tipo' => ['sometimes', 'required', Rule::in($this->vocabulario->valores('evento_tipo'))],
        ];
    }

    protected function codes(): array
    {
        return [
            'id' => 'missing_id', 'patch' => 'empty_patch', 'patch.name' => 'invalid_name',
            'patch.date' => 'invalid_date', 'patch.date_fim' => 'invalid_date_fim', 'patch.tipo' => 'invalid_tipo',
        ];
    }

    protected function handle(array $data): mixed
    {
        $mapa = ['name' => 'evt_name', 'date' => 'evt_date', 'date_fim' => 'evt_date_fim', 'tipo' => 'evt_tipo', 'projeto_id' => 'projeto_prj_id'];
        $update = [];
        foreach ($mapa as $campo => $coluna) {
            if (array_key_exists($campo, $data['patch'])) {
                $update[$coluna] = $data['patch'][$campo];
            }
        }
        if ($update === []) {
            throw new LifeosException('empty_patch');
        }

        $evento = $this->achar(Evento::class, $data['id']);

        $date = $update['evt_date'] ?? $evento->evt_date->format('Y-m-d');
        $fim = array_key_exists('evt_date_fim', $update) ? $update['evt_date_fim'] : $evento->evt_date_fim?->format('Y-m-d');
        if ($fim !== null && $fim < $date) {
            throw new LifeosException('date_fim_antes_de_date');
        }
        $this->checarProjeto($update['projeto_prj_id'] ?? null);

        $evento->update($update);

        return $evento;
    }
}
