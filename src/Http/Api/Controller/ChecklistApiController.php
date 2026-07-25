<?php

namespace App\Http\Api\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Checklist\DTO\BulkDeleteChecklistDTO;
use App\Domain\Checklist\DTO\ChecklistNameDTO;
use App\Domain\Checklist\DTO\GenerateChecklistDTO;
use App\Domain\Checklist\DTO\ReorderChecklistDTO;
use App\Domain\Checklist\DTO\UpdateChecklistStatusDTO;
use App\Domain\Checklist\Entity\Checklist;
use App\Domain\Checklist\Enum\ChecklistStatus;
use App\Domain\Checklist\ChecklistPresets;
use App\Domain\Checklist\Repository\ChecklistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/checklist")]
final class ChecklistApiController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    #[Route("", name: "api_checklist_list", methods: ["GET"])]
    public function list(ChecklistRepository $repository): JsonResponse
    {
        $items = $repository->findOrdered($this->currentUser());

        return $this->json(array_map($this->serialize(...), $items));
    }

    #[Route("", name: "api_checklist_create", methods: ["POST"])]
    public function create(#[MapRequestPayload] ChecklistNameDTO $createDatas, ChecklistRepository $repository): JsonResponse
    {
        $name = trim($createDatas->name);
        if ("" === $name) {
            return $this->json(["error" => "name_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        $repository->shiftDown($user);

        $now = new \DateTimeImmutable();
        $checklist = (new Checklist())
            ->setOwner($user)
            ->setName(mb_substr($name, 0, 510))
            ->setStatus(ChecklistStatus::InProgress)
            ->setOrdre(0)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $this->em->persist($checklist);
        $this->em->flush();

        return $this->json($this->serialize($checklist), Response::HTTP_CREATED);
    }

    #[Route("/generate", name: "api_checklist_generate", methods: ["POST"])]
    public function generate(#[MapRequestPayload] GenerateChecklistDTO $generateDatas, ChecklistRepository $repository): JsonResponse
    {
        $user = $this->currentUser();
        $names = ChecklistPresets::forLocale($generateDatas->locale);

        $repository->shiftDown($user, \count($names));

        $now = new \DateTimeImmutable();
        $rows = [];
        $ordre = 0;
        foreach ($names as $name) {
            $checklist = (new Checklist())
                ->setOwner($user)
                ->setName($name)
                ->setStatus(ChecklistStatus::InProgress)
                ->setOrdre($ordre++)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);
            $this->em->persist($checklist);
            $rows[] = $checklist;
        }
        $this->em->flush();

        return $this->json(array_map($this->serialize(...), $rows), Response::HTTP_CREATED);
    }

    #[Route("/reorder", name: "api_checklist_reorder", methods: ["POST"])]
    public function reorder(#[MapRequestPayload] ReorderChecklistDTO $reorderDatas, ChecklistRepository $repository): JsonResponse
    {
        $ids = $this->ids($reorderDatas->ids);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        $index = 0;
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $checklist->setOrdre($index++)->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true]);
    }

    #[Route("/status", name: "api_checklist_status", methods: ["POST"])]
    public function status(#[MapRequestPayload] UpdateChecklistStatusDTO $statusDatas, ChecklistRepository $repository): JsonResponse
    {
        try {
            $status = ChecklistStatus::fromKey($statusDatas->status);
        } catch (\ValueError) {
            return $this->json(["error" => "invalid_status"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $ids = $this->ids($statusDatas->ids);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $checklist->setStatus($status)->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true, "status" => $status->key()]);
    }

    #[Route("/bulk-delete", name: "api_checklist_bulk_delete", methods: ["POST"])]
    public function bulkDelete(#[MapRequestPayload] BulkDeleteChecklistDTO $bulkDeleteDatas, ChecklistRepository $repository): JsonResponse
    {
        $ids = $this->ids($bulkDeleteDatas->ids);
        if (!$ids) {
            return $this->json(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $this->em->remove($checklist);
            }
        }
        $this->em->flush();

        return $this->json(["ok" => true]);
    }

    #[Route("/{id}", name: "api_checklist_update", methods: ["PATCH", "PUT"], requirements: ["id" => "\\d+"])]
    public function update(int $id, #[MapRequestPayload] ChecklistNameDTO $updateDatas, ChecklistRepository $repository): JsonResponse
    {
        $checklist = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if (!$checklist) {
            return $this->json(["error" => "not_found"], Response::HTTP_NOT_FOUND);
        }

        $name = trim($updateDatas->name);
        if ("" === $name) {
            return $this->json(["error" => "name_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $checklist->setName(mb_substr($name, 0, 510))->setUpdatedAt(new \DateTimeImmutable());
        $this->em->flush();

        return $this->json($this->serialize($checklist));
    }

    #[Route("/{id}", name: "api_checklist_delete", methods: ["DELETE"], requirements: ["id" => "\\d+"])]
    public function delete(int $id, ChecklistRepository $repository): JsonResponse
    {
        $checklist = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if ($checklist) {
            $this->em->remove($checklist);
            $this->em->flush();
        }

        return $this->json(["ok" => true]);
    }

    /**
     * @return array{id: int|null, name: string|null, status: string|null, ordre: int|null, createdAt: string}
     */
    private function serialize(Checklist $c): array
    {
        return [
            "id" => $c->getId(),
            "name" => $c->getName(),
            "status" => $c->getStatus()?->key(),
            "ordre" => $c->getOrdre(),
            "createdAt" => $c->getCreatedAt()?->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param mixed[] $rawIds
     * @return int[]
     */
    private function ids(array $rawIds): array
    {
        return array_values(array_filter(array_map("intval", $rawIds)));
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
