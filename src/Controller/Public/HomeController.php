<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        SkillRepository $skillRepository,
    ): Response {
        return $this->render('public/home.html.twig', [
            'skill_count' => count($skillRepository->findAll()),
        ]);
    }
}
