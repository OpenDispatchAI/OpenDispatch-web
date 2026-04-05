<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\SkillManifestRepository;
use App\Repository\SkillRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SkillApiController extends AbstractController
{
    #[Route('/api/v1/index.json', name: 'api_skill_index')]
    public function index(Request $request, SkillManifestRepository $manifestRepository): Response
    {
        $manifest = $manifestRepository->findLatest();
        if (!$manifest) {
            throw $this->createNotFoundException();
        }

        $response = new Response($manifest->getContent(), 200, [
            'Content-Type' => 'application/json',
        ]);
        $response->setEtag((string) $manifest->getCreatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/info.json', name: 'api_skill_info')]
    public function info(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getCompiledInfo()) {
            throw $this->createNotFoundException();
        }

        $response = new Response($skill->getCompiledInfo(), 200, [
            'Content-Type' => 'application/json',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/skill.yaml', name: 'api_skill_yaml')]
    public function yaml(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill) {
            throw $this->createNotFoundException();
        }

        $response = new Response($skill->getYamlContent(), 200, [
            'Content-Type' => 'text/yaml',
            'Content-Disposition' => 'inline',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/icon.png', name: 'api_skill_icon')]
    public function icon(string $skillId, Request $request, SkillRepository $skillRepository): Response
    {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getIconData()) {
            throw $this->createNotFoundException();
        }

        $response = new Response(base64_decode($skill->getIconData()), 200, [
            'Content-Type' => 'image/png',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    #[Route('/api/v1/skills/{skillId}/{filename}.shortcut', name: 'api_skill_shortcut')]
    public function shortcut(
        string $skillId,
        string $filename,
        Request $request,
        SkillRepository $skillRepository,
    ): Response {
        $skill = $skillRepository->findOneBy(['skillId' => $skillId]);
        if (!$skill || !$skill->getShortcutData()) {
            throw $this->createNotFoundException();
        }

        // Validate filename matches the skill's bridge shortcut name
        if ($filename !== $skill->getBridgeShortcutName()) {
            throw $this->createNotFoundException();
        }

        $response = new Response(base64_decode($skill->getShortcutData()), 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="' . $filename . '.shortcut"',
        ]);
        $response->setEtag((string) $skill->getUpdatedAt()->getTimestamp());

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }
}
