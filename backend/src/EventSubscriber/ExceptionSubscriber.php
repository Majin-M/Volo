<?php

namespace App\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', 0],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api')) {
            return;
        }

        $exception = $event->getThrowable();
        $statusCode = $exception instanceof HttpExceptionInterface
            ? $exception->getStatusCode()
            : 500;

        if ($statusCode >= 500) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
                'url' => $request->getUri(),
            ]);
        }

        $message = match (true) {
            $statusCode === 404 => 'Ressource introuvable.',
            $statusCode === 403 => 'Acces refuse.',
            $statusCode === 401 => 'Authentification requise.',
            $statusCode === 405 => 'Methode non autorisee.',
            $statusCode >= 500 && $this->environment !== 'dev' => 'Erreur interne du serveur.',
            default => $exception->getMessage(),
        };

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $statusCode,
                'message' => $message,
            ],
        ], $statusCode));
    }
}
