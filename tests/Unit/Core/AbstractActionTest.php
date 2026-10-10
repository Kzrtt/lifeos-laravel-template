<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use Modules\Core\Actions\AbstractAction;

// Precisa do container (facade Validator), então usa o TestCase do Laravel — mas
// não toca o banco.
uses(Tests\TestCase::class);

/** Action-fixture que registra a ordem dos passos do template method. */
final class FixtureOrderedAction extends AbstractAction
{
    /** @var list<string> */
    public array $steps = [];

    protected function sanitize(array $input): array
    {
        $this->steps[] = 'sanitize';
        $input['name'] = trim((string) ($input['name'] ?? ''));

        return $input;
    }

    protected function rules(): array
    {
        return ['name' => ['required', 'string', 'min:3']];
    }

    protected function authorize(array $data): void
    {
        $this->steps[] = 'authorize';
    }

    protected function handle(array $data): mixed
    {
        $this->steps[] = 'handle';

        return mb_strtoupper($data['name']);
    }
}

it('executa o template method na ordem sanitize→validate→authorize→handle', function () {
    $action = new FixtureOrderedAction;

    $result = $action->execute(['name' => '  joao  ']);

    expect($result)->toBe('JOAO')
        ->and($action->steps)->toBe(['sanitize', 'authorize', 'handle']);
});

it('lança ValidationException quando a entrada é inválida', function () {
    expect(fn () => (new FixtureOrderedAction)->execute(['name' => 'ab']))
        ->toThrow(ValidationException::class);
});

it('não chega ao handle quando a validação falha', function () {
    $action = new FixtureOrderedAction;

    try {
        $action->execute(['name' => '']);
    } catch (ValidationException) {
        // esperado
    }

    expect($action->steps)->not->toContain('handle');
});
