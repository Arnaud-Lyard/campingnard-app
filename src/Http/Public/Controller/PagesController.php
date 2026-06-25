<?php

namespace App\Http\Public\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PagesController extends AbstractController
{
    #[Route("/test", name: "test")]
    public function index(): Response
    {
        return $this->render("pages/index.html.twig", [
            "controller_name" => "PagesController",
        ]);
    }
}
