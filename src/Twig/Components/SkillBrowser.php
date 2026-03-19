<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Repository\SkillRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
/**
 * @see templates/components/SkillBrowser.html.twig
 */
class SkillBrowser
{
    use DefaultActionTrait;

    #[LiveProp(writable: true, url: true)]
    public string $query = '';

    #[LiveProp(writable: true, url: true)]
    public string $tag = '';

    #[LiveProp(writable: true, url: true)]
    public string $sort = 'name';

    public function __construct(private readonly SkillRepository $skillRepository) {}

    public function getSkills(): array
    {
        return $this->skillRepository->search($this->query, $this->tag, $this->sort);
    }

    public function getTags(): array
    {
        return $this->skillRepository->findAllTags();
    }

    #[LiveAction]
    public function filterByTag(#[LiveArg] string $tag): void
    {
        $this->tag = $tag;
    }
}
