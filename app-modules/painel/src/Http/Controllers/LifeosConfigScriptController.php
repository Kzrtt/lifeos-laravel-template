<?php

declare(strict_types=1);

namespace Modules\Painel\Http\Controllers;

use Illuminate\Http\Response;
use Modules\Core\Contracts\EntityDirectory;

/**
 * GET /assets/js/lifeos-config.js — o único arquivo do front que muda de
 * conteúdo, agora gerado a partir do .env e da entidade. Mesmo formato
 * (`window.LIFEOS_CONFIG = {…}`), mesmas chaves, nenhuma função.
 *
 * `supabaseUrl` recebe a URL desta aplicação: o front monta
 * `CFG.supabaseUrl + '/functions/v1/…'` e o endereço certo sai sem editar tela
 * nenhuma. `anonKey` fica vazia (ninguém mais a usa).
 */
final class LifeosConfigScriptController
{
    public function __invoke(EntityDirectory $entities): Response
    {
        $entity = $entities->bySlug((string) config('lifeos.entity_slug'));

        $config = [
            'supabaseUrl' => rtrim(url('/'), '/'),
            'anonKey' => '',
            'identidade' => [
                'nome' => $entity->nome ?? 'LifeOS',
                'sub' => $entity->sub ?? 'gestão de vida',
                'banner' => $entity->banner ?? '../assets/images/banner_lifeos.png',
                'avatar' => $entity->avatar ?? '../assets/images/profile_lifeos.jpeg',
            ],
            'blog' => ['habilitado' => $entity->blogHabilitado ?? false],
            'tema' => $entity->tema ?? 'abismo',
            'hubCapa' => $entity->hubCapa ?? 'classica',
            'sessionKey' => config('lifeos.session_key'),
        ];

        $js = 'window.LIFEOS_CONFIG = '
            .json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            .";\n";

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
