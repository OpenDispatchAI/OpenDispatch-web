<?php

declare(strict_types=1);

namespace App\Controller\Public;

use League\CommonMark\CommonMarkConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DocsController extends AbstractController
{
    private string $docsDir;

    public function __construct(string $docsDir)
    {
        $this->docsDir = $docsDir;
    }

    #[Route('/docs', name: 'app_docs_index')]
    public function index(): Response
    {
        $navigation = $this->buildNavigation();
        if (empty($navigation)) {
            throw $this->createNotFoundException('No docs found');
        }

        // Redirect to first doc
        return $this->redirectToRoute('app_docs', ['slug' => $navigation[0]['slug']]);
    }

    #[Route('/docs/{slug}', name: 'app_docs', requirements: ['slug' => '[a-z0-9\-]+'])]
    public function show(string $slug): Response
    {
        $path = $this->docsDir . '/' . $slug . '.md';
        if (!file_exists($path)) {
            throw $this->createNotFoundException();
        }

        $converter = new CommonMarkConverter();
        $markdown = file_get_contents($path);
        $html = $converter->convert($markdown);

        return $this->render('public/docs/show.html.twig', [
            'content' => $html,
            'slug' => $slug,
            'navigation' => $this->buildNavigation(),
        ]);
    }

    private function buildNavigation(): array
    {
        $files = glob($this->docsDir . '/*.md');
        $nav = [];
        foreach ($files ?: [] as $file) {
            $slug = basename($file, '.md');
            $firstLine = strtok(file_get_contents($file), "\n");
            $title = ltrim($firstLine, '# ');
            $nav[] = ['slug' => $slug, 'title' => $title];
        }
        return $nav;
    }
}
