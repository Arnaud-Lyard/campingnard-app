<?php

namespace App\Http\Public\Controller;

use App\Domain\Auth\Repository\UserRepository;
use App\Domain\Testimonial\Entity\Testimonial;
use App\Domain\Testimonial\Repository\TestimonialRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Domain\Testimonial\Event\TestimonialCreatedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Attribute\Route;

final class TestimonialController extends AbstractController
{
    public function __construct(
        private readonly EventDispatcherInterface $dispatcher,
        private readonly TestimonialRepository $testimonialRepository,
        private readonly UserRepository $userRepository,
    ) {}

    #[Route("/testimonial/submit", name: "testimonial_submit", methods: ["POST"])]
    public function submit(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid("testimonial_submit", (string) $request->request->get("_csrf_token"))) {
            return new JsonResponse(["success" => false, "error" => "Token de sécurité invalide."], 403);
        }

        $name = trim((string) $request->request->get("name", ""));
        $recipientEmail = trim((string) $request->request->get("email", ""));
        $comment = trim((string) $request->request->get("comment", ""));
        $rating = (int) $request->request->get("rating", 0);

        if (
            !$name
            || !$recipientEmail
            || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)
            || !$comment
            || $rating < 1
            || $rating > 5
        ) {
            return new JsonResponse(["success" => false, "error" => "Données invalides."], 400);
        }

        if ($this->testimonialRepository->findOneBy(["email" => $recipientEmail])) {
            return new JsonResponse(["success" => false, "error" => "Un avis a déjà été soumis avec cette adresse e-mail."], 409);
        }

        $user = $this->userRepository->findOneBy(["email" => $recipientEmail]);
        if ($user === null) {
            return new JsonResponse(["success" => false, "error" => "Aucun utilisateur trouvé avec cette adresse e-mail."], 404);
        }
        if ($user->isVerified() === false) {
            return new JsonResponse(["success" => false, "error" => "Votre compte n'est pas vérifié."], 403);
        }

        $token = bin2hex(random_bytes(32));

        $testimonial = new Testimonial();
        $testimonial->setName($name);
        $testimonial->setEmail($recipientEmail);
        $testimonial->setComment($comment);
        $testimonial->setRating($rating);
        $testimonial->setCreatedAt(new \DateTimeImmutable());
        $testimonial->setConfirmationToken($token);
        $testimonial->setIsActive(false);

        $em->persist($testimonial);
        $em->flush();

        $this->dispatcher->dispatch(
            new TestimonialCreatedEvent($testimonial, $token, $user, $name)
        );

        return new JsonResponse(["success" => true]);
    }

    #[Route("/testimonial/confirm/{token}", name: "testimonial_confirm", methods: ["GET"])]
    public function confirm(
        string $token,
        TestimonialRepository $repository,
        EntityManagerInterface $em,
    ): Response {
        $testimonial = $repository->findOneBy(["confirmationToken" => $token]);

        if (!$testimonial) {
            $this->addFlash("error", "Lien de confirmation invalide ou expiré.");

            return $this->redirectToRoute("home", ["_locale" => "fr"]);
        }

        if ($testimonial->isActive()) {
            $this->addFlash("info", "Votre avis a déjà été confirmé.");

            return $this->redirectToRoute("home", ["_locale" => "fr"]);
        }

        $testimonial->setIsActive(true);
        $em->flush();

        $this->addFlash("success", "Votre avis a été publié. Merci !");

        return $this->redirectToRoute("home", ["_locale" => "fr"]);
    }
}
