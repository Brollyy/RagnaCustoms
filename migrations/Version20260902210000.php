<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260902210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Guarantee one up/down vote state per user and song.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'DELETE duplicate_vote FROM vote_counter duplicate_vote '
            .'INNER JOIN vote_counter retained_vote '
            .'ON duplicate_vote.user_id = retained_vote.user_id '
            .'AND duplicate_vote.song_id = retained_vote.song_id '
            .'AND duplicate_vote.id > retained_vote.id'
        );
        $this->addSql(
            'UPDATE song song_to_recount SET '
            .'vote_up = (SELECT COUNT(*) FROM vote_counter WHERE song_id = song_to_recount.id AND votes_indc = 1), '
            .'vote_down = (SELECT COUNT(*) FROM vote_counter WHERE song_id = song_to_recount.id AND votes_indc = 0)'
        );
        $this->addSql('CREATE UNIQUE INDEX uniq_vote_counter_user_song ON vote_counter (user_id, song_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_vote_counter_user_song ON vote_counter');
    }
}
