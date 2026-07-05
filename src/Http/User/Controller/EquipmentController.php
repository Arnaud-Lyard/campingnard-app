<?php

namespace App\Http\User\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Equipment\Entity\Equipment;
use App\Domain\Equipment\Enum\EquipmentStatus;
use App\Domain\Equipment\EquipmentPresets;
use App\Domain\Equipment\Form\EquipmentType;
use App\Domain\Equipment\Repository\EquipmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/user", name: "user_")]
final class EquipmentController extends AbstractController
{
    private const CSRF_ID = "equipment";

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route("/equipment", name: "equipment_index", methods: ["GET"])]
    public function index(EquipmentRepository $repository): Response
    {
        return $this->render("user/equipment/index.html.twig", [
            "equipments" => $repository->findOrdered($this->currentUser()),
            "form" => $this->createForm(EquipmentType::class, new Equipment()),
        ]);
    }

    #[Route("/equipment", name: "equipment_create", methods: ["POST"])]
    public function create(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $user = $this->currentUser();
        $equipment = new Equipment();
        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        // Push existing items down so the new one takes the top slot (ordre 0).
        $repository->shiftDown($user);

        $now = new \DateTimeImmutable();
        $equipment
            ->setOwner($user)
            ->setStatus(EquipmentStatus::InProgress)
            ->setOrdre(0)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $em->persist($equipment);
        $em->flush();

        return $this->rowResponse($equipment, Response::HTTP_CREATED);
    }

    #[Route("/equipment/generate", name: "equipment_generate", methods: ["POST"])]
    public function generate(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $user = $this->currentUser();
        $names = EquipmentPresets::forLocale($request->getLocale());

        // Free the top slots (0..count-1) for the generated items.
        $repository->shiftDown($user, \count($names));

        $rows = [];
        $ordre = 0;
        foreach ($names as $name) {
            $equipment = $this->newEquipment($name, $ordre++, $user);
            $em->persist($equipment);
            $rows[] = $equipment;
        }
        $em->flush();

        // Rows are already in display order (first preset on top).
        $html = "";
        foreach ($rows as $equipment) {
            $html .= $this->renderView("user/equipment/_row.html.twig", ["equipment" => $equipment]);
        }

        return new JsonResponse(["html" => $html, "count" => \count($rows)], Response::HTTP_CREATED);
    }

    #[Route("/equipment/reorder", name: "equipment_reorder", methods: ["POST"])]
    public function reorder(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        $index = 0;
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $equipment->setOrdre($index++);
                $equipment->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/equipment/status", name: "equipment_status", methods: ["POST"])]
    public function status(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        $statusKey = (string) $request->request->get("status", "");
        try {
            $status = EquipmentStatus::fromKey($statusKey);
        } catch (\ValueError) {
            return new JsonResponse(["error" => "invalid_status"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $equipment->setStatus($status);
                $equipment->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true, "status" => $status->key()]);
    }

    #[Route("/equipment/bulk-delete", name: "equipment_bulk_delete", methods: ["POST"])]
    public function bulkDelete(Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $equipment = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($equipment) {
                $em->remove($equipment);
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/equipment/{id}", name: "equipment_update", methods: ["POST"], requirements: ["id" => "\\d+"])]
    public function update(int $id, Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $equipment = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if (!$equipment) {
            return new JsonResponse(["error" => "not_found"], Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(EquipmentType::class, $equipment);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        $equipment->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->rowResponse($equipment);
    }

    #[Route("/equipment/{id}", name: "equipment_delete", methods: ["DELETE"], requirements: ["id" => "\\d+"])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, EquipmentRepository $repository): Response
    {
        $this->assertCsrf($request);

        $equipment = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if ($equipment) {
            $em->remove($equipment);
            $em->flush();
        }

        return new JsonResponse(["ok" => true]);
    }

    private function newEquipment(string $name, int $ordre, User $owner): Equipment
    {
        $now = new \DateTimeImmutable();

        return (new Equipment())
            ->setName($name)
            ->setOwner($owner)
            ->setStatus(EquipmentStatus::InProgress)
            ->setOrdre($ordre)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function rowResponse(Equipment $equipment, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            "html" => $this->renderView("user/equipment/_row.html.twig", ["equipment" => $equipment]),
        ], $status);
    }

    private function formErrorsResponse(FormInterface $form): JsonResponse
    {
        $messages = [];
        foreach ($form->getErrors(true) as $error) {
            $messages[] = $this->translator->trans(
                $error->getMessage(),
                $error->getMessageParameters(),
                "validators",
            );
        }

        return new JsonResponse(["errors" => $messages], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return int[]
     */
    private function idsFromRequest(Request $request): array
    {
        return array_values(array_filter(array_map(
            "intval",
            (array) $request->request->all("ids"),
        )));
    }

    private function assertCsrf(Request $request): void
    {
        $token = $request->headers->get("X-CSRF-Token", "");
        if (!$this->isCsrfTokenValid(self::CSRF_ID, $token)) {
            throw $this->createAccessDeniedException("Invalid CSRF token.");
        }
    }
}
