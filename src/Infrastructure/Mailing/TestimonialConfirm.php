<?php

namespace App\Infrastructure\Mailing;

use App\Domain\Testimonial\Event\TestimonialCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class TestimonialConfirm implements EventSubscriberInterface
{
    public function __construct(
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            TestimonialCreatedEvent::class => "onCreated",
        ];
    }

    public function onCreated(TestimonialCreatedEvent $event): void
    {

        $confirmUrl = $this->urlGenerator->generate(
            "testimonial_confirm",
            ["token" => $event->getToken(), "_locale" => $event->getUser()->getLocale()],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $message = (new TemplatedEmail())
            ->from(new Address("mailer@camping.fr", "Campingnard"))
            ->to($event->getUser()->getEmail())
            ->subject("Confirmez votre avis")
            ->htmlTemplate("testimonial/confirmation_email.html.twig")
            ->context([
                "name" => $event->getName(),
                "confirmUrl" => $confirmUrl,
            ]);

        $this->mailer->send($message);

    }
}
