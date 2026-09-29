<?php

declare(strict_types=1);

/*
 * Старые имена классов редиректа до переноса из phpsoftbox/http-message в Application.
 * Оставлены, пока проекты не заменят импорты; будут удалены до публикации версии.
 */

use PhpSoftBox\Application\Response\Redirector;
use PhpSoftBox\Application\Response\RedirectResponse;

if (!class_exists('PhpSoftBox\Http\Message\Redirector', false)) {
    class_alias(Redirector::class, 'PhpSoftBox\Http\Message\Redirector');
}

if (!class_exists('PhpSoftBox\Http\Message\RedirectResponse', false)) {
    class_alias(RedirectResponse::class, 'PhpSoftBox\Http\Message\RedirectResponse');
}
