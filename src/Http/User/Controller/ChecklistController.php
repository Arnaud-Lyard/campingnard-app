<?php

namespace App\Http\User\Controller;

use App\Domain\Auth\Entity\User;
use App\Domain\Checklist\Entity\Checklist;
use App\Domain\Checklist\Enum\ChecklistStatus;
use App\Domain\Checklist\ChecklistPresets;
use App\Domain\Checklist\Form\ChecklistType;
use App\Domain\Checklist\Repository\ChecklistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route("/user", name: "user_")]
final class ChecklistController extends AbstractController
{
    private const CSRF_ID = "checklist";

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route("/checklist", name: "checklist_index", methods: ["GET"])]
    public function index(ChecklistRepository $repository): Response
    {
        return $this->render("user/checklist/index.html.twig", [
            "checklists" => $repository->findOrdered($this->currentUser()),
            "form" => $this->createForm(ChecklistType::class, new Checklist()),
        ]);
    }

    #[Route("/checklist", name: "checklist_create", methods: ["POST"])]
    public function create(Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $user = $this->currentUser();
        $checklist = new Checklist();
        $form = $this->createForm(ChecklistType::class, $checklist);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        $repository->shiftDown($user);

        $now = new \DateTimeImmutable();
        $checklist
            ->setOwner($user)
            ->setStatus(ChecklistStatus::InProgress)
            ->setOrdre(0)
            ->setCreatedAt($now)
            ->setUpdatedAt($now);
        $em->persist($checklist);
        $em->flush();

        return $this->rowResponse($checklist, Response::HTTP_CREATED);
    }

    #[Route("/checklist/generate", name: "checklist_generate", methods: ["POST"])]
    public function generate(Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $this->assertCsrf($request);

        $user = $this->currentUser();
        $names = ChecklistPresets::forLocale($request->getLocale());

        $repository->shiftDown($user, \count($names));

        $rows = [];
        $ordre = 0;
        foreach ($names as $name) {
            $checklist = $this->newChecklist($name, $ordre++, $user);
            $em->persist($checklist);
            $rows[] = $checklist;
        }
        $em->flush();

        $html = "";
        foreach ($rows as $checklist) {
            $html .= $this->renderView("user/checklist/_row.html.twig", ["checklist" => $checklist]);
        }

        return new JsonResponse(["html" => $html, "count" => \count($rows)], Response::HTTP_CREATED);
    }

    #[Route("/checklist/reorder", name: "checklist_reorder", methods: ["POST"])]
    public function reorder(Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        $index = 0;
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $checklist->setOrdre($index++);
                $checklist->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/checklist/status", name: "checklist_status", methods: ["POST"])]
    public function status(Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        $statusKey = (string) $request->request->get("status", "");
        try {
            $status = ChecklistStatus::fromKey($statusKey);
        } catch (\ValueError) {
            return new JsonResponse(["error" => "invalid_status"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $checklist->setStatus($status);
                $checklist->setUpdatedAt(new \DateTimeImmutable());
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true, "status" => $status->key()]);
    }

    #[Route("/checklist/bulk-delete", name: "checklist_bulk_delete", methods: ["POST"])]
    public function bulkDelete(Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $this->assertCsrf($request);

        $ids = $this->idsFromRequest($request);
        if (!$ids) {
            return new JsonResponse(["error" => "ids_required"], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->currentUser();
        foreach ($ids as $id) {
            $checklist = $repository->findOneBy(["id" => $id, "owner" => $user]);
            if ($checklist) {
                $em->remove($checklist);
            }
        }
        $em->flush();

        return new JsonResponse(["ok" => true]);
    }

    #[Route("/checklist/{id}", name: "checklist_update", methods: ["POST"], requirements: ["id" => "\\d+"])]
    public function update(int $id, Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $checklist = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if (!$checklist) {
            return new JsonResponse(["error" => "not_found"], Response::HTTP_NOT_FOUND);
        }

        $form = $this->createForm(ChecklistType::class, $checklist);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            return $this->formErrorsResponse($form);
        }

        $checklist->setUpdatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->rowResponse($checklist);
    }

    #[Route("/checklist/{id}", name: "checklist_delete", methods: ["DELETE"], requirements: ["id" => "\\d+"])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, ChecklistRepository $repository): Response
    {
        $this->assertCsrf($request);

        $checklist = $repository->findOneBy(["id" => $id, "owner" => $this->currentUser()]);
        if ($checklist) {
            $em->remove($checklist);
            $em->flush();
        }

        return new JsonResponse(["ok" => true]);
    }

    private function newChecklist(string $name, int $ordre, User $owner): Checklist
    {
        $now = new \DateTimeImmutable();

        return (new Checklist())
            ->setName($name)
            ->setOwner($owner)
            ->setStatus(ChecklistStatus::InProgress)
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

    private function rowResponse(Checklist $checklist, int $status = Response::HTTP_OK): JsonResponse
    {
        return new JsonResponse([
            "html" => $this->renderView("user/checklist/_row.html.twig", ["checklist" => $checklist]),
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
