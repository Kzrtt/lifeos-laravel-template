<?php

declare(strict_types=1);

namespace Modules\Core\DTOs;

final readonly class ProjetoResumoDTO
{
    /** @param list<string> $tags */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $emoji,
        public string $status,
        public array $tags,
    ) {}

    /** "🚀 Nome" — o rótulo que o MCP devolve. */
    public function rotulo(): string
    {
        return ($this->emoji ? $this->emoji.' ' : '').$this->name;
    }
}
