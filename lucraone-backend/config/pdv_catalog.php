<?php

return [
    // Habilitar somente após aplicar e revisar as migrations de catálogo no ambiente.
    'enabled' => (bool) env('PDV_CATALOG_DISTRIBUTION_ENABLED', false),
];
