<?php

// This runner may migrate only the explicitly named disposable database.
if (getenv('APP_ENV') !== 'testing' || getenv('DB_HOST') !== 'pdv-lifecycle-mysql'
    || getenv('DB_DATABASE') !== 'pdv_lifecycle_test' || getenv('DB_CONNECTION') !== 'mysql'
    || getenv('PDV_DISPOSABLE_MYSQL') !== '1') {
    throw new RuntimeException('Refusing MySQL tests outside the disposable lifecycle environment.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
