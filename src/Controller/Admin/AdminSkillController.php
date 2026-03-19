<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin')]
class AdminSkillController extends AbstractController
{
    #[Route('/skills', name: 'app_admin_skills')]
    public function list(SkillRepository $skillRepository): Response
    {
        return $this->render('admin/skill/list.html.twig', [
            'skills' => $skillRepository->findBy([], ['name' => 'ASC']),
        ]);
    }
}
