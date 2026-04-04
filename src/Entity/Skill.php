<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SkillRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SkillRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Skill
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 255, unique: true)]
    private string $skillId;

    #[ORM\Column(type: Types::TEXT)]
    private string $yamlContent;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $version;

    #[ORM\Column(type: Types::TEXT)]
    private string $description;

    #[ORM\Column(length: 255)]
    private string $author;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $authorUrl = null;

    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(type: Types::JSON)]
    private array $languages = [];

    #[ORM\Column]
    private bool $requiresBridgeShortcut = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bridgeShortcutName = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $bridgeShortcutShareUrl = null;

    #[ORM\Column]
    private int $actionCount = 0;

    #[ORM\Column]
    private int $exampleCount = 0;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iconPath = null;

    /** Not persisted — carries the temp .shortcut file path during a sync cycle */
    private ?string $bridgeShortcutFilePath = null;

    #[ORM\Column]
    private bool $isFeatured = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $syncedAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, SkillDownload> */
    #[ORM\OneToMany(targetEntity: SkillDownload::class, mappedBy: 'skill', cascade: ['remove'])]
    private Collection $downloads;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->downloads = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->syncedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Populates all YAML-derived fields from parsed data.
     * Does NOT touch isFeatured. Sets syncedAt to now.
     */
    public function updateFromYaml(string $yamlContent, array $data): void
    {
        $this->yamlContent = $yamlContent;
        $this->skillId = $data['skill_id'];
        $this->name = $data['name'];
        $this->version = $data['version'];
        $this->description = $data['description'];
        $this->author = $data['author'];
        $this->authorUrl = $data['author_url'] ?? null;
        $this->tags = $data['tags'] ?? [];
        $this->languages = $data['languages'] ?? [];
        $this->requiresBridgeShortcut = $data['requires_bridge_shortcut'] ?? false;
        $this->bridgeShortcutName = $data['bridge_shortcut_name'] ?? null;
        $this->bridgeShortcutShareUrl = $data['bridge_shortcut_share_url'] ?? null;
        $this->actionCount = \count($data['actions'] ?? []);
        $this->exampleCount = \count($data['examples'] ?? []);
        $this->syncedAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getSkillId(): string
    {
        return $this->skillId;
    }

    public function setSkillId(string $skillId): static
    {
        $this->skillId = $skillId;

        return $this;
    }

    public function getYamlContent(): string
    {
        return $this->yamlContent;
    }

    public function setYamlContent(string $yamlContent): static
    {
        $this->yamlContent = $yamlContent;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): static
    {
        $this->author = $author;

        return $this;
    }

    public function getAuthorUrl(): ?string
    {
        return $this->authorUrl;
    }

    public function setAuthorUrl(?string $authorUrl): static
    {
        $this->authorUrl = $authorUrl;

        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function setTags(array $tags): static
    {
        $this->tags = $tags;

        return $this;
    }

    public function getLanguages(): array
    {
        return $this->languages;
    }

    public function setLanguages(array $languages): static
    {
        $this->languages = $languages;

        return $this;
    }

    public function requiresBridgeShortcut(): bool
    {
        return $this->requiresBridgeShortcut;
    }

    public function setRequiresBridgeShortcut(bool $requiresBridgeShortcut): static
    {
        $this->requiresBridgeShortcut = $requiresBridgeShortcut;

        return $this;
    }

    public function getBridgeShortcutName(): ?string
    {
        return $this->bridgeShortcutName;
    }

    public function setBridgeShortcutName(?string $bridgeShortcutName): static
    {
        $this->bridgeShortcutName = $bridgeShortcutName;

        return $this;
    }

    public function getBridgeShortcutShareUrl(): ?string
    {
        return $this->bridgeShortcutShareUrl;
    }

    public function setBridgeShortcutShareUrl(?string $bridgeShortcutShareUrl): static
    {
        $this->bridgeShortcutShareUrl = $bridgeShortcutShareUrl;

        return $this;
    }

    public function getActionCount(): int
    {
        return $this->actionCount;
    }

    public function setActionCount(int $actionCount): static
    {
        $this->actionCount = $actionCount;

        return $this;
    }

    public function getExampleCount(): int
    {
        return $this->exampleCount;
    }

    public function setExampleCount(int $exampleCount): static
    {
        $this->exampleCount = $exampleCount;

        return $this;
    }

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function setIconPath(?string $iconPath): static
    {
        $this->iconPath = $iconPath;

        return $this;
    }

    public function getBridgeShortcutFilePath(): ?string
    {
        return $this->bridgeShortcutFilePath;
    }

    public function setBridgeShortcutFilePath(?string $bridgeShortcutFilePath): static
    {
        $this->bridgeShortcutFilePath = $bridgeShortcutFilePath;

        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): static
    {
        $this->isFeatured = $isFeatured;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /** @return Collection<int, SkillDownload> */
    public function getDownloads(): Collection
    {
        return $this->downloads;
    }

    public function addDownload(SkillDownload $download): static
    {
        if (!$this->downloads->contains($download)) {
            $this->downloads->add($download);
            $download->setSkill($this);
        }

        return $this;
    }

    public function removeDownload(SkillDownload $download): static
    {
        $this->downloads->removeElement($download);

        return $this;
    }
}
