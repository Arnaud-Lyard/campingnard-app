<?php

namespace App\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects any /api/ request that does not carry the correct X-Api-Key header.
 * Runs before the JWT firewall (priority 10 > firewall priority 8) so unknown
 * clients never reach authentication logic.
 */
final class MobileApiKeySubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly string $mobileApiKey) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onKernelRequest', 10]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $key = $request->headers->get('X-Api-Key', '');

        if (!hash_equals($this->mobileApiKey, $key)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'forbidden'],
                Response::HTTP_FORBIDDEN,
            ));
        }
    }
}
