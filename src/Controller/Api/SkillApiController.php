<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SkillManifestRepository;
use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SkillApiController extends AbstractController
{
    #[Route('/api/v1/index.json', name: 'api_skill_index')]
    public function index(SkillManifestRepository $manifestRepository): Response
    {
        $manifest = $manifestRepository->findLatest();
        if (!$manifest) {
            throw $this->createNotFoundException();
        }

        return new Response($manifest->getContent(), 200, [
            'Content-Type' => 'application/json',
            'ETag' => '"' . $manifest->getCreatedAt()->getTimestamp() . '"',
            'Cache-Control' => 'public, s-maxage=3600',
        ]);
    }

    #[Route('/api/v1/skills/{skillId}/info.json', name: 'api_skill_info')]
    public function info(string $skillId, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getCompiledInfo()) {
            throw $this->createNotFoundException();
        }

        return new Response($skill->getCompiledInfo(), 200, [
            'Content-Type' => 'application/json',
            'ETag' => '"' . $skill->getUpdatedAt()->getTimestamp() . '"',
            'Cache-Control' => 'public, s-maxage=3600',
        ]);
    }

    #[Route('/api/v1/skills/{skillId}/skill.yaml', name: 'api_skill_yaml')]
    public function yaml(string $skillId, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        return new Response($skill->getYamlContent(), 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'inline',
            'ETag' => '"' . $skill->getUpdatedAt()->getTimestamp() . '"',
            'Cache-Control' => 'public, s-maxage=3600',
        ]);
    }

    #[Route('/api/v1/skills/{skillId}/icon.png', name: 'api_skill_icon')]
    public function icon(string $skillId, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getIconData()) {
            throw $this->createNotFoundException();
        }

        return new Response(base64_decode($skill->getIconData()), 200, [
            'Content-Type' => 'image/png',
            'ETag' => '"' . $skill->getUpdatedAt()->getTimestamp() . '"',
            'Cache-Control' => 'public, s-maxage=86400',
        ]);
    }

    #[Route('/api/v1/skills/{skillId}/shortcut', name: 'api_skill_shortcut')]
    public function shortcut(string $skillId, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getShortcutData()) {
            throw $this->createNotFoundException();
        }

        return new Response(base64_decode($skill->getShortcutData()), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $skill->getBridgeShortcutName() . '.shortcut"',
            'ETag' => '"' . $skill->getUpdatedAt()->getTimestamp() . '"',
            'Cache-Control' => 'public, s-maxage=86400',
        ]);
    }
}
