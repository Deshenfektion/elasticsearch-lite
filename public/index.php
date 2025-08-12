<?php

declare(strict_types=1);

use EsLite\Application;
use EsLite\Http\ExceptionMapper;
use EsLite\Http\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

try {
    $application = Application::boot(dirname(__DIR__));
    $request = Request::fromGlobals($application->config()->int('app.api.max_body_bytes', 2097152));
    $application->kernel()->handle($request)->send();
} catch (Throwable $exception) {
    (new ExceptionMapper(getenv('ES_LITE_ENV') !== 'production'))->map($exception)->send();
}
