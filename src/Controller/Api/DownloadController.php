<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\SkillDownload;
use App\Repository\SkillRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DownloadController extends AbstractController
{
    #[Route('/api/v1/skills/{skillId}/download', name: 'api_skill_download')]
    public function download(
        string $skillId,
        Request $request,
        SkillRepository $skillRepository,
        EntityManagerInterface $em,
    ): Response {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        $download = new SkillDownload();
        $download->setSkill($skill);
        $download->setAppVersion($request->headers->get('X-OpenDispatch-Version'));
        $em->persist($download);
        $em->flush();

        return new Response($skill->getYamlContent(), 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'attachment; filename="skill.yaml"',
        ]);
    }
}
