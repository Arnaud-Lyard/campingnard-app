<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Equipment\Entity\Equipment;
use App\Domain\Equipment\Enum\EquipmentStatus;
use App\Domain\Equipment\EquipmentPresets;
use App\Domain\Equipment\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Mobile JSON API mirroring the web equipment management (status, bulk actions,
 * reordering, presets). Stateless JWT firewall — no CSRF, JSON request bodies.
 */
#[Route("/equipment")]
final class EquipmentApiController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route("", name: "api_equipment_list", methods: ["GET"])]
    public function list(EquipmentRepository $repository): JsonResponse
    {
        $items = $repository->findOrdered($this->currentUser());

        return $this->json(array_map($this->serialize(...), $items));
    }

    #[Route("", name: "api_equipment_create", methods: ["POST"])]
    public function create(Request $request, EquipmentRepository $repository): JsonResponse
    {
        $name = trim((string) $request->getPayload()->get("name", ""));
        if ("" === $name) {
            return $this->json(["error" => "name_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        // Push existing items down so the new one takes the top slot (ordre 0).
        $repository->shiftDown($user);

        $now = new \DateTimeImmutable();
        $equipment = (new Equipment())
            ->setOwner($user)
            ->setName(mb_substr($name, 0, 510))
            ->setStatus(EquipmentStatus::InProgress)
            ->setOrdre(0)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $this->em->persist($equipment);
        $this->em->flush();

        return $this->json($this->serialize($equipment), Response::HTTP_CREATED);
    }

    #[Route("/generate", name: "api_equipment_generate", methods: ["POST"])]
    public function generate(Request $request, EquipmentRepository $repository): JsonResponse
    {
        $user = $this->currentUser();
        $locale = (string) $request->getPayload()->get("locale", "fr");
        $names = EquipmentPresets::forLocale($locale);

        $repository->shiftDown($user, \count($names));

        $now = new \DateTimeImmutable();
        $rows = [];
        $ordre = 0;
        foreach ($names as $name) {
            $equipment = (new Equipment())
                ->setOwner($user)
                ->setName($name)
                ->setStatus(EquipmentStatus::InProgress)
                ->setOrdre($ordre++)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $this->em->persist($equipment);
            $rows[] = $equipment;
        }
        $this->em->flush();

        return $this->json(array_map($this->serialize(...), $rows), Response::HTTP_CREATED);
    }

    #[Route("/reorder", name: "api_equipment_reorder", methods: ["POST"])]
    public function reorder(Request $request, EquipmentRepository $repository): JsonResponse
    {
        $ids = $this->ids($request);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        $index = 0;
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $equipment->setOrdre($index++)->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true]);
    }

    #[Route("/status", name: "api_equipment_status", methods: ["POST"])]
    public function status(Request $request, EquipmentRepository $repository): JsonResponse
    {
        try {
            $status = EquipmentStatus::fromKey((string) $request->getPayload()->get("status", ""));
        } catch (\ValueError) {
            return $this->json(["error" => "invalid_status"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $ids = $this->ids($request);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $equipment->setStatus($status)->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true, "status" => $status->key()]);
    }

    #[Route("/bulk-delete", name: "api_equipment_bulk_delete", methods: ["POST"])]
    public function bulkDelete(Request $request, EquipmentRepository $repository): JsonResponse
    {
        $ids = $this->ids($request);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $this->em->remove($equipment);
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true]);
    }

    #[Route("/{id}", name: "api_equipment_update", methods: ["PATCH", "PUT"], requirements: ["id" => "\\d+"])]
    public function update(int $id, Request $request, EquipmentRepository $repository): JsonResponse
    {
        $equipment = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if (!$equipment) {
            return $this->json(["error" => "not_found"], Response::HTTP_NOT_FOUND);
        }

        $name = trim((string) $request->getPayload()->get("name", ""));
        if ("" === $name) {
            return $this->json(["error" => "name_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $equipment->setName(mb_substr($name, 0, 510))->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json($this->serialize($equipment));
    }

    #[Route("/{id}", name: "api_equipment_delete", methods: ["DELETE"], requirements: ["id" => "\\d+"])]
    public function delete(int $id, EquipmentRepository $repository): JsonResponse
    {
        $equipment = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if ($equipment) {
            $this->em->remove($equipment);
            $this->em->flush();
        }

        return $this->json(["ok" => true]);
    }

    /**
     * @return array{id: int|null, name: string|null, status: string|null, ordre: int|null, createdAt: string}
     */
    private function serialize(Equipment $e): array
    {
        return [
            "id" => $e->getId(),
            "name" => $e->getName(),
            "status" => $e->getStatus()?->key(),
            "ordre" => $e->getOrdre(),
            "createdAt" => $e->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @return int[]
     */
    private function ids(Request $request): array
    {
        return array_values(array_filter(array_map(
            "intval",
            (array) $request->getPayload()->all("ids"),
        )));
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
