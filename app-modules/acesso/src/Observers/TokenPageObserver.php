<?php

declare(strict_types=1);

namespace Modules\Acesso\Observers;

use Modules\Core\Observers\AuditObserver;

/** Auditoria. O que é segredo fica fora do diff ($hidden). */
final class TokenPageObserver extends AuditObserver
{
    protected array $hidden = [];
}
