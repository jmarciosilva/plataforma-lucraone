<?php

return [
    // A interface pode ser aberta em modo de leitura antes da distribuição.
    'ui_preview_enabled' => (bool) env('PDV_CATALOG_UI_PREVIEW_ENABLED', false),

    // A distribuição operacional só é habilitada após revisão e autorização.
    'enabled' => (bool) env('PDV_CATALOG_DISTRIBUTION_ENABLED', false),
];
