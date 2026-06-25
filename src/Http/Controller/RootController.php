<?php

namespace App\Http\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RootController extends AbstractController
{
    #[Route("/", name: "root")]
    public function index(Request $request): Response
    {
        $locale = $request->getPreferredLanguage(["fr", "en"]) ?? "fr";

        return $this->redirectToRoute("home", ["_locale" => $locale]);
    }
}
