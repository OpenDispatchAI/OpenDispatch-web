<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260404093025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_user (id BLOB NOT NULL, email VARCHAR(180) NOT NULL, roles CLOB NOT NULL, password VARCHAR(255) NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AD8A54A9E7927C74 ON admin_user (email)');
        $this->addSql('CREATE TABLE skill (id BLOB NOT NULL, skill_id VARCHAR(255) NOT NULL, yaml_content CLOB NOT NULL, name VARCHAR(255) NOT NULL, version VARCHAR(50) NOT NULL, description CLOB NOT NULL, author VARCHAR(255) NOT NULL, author_url VARCHAR(255) DEFAULT NULL, tags CLOB NOT NULL, languages CLOB NOT NULL, requires_bridge_shortcut BOOLEAN NOT NULL, bridge_shortcut_name VARCHAR(255) DEFAULT NULL, bridge_shortcut_share_url VARCHAR(255) DEFAULT NULL, action_count INTEGER NOT NULL, example_count INTEGER NOT NULL, icon_path VARCHAR(255) DEFAULT NULL, is_featured BOOLEAN NOT NULL, synced_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E3DE4775585C142 ON skill (skill_id)');
        $this->addSql('CREATE TABLE skill_download (id BLOB NOT NULL, downloaded_at DATETIME NOT NULL, app_version VARCHAR(50) DEFAULT NULL, skill_id BLOB NOT NULL, PRIMARY KEY (id), CONSTRAINT FK_3AB2EF825585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('CREATE INDEX IDX_3AB2EF825585C142 ON skill_download (skill_id)');
        $this->addSql('CREATE INDEX idx_download_date ON skill_download (downloaded_at)');
        $this->addSql('CREATE TABLE sync_log (id BLOB NOT NULL, status VARCHAR(20) NOT NULL, skill_count INTEGER NOT NULL, error_message CLOB DEFAULT NULL, commit_sha VARCHAR(40) NOT NULL, commit_url VARCHAR(255) NOT NULL, action_run_url VARCHAR(255) DEFAULT NULL, synced_at DATETIME NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE TABLE messenger_messages (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, body CLOB NOT NULL, headers CLOB NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL)');
        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 ON messenger_messages (queue_name, available_at, delivered_at, id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE admin_user');
        $this->addSql('DROP TABLE skill');
        $this->addSql('DROP TABLE skill_download');
        $this->addSql('DROP TABLE sync_log');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
