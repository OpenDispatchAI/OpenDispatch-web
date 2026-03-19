<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SyncLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SyncLogRepository::class)]
class SyncLog
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column]
    private int $skillCount = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(length: 40)]
    private string $commitSha;

    #[ORM\Column(length: 255)]
    private string $commitUrl;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $actionRunUrl = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSkillCount(): int
    {
        return $this->skillCount;
    }

    public function setSkillCount(int $skillCount): static
    {
        $this->skillCount = $skillCount;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getCommitSha(): string
    {
        return $this->commitSha;
    }

    public function setCommitSha(string $commitSha): static
    {
        $this->commitSha = $commitSha;

        return $this;
    }

    public function getCommitUrl(): string
    {
        return $this->commitUrl;
    }

    public function setCommitUrl(string $commitUrl): static
    {
        $this->commitUrl = $commitUrl;

        return $this;
    }

    public function getActionRunUrl(): ?string
    {
        return $this->actionRunUrl;
    }

    public function setActionRunUrl(?string $actionRunUrl): static
    {
        $this->actionRunUrl = $actionRunUrl;

        return $this;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function setSyncedAt(\DateTimeImmutable $syncedAt): static
    {
        $this->syncedAt = $syncedAt;

        return $this;
    }
}
