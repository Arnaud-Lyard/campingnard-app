<?php

namespace App\Http\Public\Controller;

use App\Domain\Testimonial\Entity\Testimonial;
use App\Domain\Testimonial\Repository\TestimonialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Exception\UniqueConstraintViolationException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TestimonialController extends AbstractController
{
    #[Route("/testimonial/submit", name: "testimonial_submit", methods: ["POST"])]
    public function submit(
        Request $request,
        TestimonialRepository $repository,
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

        if ($repository->findOneBy(["email" => $recipientEmail])) {
            return new JsonResponse(["success" => false, "error" => "Un avis a déjà été soumis avec cette adresse e-mail."], 409);
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

        $confirmUrl = $this->generateUrl(
            "testimonial_confirm",
            ["token" => $token, "_locale" => "fr"],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $message = (new TemplatedEmail())
            ->from(new Address("mailer@camping.fr", "Campingnard"))
            ->to($recipientEmail)
            ->subject("Confirmez votre avis")
            ->htmlTemplate("testimonial/confirmation_email.html.twig")
            ->context([
                "name" => $name,
                "confirmUrl" => $confirmUrl,
            ]);

        $mailer->send($message);

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
