<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Repository\SkillDownloadRepository;
use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Yaml\Yaml;

class SkillDetailController extends AbstractController
{
    #[Route('/skills/{skillId}', name: 'app_skill_detail')]
    public function show(
        string $skillId,
        SkillRepository $skillRepository,
        SkillDownloadRepository $downloadRepository,
    ): Response {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        $data = Yaml::parse($skill->getYamlContent());
        $downloadCount = $downloadRepository->count(['skill' => $skill]);

        return $this->render('public/skills/show.html.twig', [
            'skill' => $skill,
            'actions' => $data['actions'] ?? [],
            'download_count' => $downloadCount,
        ]);
    }
}
