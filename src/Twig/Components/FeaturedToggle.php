<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\Skill;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
class FeaturedToggle
{
    use DefaultActionTrait;

    #[LiveProp]
    public Skill $skill;

    public function __construct(private EntityManagerInterface $em) {}

    #[LiveAction]
    public function toggle(): void
    {
        $this->skill->setIsFeatured(!$this->skill->isFeatured());
        $this->em->flush();
    }
}
