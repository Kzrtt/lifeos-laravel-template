<?php

declare(strict_types=1);

namespace Modules\Vocabularios\Support;

/**
 * Os domínios de vocabulário e os valores que cada instância nasce tendo.
 * Os valores são EXATAMENTE os que já estavam em uso; `protegido` marca os que o
 * código lê por nome (renomear migra os dados, apagar quebraria a lógica).
 */
final class Dominios
{
    /**
     * `tabela`/`coluna` são só metadados de exibição da tela (como hoje): dizem
     * onde o valor é gravado, para explicar o efeito de renomear.
     *
     * @var array<string, array{rotulo: string, tabela: string, coluna: string, array: bool, cor: bool}>
     */
    public const LISTA = [
        'nota_tipo' => ['rotulo' => 'Tipos de nota', 'tabela' => 'lifeos_notas', 'coluna' => 'tipo', 'array' => true, 'cor' => false],
        'tarefa_status' => ['rotulo' => 'Status de tarefa', 'tabela' => 'lifeos_tarefas', 'coluna' => 'status', 'array' => false, 'cor' => false],
        'tarefa_tipo' => ['rotulo' => 'Tipos de tarefa', 'tabela' => 'lifeos_tarefas', 'coluna' => 'tipo', 'array' => true, 'cor' => false],
        'projeto_status' => ['rotulo' => 'Status de projeto', 'tabela' => 'lifeos_projetos', 'coluna' => 'status', 'array' => false, 'cor' => false],
        'projeto_tag' => ['rotulo' => 'Tags de projeto', 'tabela' => 'lifeos_projetos', 'coluna' => 'tags', 'array' => true, 'cor' => false],
        'evento_tipo' => ['rotulo' => 'Tipos de evento', 'tabela' => 'lifeos_eventos', 'coluna' => 'tipo', 'array' => false, 'cor' => true],
        'manifestacao_status' => ['rotulo' => 'Status de manifestação', 'tabela' => 'lifeos_manifestacoes', 'coluna' => 'status', 'array' => false, 'cor' => false],
        'manifestacao_tag' => ['rotulo' => 'Tags de manifestação', 'tabela' => 'lifeos_manifestacoes', 'coluna' => 'tags', 'array' => true, 'cor' => false],
        'mov_direcao' => ['rotulo' => 'Direção de movimentação', 'tabela' => 'lifeos_movimentacoes', 'coluna' => 'tipo', 'array' => true, 'cor' => false],
        'mov_meio' => ['rotulo' => 'Meios de pagamento', 'tabela' => 'lifeos_movimentacoes', 'coluna' => 'tipo', 'array' => true, 'cor' => false],
        'memoria_categoria' => ['rotulo' => 'Categorias de memória', 'tabela' => 'lifeos_memorias', 'coluna' => 'categoria', 'array' => false, 'cor' => false],
    ];

    /**
     * Seed: domínio => lista de [valor, cor, protegido], na ordem (10, 20, ...).
     *
     * @var array<string, list<array{0: string, 1: ?string, 2: bool}>>
     */
    public const PADRAO = [
        'nota_tipo' => [['Lembranças', null, false], ['Análise de Leitura', null, false], ['Pensamentos', null, false], ['Conclusões', null, false], ['Úteis', null, false], ['Faculdade', null, false], ['Vida', null, false], ['Pesquisa', null, false], ['Programação', null, false], ['Pessoal', null, false], ['Relato', null, false], ['Documentação', null, false]],
        'tarefa_status' => [['Não Iniciado', null, true], ['Em Andamento', null, true], ['Feito', null, true]],
        'tarefa_tipo' => [['Vida', null, false], ['Organização', null, false], ['Documentação', null, false], ['Estudo', null, false], ['Avaliação', null, false], ['Código', null, false], ['Freelance', null, false], ['Trabalho', null, false], ['Tarefa', null, false]],
        'projeto_status' => [['Não Iniciado', null, true], ['Em Progresso', null, true], ['Feito', null, true], ['Pausado', null, true]],
        'projeto_tag' => [['Pessoal', null, false], ['Profissional', null, false], ['Acadêmico', null, false], ['Configuração', null, false]],
        'evento_tipo' => [['faculdade', '#5b8def', false], ['trabalho', '#c4913a', false], ['lazer', '#3fb98c', false], ['vida', '#e58b5b', false], ['psicodelia', '#b06ee0', false]],
        'manifestacao_status' => [['Não Iniciado', null, true], ['Em Progresso', null, true], ['Feito', null, true]],
        'manifestacao_tag' => [['Vida', null, false], ['Financeiro', null, false], ['Carreira', null, false], ['Saúde', null, false], ['Lazer', null, false]],
        'mov_direcao' => [['Entrada', null, true], ['Saida', null, true]],
        'mov_meio' => [['Crédito', null, true], ['Débito', null, false], ['Pix', null, false], ['Vale', null, false], ['Boleto', null, false]],
        'memoria_categoria' => [['Perfil', null, false], ['Preferências', null, false], ['Projetos', null, false], ['Referências', null, false], ['Vida', null, false]],
    ];

    /**
     * Valores embutidos de um domínio (o fallback do read model).
     *
     * @return list<string>
     */
    public static function valoresPadrao(string $dominio): array
    {
        return array_map(fn (array $v): string => $v[0], self::PADRAO[$dominio] ?? []);
    }
}
