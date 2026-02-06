<?php

declare(strict_types=1);

namespace PhpSoftBox\Application\ErrorHandler;

use PhpSoftBox\ErrorFormatter\ThrowableFormatter;
use PhpSoftBox\Validator\Exception\ValidationException;
use PhpSoftBox\View\ViewRendererInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

use function class_exists;
use function htmlspecialchars;
use function implode;
use function is_array;
use function nl2br;
use function sprintf;

final class HtmlExceptionHandler extends AbstractExceptionHandler
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        bool $includeDetails = false,
        private readonly ?ViewRendererInterface $viewRenderer = null,
        private readonly ?string $viewPath = null,
    ) {
        parent::__construct($includeDetails);
    }

    public function handle(Throwable $exception, ServerRequestInterface $request): ResponseInterface
    {
        ['status' => $status, 'headers' => $headers] = $this->resolveStatusAndHeaders($exception);

        $title        = $this->resolveTitle($exception, $status);
        $message      = $this->resolveMessage($exception, $status);
        $debugMessage = $this->resolveDebugMessage($exception);
        $body         = $this->renderHtml($status, $title, $message, $debugMessage, $exception, $request);

        $stream   = $this->streamFactory->createStream($body);
        $response = $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'text/html; charset=utf-8');

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, is_array($value) ? $value : (string) $value);
        }

        return $response->withBody($stream);
    }

    private function renderHtml(
        int $status,
        string $title,
        string $message,
        ?string $debugMessage,
        Throwable $exception,
        ServerRequestInterface $request,
    ): string {
        $details          = '';
        $validationErrors = [];

        if (class_exists(ValidationException::class) && $exception instanceof ValidationException) {
            $list = [];
            foreach ($exception->errors() as $field => $messages) {
                $validationErrors[$field] = $messages;
                $list[]                   = sprintf('<li><strong>%s</strong>: %s</li>', htmlspecialchars($field), htmlspecialchars(implode(', ', $messages)));
            }

            $details = '<ul>' . implode('', $list) . '</ul>';
        } elseif ($this->includeDetails) {
            $location = htmlspecialchars(ThrowableFormatter::toLocation($exception));
            $trace    = nl2br(htmlspecialchars(ThrowableFormatter::toTrace($exception)));

            $details = sprintf(
                '<div class="psb-trace"><div class="psb-location">%s</div><pre>%s</pre></div>',
                $location,
                $trace,
            );
        }

        if ($this->viewRenderer !== null && $this->viewPath !== null && $this->viewPath !== '') {
            return $this->viewRenderer->render($this->viewPath, [
                'status'           => $status,
                'title'            => $title,
                'message'          => $message,
                'debugMessage'     => $debugMessage,
                'exception'        => $exception,
                'validationErrors' => $validationErrors,
                'includeDetails'   => $this->includeDetails,
                'trace'            => $this->includeDetails ? ThrowableFormatter::toTrace($exception) : null,
                'location'         => $this->includeDetails ? ThrowableFormatter::toLocation($exception) : null,
                'request'          => $request,
            ]);
        }

        $message = htmlspecialchars($message);

        $title = htmlspecialchars($title);

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f7f7f9; color: #1f2328; margin: 0; }
        .psb-wrapper { max-width: 960px; margin: 32px auto; padding: 24px; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 6px 24px rgba(15, 23, 42, 0.08); }
        h1 { margin: 0 0 12px; font-size: 28px; }
        p { margin: 0 0 16px; color: #475569; }
        ul { margin: 0 0 16px 18px; }
        .psb-location { font-weight: 600; margin-bottom: 8px; }
        .psb-trace pre { margin: 0; background: #0f172a; color: #e2e8f0; padding: 16px; border-radius: 8px; overflow: auto; }
    </style>
</head>
<body>
    <div class="psb-wrapper">
        <h1>{$title}</h1>
        <p>{$message}</p>
        {$details}
    </div>
</body>
</html>
HTML;
    }
}
