<?php

namespace App\Command;

use App\Domain\Equipment\Repository\BatteryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsCommand(
    name: "app:battery:send-reminders",
    description: "Envoie un mail de rappel de recharge aux utilisateurs dont le rappel batterie est dû",
)]
final class SendBatteryRemindersCommand extends Command
{
    public function __construct(
        private readonly BatteryRepository $batteryRepository,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        private readonly TranslatorInterface $translator,
    ) {
        parent::__construct();
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $now = new \DateTimeImmutable();
        $sent = 0;

        foreach ($this->batteryRepository->findActive() as $battery) {
            if (!$battery->isReminderDue($now)) {
                continue;
            }

            $user = $battery->getOwner();
            $locale = $user->getLocale();

            $email = (new TemplatedEmail())
                ->from(new Address("send@campingnard.com", "Campingnard"))
                ->to((string) $user->getEmail())
                ->subject($this->translator->trans("email.battery_reminder.subject", [], null, $locale))
                ->locale($locale)
                ->htmlTemplate("battery/reminder_email.html.twig")
                ->context(["frequency" => $battery->getFrequency()]);

            $this->mailer->send($email);

            $battery->setLastReminderAt($now);
            $battery->setUpdatedAt($now);
            ++$sent;
        }

        $this->em->flush();

        $io->success(sprintf("%d rappel(s) de batterie envoyé(s).", $sent));

        return Command::SUCCESS;
    }
}
