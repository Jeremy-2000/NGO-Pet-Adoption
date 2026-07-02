<?php
declare(strict_types=1);

if (!defined('APP_ROOT')) {
    define('APP_ROOT', dirname(__DIR__));
}

if (!defined('APP_REQUEST_STARTED_AT')) {
    define('APP_REQUEST_STARTED_AT', microtime(true));
}

require_once APP_ROOT . '/app/helpers/functions.php';

app_start();

require_once APP_ROOT . '/app/services/VisibilityService.php';
require_once APP_ROOT . '/app/services/RateLimiter.php';
require_once APP_ROOT . '/app/services/UploadService.php';
require_once APP_ROOT . '/app/repositories/AnimalRepository.php';
require_once APP_ROOT . '/app/repositories/ShelterRepository.php';
