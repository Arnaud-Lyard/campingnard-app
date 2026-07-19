<?php

namespace App\Infrastructure\Security;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects any /api/ request whose HMAC-SHA256 signature is missing, invalid,
 * or older than WINDOW_SECONDS (replay protection).
 *
 * The mobile app signs: "{timestamp}\n{METHOD}\n{path}" with the shared secret
 * and sends X-Timestamp + X-Signature headers on every request.
 *
 * Runs before the JWT firewall (priority 10 > firewall priority 8) so unknown
 * clients never reach authentication logic.
 */
final class MobileApiKeySubscriber implements EventSubscriberInterface
{
    private const WINDOW_SECONDS = 300;

    public function __construct(private readonly string $hmacSecret) {}

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

        $timestamp = $request->headers->get('X-Timestamp', '');
        $signature = $request->headers->get('X-Signature', '');

        if (!$timestamp || !$signature) {
            $this->deny($event);
            return;
        }

        if (abs(time() - (int) $timestamp) > self::WINDOW_SECONDS) {
            $this->deny($event);
            return;
        }

        $message = "{$timestamp}\n{$request->getMethod()}\n{$request->getPathInfo()}";
        $expected = hash_hmac('sha256', $message, $this->hmacSecret);

        if (!hash_equals($expected, $signature)) {
            $this->deny($event);
        }
    }

    private function deny(RequestEvent $event): void
    {
        $event->setResponse(new JsonResponse(
            ['error' => 'forbidden'],
            Response::HTTP_FORBIDDEN,
        ));
    }
}
