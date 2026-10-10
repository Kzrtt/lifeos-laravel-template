<?php

declare(strict_types=1);

namespace Modules\Memoria\Support;

use Modules\Core\Contracts\InstrucoesDoServidor;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Memoria\Models\Memoria;
use Throwable;

/**
 * `instructions` do handshake MCP: o cliente as coloca no system prompt, então o
 * modelo começa a conversa já sabendo quais memórias existem — sem gastar uma
 * tool call. Falhar aqui nunca derruba o handshake: sem índice, saem só com o
 * texto fixo. Montado a cada `initialize`, por instância.
 */
final class InstrucoesMemoria implements InstrucoesDoServidor
{
    /** Clientes costumam truncar instruções longas: passando disto, o índice é cortado com aviso. */
    private const MAX_INDICE = 6000;

    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    public function texto(): string
    {
        $base = 'LifeOS é o sistema de gestão de vida do usuário (tarefas, projetos, notas, eventos, finanças, '
            .'manifestações, citações). Este servidor também guarda a MEMÓRIA DE LONGO PRAZO sobre o usuário, '
            ."independente de cliente ou modelo.\n\n"
            ."Como usar a memória:\n"
            .'- O índice abaixo lista cada memória (título — descrição). Quando o assunto da conversa tocar uma '
            ."delas, abra com get_memoria antes de responder.\n"
            .'- Ao aprender algo durável sobre o usuário (quem é, preferências, correções que ele fez, contexto '
            .'de projetos, referências), registre: add_registro na memória certa, ou create_memoria se nenhuma '
            ."cobre o tema. Informe o parâmetro origem com o nome do seu cliente.\n"
            .'- Não registre o que é efêmero ou só vale para a conversa atual. Não duplique: se um fato mudou, '
            ."corrija com update_registro.\n"
            ."- Cada registro reflete o que era verdade quando foi escrito (veja a data).\n"
            .'- Este índice foi montado na conexão; use list_memorias para a versão atual.';

        try {
            $memorias = Memoria::query()->withCount('registros')->orderBy('mem_titulo')->orderBy('mem_id')->get();
        } catch (Throwable) {
            return $base."\n\n(Índice de memória indisponível agora -- use list_memorias.)";
        }
        if ($memorias->isEmpty()) {
            return $base."\n\nÍndice de memória: vazio -- nenhuma memória registrada ainda.";
        }

        $ordem = $this->vocabulario->valores('memoria_categoria');
        foreach ($memorias as $m) {
            if (! in_array($m->mem_categoria, $ordem, true)) {
                $ordem[] = $m->mem_categoria;
            }
        }

        $indice = '';
        $cortado = false;
        foreach ($ordem as $categoria) {
            $doGrupo = $memorias->filter(fn (Memoria $m): bool => $m->mem_categoria === $categoria);
            if ($doGrupo->isEmpty()) {
                continue;
            }
            $bloco = "\n[{$categoria}]\n".$doGrupo->map(fn (Memoria $m): string => '- '.$m->mem_titulo
                .($m->mem_descricao !== '' ? ' — '.$m->mem_descricao : '')
                ." ({$m->registros_count} ".($m->registros_count === 1 ? 'registro' : 'registros').')')->implode("\n");
            if (mb_strlen($indice) + mb_strlen($bloco) > self::MAX_INDICE) {
                $cortado = true;
                break;
            }
            $indice .= $bloco;
        }
        if ($cortado) {
            $indice .= "\n(… índice cortado por tamanho -- use list_memorias para ver todas.)";
        }

        return $base."\n\nÍndice de memória:".$indice;
    }
}
