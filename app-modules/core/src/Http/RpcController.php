<?php

declare(strict_types=1);

namespace Modules\Core\Http;

use Illuminate\Container\Container;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Core\Actions\AbstractAction;
use Modules\Core\Actions\Input\RpcInputAdapter;

/**
 * Controller-adapter do contrato `POST {token, action, ...}` -> `{ok, ...}`.
 *
 * É o "mapa fino action -> Action" do desenho: o módulo declara em `actions()`
 * qual Action atende cada ação e como o resultado vira o corpo da resposta; o
 * resto (token, envelope, ação desconhecida) é daqui. Nenhuma regra de negócio.
 */
abstract class RpcController
{
    /**
     * @return array<string, array{0: class-string<AbstractAction>, 1: callable(mixed): array<string, mixed>}>
     */
    abstract protected function actions(): array;

    /** Ação usada quando `action` falta ou é desconhecida (como nas functions de hoje). */
    protected function defaultAction(): string
    {
        return 'query';
    }

    /**
     * Array canônico entregue à Action. Sobrescreva para acrescentar contexto de
     * borda (ex.: a senha de quem chama), nunca regra.
     *
     * @return array<string, mixed>
     */
    protected function input(Request $request): array
    {
        return RpcInputAdapter::from($request);
    }

    public function __invoke(Request $request): JsonResponse
    {
        $map = $this->actions();
        $name = RpcInputAdapter::action($request, $this->defaultAction());

        [$actionClass, $respond] = $map[$name] ?? $map[$this->defaultAction()];

        /** @var AbstractAction $action */
        $action = Container::getInstance()->make($actionClass);

        $result = $action->execute($this->input($request));

        return response()->json(['ok' => true, ...$respond($result)]);
    }
}
