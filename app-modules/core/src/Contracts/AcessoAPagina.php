<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Porta do gate por página: dada uma senha e o slug de uma página, a instância
 * dona da senha SE ela abre aquela página (mestre abre qualquer uma; as demais,
 * só as concedidas). Quem precisa (upload da galeria) pergunta aqui em vez de
 * importar o módulo `acesso`. Implementada por `acesso`.
 */
interface AcessoAPagina
{
    /** Id da instância dona da senha, ou null se a senha não abre a página. */
    public function entidadeSeAutorizado(string $senha, string $pagina): ?string;
}
