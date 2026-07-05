<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Equipment\Entity\Battery;
use App\Domain\Equipment\Repository\BatteryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mobile JSON API for the per-user battery-recharge reminder. Mirrors
 * BatteryController (web) over the stateless JWT firewall.
 */
#[Route("/battery")]
final class BatteryApiController extends AbstractController
{
    private const DEFAULT_FREQUENCY = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BatteryRepository $repository,
    ) {
    }

    #[Route("", name: "api_battery_get", methods: ["GET"])]
    public function get(): JsonResponse
    {
        $battery = $this->repository->findOneBy(["owner" => $this->currentUser()]);

        return $this->json([
            "isActive" => $battery?->isActive() ?? false,
            "frequency" => $battery?->getFrequency() ?? self::DEFAULT_FREQUENCY,
            "lastReminderAt" => $battery
                ?->getLastReminderAt()
                ?->format(\DateTimeInterface::ATOM),
        ]);
    }

    #[Route("", name: "api_battery_save", methods: ["PUT", "POST"])]
    public function save(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $payload = $request->getPayload();
        $isActive = filter_var($payload->get("isActive"), FILTER_VALIDATE_BOOL);
        $frequency = $payload->getInt("frequency", self::DEFAULT_FREQUENCY);

        if ($frequency < 1 || $frequency > 365) {
            return $this->json(["error" => "frequency_range"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $battery = $this->repository->findOneBy(["owner" => $user])
            ?? (new Battery())->setOwner($user);

        $now = new \DateTimeImmutable();
        if (null === $battery->getId()) {
            $battery->setCreatedAt($now);
        }
        $battery->setIsActive($isActive)->setFrequency($frequency)->setUpdatedAt($now);

        // Start the countdown when the reminder is first enabled.
        if ($battery->isActive() && null === $battery->getLastReminderAt()) {
            $battery->setLastReminderAt($now);
        }

        $this->em->persist($battery);
        $this->em->flush();

        return $this->json([
            "isActive" => $battery->isActive(),
            "frequency" => $battery->getFrequency(),
            "lastReminderAt" => $battery
                ->getLastReminderAt()
                ?->format(\DateTimeInterface::ATOM),
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
