<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260319130541 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE skill_download (id UUID NOT NULL, downloaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, app_version VARCHAR(50) DEFAULT NULL, skill_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_3AB2EF825585C142 ON skill_download (skill_id)');
        $this->addSql('CREATE INDEX idx_download_date ON skill_download (downloaded_at)');
        $this->addSql('CREATE TABLE sync_log (id UUID NOT NULL, status VARCHAR(20) NOT NULL, skill_count INT NOT NULL, error_message TEXT DEFAULT NULL, commit_sha VARCHAR(40) NOT NULL, commit_url VARCHAR(255) NOT NULL, action_run_url VARCHAR(255) DEFAULT NULL, synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE skill_download ADD CONSTRAINT FK_3AB2EF825585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE');
        $this->addSql('ALTER TABLE skill ADD name VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE skill ADD version VARCHAR(50) NOT NULL');
        $this->addSql('ALTER TABLE skill ADD description TEXT NOT NULL');
        $this->addSql('ALTER TABLE skill ADD author VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE skill ADD author_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE skill ADD languages JSON NOT NULL');
        $this->addSql('ALTER TABLE skill ADD requires_bridge_shortcut BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE skill ADD bridge_shortcut_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE skill ADD bridge_shortcut_share_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE skill ADD action_count INT NOT NULL');
        $this->addSql('ALTER TABLE skill ADD example_count INT NOT NULL');
        $this->addSql('ALTER TABLE skill ADD is_featured BOOLEAN NOT NULL');
        $this->addSql('ALTER TABLE skill ADD synced_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL');
        $this->addSql('ALTER TABLE skill DROP published_at');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE skill_download DROP CONSTRAINT FK_3AB2EF825585C142');
        $this->addSql('DROP TABLE skill_download');
        $this->addSql('DROP TABLE sync_log');
        $this->addSql('ALTER TABLE skill ADD published_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE skill DROP name');
        $this->addSql('ALTER TABLE skill DROP version');
        $this->addSql('ALTER TABLE skill DROP description');
        $this->addSql('ALTER TABLE skill DROP author');
        $this->addSql('ALTER TABLE skill DROP author_url');
        $this->addSql('ALTER TABLE skill DROP languages');
        $this->addSql('ALTER TABLE skill DROP requires_bridge_shortcut');
        $this->addSql('ALTER TABLE skill DROP bridge_shortcut_name');
        $this->addSql('ALTER TABLE skill DROP bridge_shortcut_share_url');
        $this->addSql('ALTER TABLE skill DROP action_count');
        $this->addSql('ALTER TABLE skill DROP example_count');
        $this->addSql('ALTER TABLE skill DROP is_featured');
        $this->addSql('ALTER TABLE skill DROP synced_at');
    }
}
