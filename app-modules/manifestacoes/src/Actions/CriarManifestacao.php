<?php

declare(strict_types=1);

namespace Modules\Manifestacoes\Actions;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Modules\Core\Actions\RpcAction;
use Modules\Core\Contracts\VocabularioReadModel;
use Modules\Manifestacoes\Models\Manifestacao;
use Throwable;

/**
 * Cria a manifestação e, se veio banner (base64), grava no disco `public`. Falha
 * na imagem não derruba a criação: devolve `banner_error` e a manifestação segue.
 *
 * @phpstan-type Result array{manifestacao: Manifestacao, banner_error: bool}
 */
final class CriarManifestacao extends RpcAction
{
    private const MAX_BASE64_LEN = 6 * 1024 * 1024;

    public function __construct(private readonly VocabularioReadModel $vocabulario) {}

    protected function sanitize(array $input): array
    {
        $m = $input['manifestacao'] ?? null;

        if (is_array($m)) {
            $m = [
                'name' => $this->texto($m['name'] ?? null),
                'status' => (string) ($m['status'] ?? ''),
                'tags' => $this->lista($m['tags'] ?? null),
            ];
        }

        return [
            'manifestacao' => is_array($m) ? $m : null,
            'banner_base64' => (string) ($input['banner_base64'] ?? ''),
            'banner_content_type' => (string) ($input['banner_content_type'] ?? ''),
        ];
    }

    protected function rules(): array
    {
        return [
            'manifestacao' => ['required', 'array'],
            'manifestacao.name' => ['required', 'string'],
            'manifestacao.status' => ['required', Rule::in($this->vocabulario->valores('manifestacao_status'))],
            'manifestacao.tags.*' => [Rule::in($this->vocabulario->valores('manifestacao_tag'))],
            'banner_base64' => ['max:'.self::MAX_BASE64_LEN],
        ];
    }

    protected function codes(): array
    {
        return [
            'manifestacao' => 'missing_manifestacao',
            'manifestacao.name' => 'invalid_name',
            'manifestacao.status' => 'invalid_status',
            'manifestacao.tags.*' => 'invalid_tags',
            'banner_base64' => 'banner_too_large',
        ];
    }

    protected function handle(array $data): mixed
    {
        $m = $data['manifestacao'];

        $manifestacao = Manifestacao::query()->create([
            'man_name' => $m['name'],
            'man_status' => $m['status'],
            'man_tags' => $m['tags'],
        ]);

        if ($data['banner_base64'] === '') {
            return ['manifestacao' => $manifestacao, 'banner_error' => false];
        }

        try {
            $bytes = base64_decode($data['banner_base64'], true);
            if ($bytes === false) {
                throw new \RuntimeException('base64 inválido');
            }
            $tipo = $data['banner_content_type'] ?: 'image/jpeg';
            $caminho = "manifestacoes/{$manifestacao->man_id}.".$this->extensao($tipo);

            Storage::disk('public')->put($caminho, $bytes);
            $manifestacao->update(['man_banner_url' => '/storage/'.$caminho]);

            return ['manifestacao' => $manifestacao, 'banner_error' => false];
        } catch (Throwable) {
            return ['manifestacao' => $manifestacao, 'banner_error' => true];
        }
    }

    private function extensao(string $contentType): string
    {
        return match (true) {
            str_contains($contentType, 'png') => 'png',
            str_contains($contentType, 'webp') => 'webp',
            str_contains($contentType, 'gif') => 'gif',
            default => 'jpg',
        };
    }
}
