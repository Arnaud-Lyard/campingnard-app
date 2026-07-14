<?php

namespace App\Http\Public\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route("/politique-de-confidentialite", name: "privacy_policy")]
    public function privacy(): Response
    {
        return $this->render("pages/privacy.html.twig");
    }

    #[Route("/mentions-legales", name: "legal_mentions")]
    public function legal(): Response
    {
        return $this->render("pages/legal.html.twig");
    }
}
