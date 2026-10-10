<?php

declare(strict_types=1);

namespace Modules\Backup\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Backup\Actions\GerarBackup;
use Modules\Core\Actions\Input\RpcInputAdapter;
use Modules\Core\Exceptions\LifeosException;

/** POST /functions/v1/lifeos-backup — action "export" (a única). */
final class BackupRpcController
{
    public function __invoke(Request $request, GerarBackup $action): JsonResponse
    {
        if (RpcInputAdapter::action($request, 'export') !== 'export') {
            throw new LifeosException('invalid_action');
        }

        return response()->json(['ok' => true, ...$action->execute(RpcInputAdapter::from($request))]);
    }
}
