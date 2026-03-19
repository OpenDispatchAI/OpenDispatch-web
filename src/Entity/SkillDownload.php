<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SkillDownloadRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SkillDownloadRepository::class)]
#[ORM\Index(columns: ['downloaded_at'], name: 'idx_download_date')]
class SkillDownload
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Skill::class, inversedBy: 'downloads')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Skill $skill;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $downloadedAt;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $appVersion = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->downloadedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSkill(): Skill
    {
        return $this->skill;
    }

    public function setSkill(Skill $skill): static
    {
        $this->skill = $skill;

        return $this;
    }

    public function getDownloadedAt(): \DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function setDownloadedAt(\DateTimeImmutable $downloadedAt): static
    {
        $this->downloadedAt = $downloadedAt;

        return $this;
    }

    public function getAppVersion(): ?string
    {
        return $this->appVersion;
    }

    public function setAppVersion(?string $appVersion): static
    {
        $this->appVersion = $appVersion;

        return $this;
    }
}
