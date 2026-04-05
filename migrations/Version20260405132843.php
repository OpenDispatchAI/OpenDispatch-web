<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405132843 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE skill_manifest (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, content CLOB NOT NULL, commit_sha VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL)');
        $this->addSql('CREATE TEMPORARY TABLE __temp__skill AS SELECT id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at FROM skill');
        $this->addSql('DROP TABLE skill');
        $this->addSql('CREATE TABLE skill (id BLOB NOT NULL, skill_id VARCHAR(255) NOT NULL, yaml_content CLOB NOT NULL, name VARCHAR(255) NOT NULL, version VARCHAR(50) NOT NULL, description CLOB NOT NULL, author VARCHAR(255) NOT NULL, author_url VARCHAR(255) DEFAULT NULL, tags CLOB NOT NULL, languages CLOB NOT NULL, requires_bridge_shortcut BOOLEAN NOT NULL, bridge_shortcut_name VARCHAR(255) DEFAULT NULL, bridge_shortcut_share_url CLOB DEFAULT NULL, action_count INTEGER NOT NULL, example_count INTEGER NOT NULL, is_featured BOOLEAN NOT NULL, synced_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, icon_data CLOB DEFAULT NULL, shortcut_data CLOB DEFAULT NULL, compiled_info CLOB DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO skill (id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at) SELECT id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at FROM __temp__skill');
        $this->addSql('DROP TABLE __temp__skill');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E3DE4775585C142 ON skill (skill_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE skill_manifest');
        $this->addSql('CREATE TEMPORARY TABLE __temp__skill AS SELECT id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at FROM skill');
        $this->addSql('DROP TABLE skill');
        $this->addSql('CREATE TABLE skill (id BLOB NOT NULL, skill_id VARCHAR(255) NOT NULL, yaml_content CLOB NOT NULL, name VARCHAR(255) NOT NULL, version VARCHAR(50) NOT NULL, description CLOB NOT NULL, author VARCHAR(255) NOT NULL, author_url VARCHAR(255) DEFAULT NULL, tags CLOB NOT NULL, languages CLOB NOT NULL, requires_bridge_shortcut BOOLEAN NOT NULL, bridge_shortcut_name VARCHAR(255) DEFAULT NULL, bridge_shortcut_share_url VARCHAR(255) DEFAULT NULL, action_count INTEGER NOT NULL, example_count INTEGER NOT NULL, is_featured BOOLEAN NOT NULL, synced_at DATETIME NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, icon_path VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('INSERT INTO skill (id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at) SELECT id, skill_id, yaml_content, name, version, description, author, author_url, tags, languages, requires_bridge_shortcut, bridge_shortcut_name, bridge_shortcut_share_url, action_count, example_count, is_featured, synced_at, created_at, updated_at FROM __temp__skill');
        $this->addSql('DROP TABLE __temp__skill');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5E3DE4775585C142 ON skill (skill_id)');
    }
}
