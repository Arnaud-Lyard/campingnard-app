<?php

namespace App\Http\Public\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route("", name: "home")]
    public function index(): Response
    {
        return $this->render("pages/home.html.twig");
    }
}
