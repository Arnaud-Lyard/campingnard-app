<?php

namespace App\Http\User\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route("/user", name: "user_")]
final class EquipmentController extends AbstractController
{
    #[Route("/equipment", name: "equipment_index")]
    public function index(): Response
    {
        return $this->render("user/equipment/index.html.twig", [
            "controller_name" => "PagesController",
        ]);
    }
}
