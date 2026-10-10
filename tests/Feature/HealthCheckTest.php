<?php

declare(strict_types=1);

it('health check público responde 200', function () {
    $this->get('/up')->assertOk();
});
