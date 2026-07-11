<?php

namespace App\Http\User\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/user', name: 'user_')]
final class WeatherController extends AbstractController
{
    #[Route('/weather', name: 'weather_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('user/weather/index.html.twig');
    }
}
