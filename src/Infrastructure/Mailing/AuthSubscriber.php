<?php

namespace App\Infrastructure\Mailing;

use App\Domain\Auth\Event\ResetPasswordEvent;
use App\Domain\Auth\Event\UserCreatedEvent;
use Doctrine\ORM\EntityManagerInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

class AuthSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private ResetPasswordHelperInterface $resetHelper,
    ) {}

    /**
     * @return array<string,string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            UserCreatedEvent::class => "onRegister",
            ResetPasswordEvent::class => "onResetPassword",
        ];
    }

    public function onRegister(UserCreatedEvent $event): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            "app_verify_email",
            (string) $event->getUser()->getId(),
            (string) $event->getUser()->getEmail(),
            ["id" => $event->getUser()->getId()],
        );

        $email = new TemplatedEmail()
            ->from(new Address("send@campingnard.com", "Campingnard"))
            ->to((string) $event->getUser()->getEmail())
            ->subject($this->translator->trans("email.confirm.subject"))
            ->locale($event->getLocale())
            ->htmlTemplate("registration/confirmation_email.html.twig");

        $context = $email->getContext();
        $context["signedUrl"] = $signatureComponents->getSignedUrl();
        $context[
            "expiresAtMessageKey"
        ] = $signatureComponents->getExpirationMessageKey();
        $context[
            "expiresAtMessageData"
        ] = $signatureComponents->getExpirationMessageData();

        $email->context($context);
        $this->mailer->send($email);
    }

    public function onResetPassword(ResetPasswordEvent $event): void
    {
        $resetToken = $this->resetHelper->generateResetToken($event->getUser());

        $email = new TemplatedEmail()
            ->from(new Address("send@campingnard.com", "Campingnard"))
            ->to($event->getUser()->getEmail())
            ->subject(
                $this->translator->trans("email.reset.subject"),
            )
            ->locale($event->getUser()->getLocale()->value)
            ->htmlTemplate("reset_password/email.html.twig")
            ->context(["resetToken" => $resetToken]);

        $this->mailer->send($email);
    }
}
