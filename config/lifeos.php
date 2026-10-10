<?php

declare(strict_types=1);

return [
    /*
    | Instância (Entity) que responde antes do login — lifeos-config.js, capa e
    | identidade. Depois do login a senha resolve a entidade. Enquanto houver
    | uma só instância, basta o slug; a segunda vira um registro DNS (host).
    */
    'entity_slug' => env('LIFEOS_ENTITY_SLUG', 'demo'),

    /* localStorage onde o front guarda a senha ("lembrar neste navegador"). */
    'session_key' => env('LIFEOS_SESSION_KEY', 'financas_master'),
];
