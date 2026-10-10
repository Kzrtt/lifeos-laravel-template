<?php

declare(strict_types=1);

namespace Modules\Tenancy\Observers;

use Modules\Core\Observers\AuditObserver;

/**
 * Auditoria do tenant. Sem colunas ocultas por enquanto — quando o Entity ganhar
 * campos sensíveis (documentos, chaves), declare-os em $hidden aqui.
 */
final class EntityObserver extends AuditObserver {}
