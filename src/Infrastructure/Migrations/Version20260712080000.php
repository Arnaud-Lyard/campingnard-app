<?php

declare(strict_types=1);

namespace App\Infrastructure\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260712080000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Unique constraint on testimonial.email — one review per email address';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DELETE FROM testimonial t1 USING testimonial t2 WHERE t1.id > t2.id AND t1.email = t2.email');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_TESTIMONIAL_EMAIL ON testimonial (email)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_TESTIMONIAL_EMAIL');
    }
}
