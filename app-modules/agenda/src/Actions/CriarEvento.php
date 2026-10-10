<?php

declare(strict_types=1);

namespace Modules\Agenda\Actions;

use Illuminate\Validation\Rule;
use Modules\Agenda\Models\Evento;
use Modules\Core\Contracts\ProjetoReadModel;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Core\Exceptions\LifeosException;

final class CriarEvento extends AbstractEventoAction
{
    public function __construct(ProjetoReadModel $projetos, private readonly VocabularioReadModel $vocabulario)
    {
        parent::__construct($projetos);
    }

    protected function sanitize(array $input): array
    {
        $e = $input['evento'] ?? null;

        if (is_array($e)) {
            $fim = $e['date_fim'] ?? null;
            $e = [
                'name' => $this->texto($e['name'] ?? null),
                'date' => (string) ($e['date'] ?? ''),
                'date_fim' => ($fim === null || $fim === '') ? null : (string) $fim,
                'tipo' => (string) ($e['tipo'] ?? ''),
                'projeto_id' => is_string($e['projeto_id'] ?? null) ? $this->textoOuNull($e['projeto_id']) : null,
            ];
        }

        return ['evento' => is_array($e) ? $e : null];
    }

    protected function rules(): array
    {
        return [
            'evento' => ['required', 'array'],
            'evento.name' => ['required', 'string'],
            'evento.date' => ['required', 'date_format:Y-m-d'],
            'evento.date_fim' => ['nullable', 'date_format:Y-m-d'],
            'evento.tipo' => ['required', Rule::in($this->vocabulario->valores('evento_tipo'))],
        ];
    }

    protected function codes(): array
    {
        return [
            'evento' => 'missing_evento', 'evento.name' => 'invalid_name', 'evento.date' => 'invalid_date',
            'evento.date_fim' => 'invalid_date_fim', 'evento.tipo' => 'invalid_tipo',
        ];
    }

    protected function handle(array $data): mixed
    {
        $e = $data['evento'];

        if ($e['date_fim'] !== null && $e['date_fim'] < $e['date']) {
            throw new LifeosException('date_fim_antes_de_date');
        }
        $this->checarProjeto($e['projeto_id']);

        return Evento::query()->create([
            'evt_name' => $e['name'],
            'evt_date' => $e['date'],
            'evt_date_fim' => $e['date_fim'],
            'evt_tipo' => $e['tipo'],
            'projeto_prj_id' => $e['projeto_id'],
        ]);
    }
}
