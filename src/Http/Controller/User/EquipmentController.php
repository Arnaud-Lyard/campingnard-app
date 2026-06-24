<?php

namespace App\Http\Controller\User;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/utilisateur", name: "user_")]
final class EquipmentController extends AbstractController
{
    #[Route("/equipement", name: "equipment_index")]
    public function index(): Response
    {
        return $this->render("user/equipment/index.html.twig", [
            "controller_name" => "PagesController",
        ]);
    }
}
