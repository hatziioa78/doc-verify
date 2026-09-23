<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    dispatch(request_method(), request_path());
} catch (Throwable $e) {
    log_exception($e);
    if (!headers_sent()) {
        http_response_code(500);
    }
    $message = public_error_message($e);
    require BASE_PATH . '/views/errors/500.php';
}
