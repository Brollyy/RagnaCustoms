<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Song;
use App\Entity\Utilisateur;
use App\Entity\VoteCounter;
use App\Repository\VoteCounterRepository;
use App\Service\SongService;
use App\Service\VoteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class VoteServiceTest extends TestCase
{
    public function testDesiredVoteStateIsIdempotentAndSwitchable(): void
    {
        $song = (new Song())->setVoteUp(0)->setVoteDown(0);
        $user = new Utilisateur();
        $storedVote = null;

        $repository = $this->createMock(VoteCounterRepository::class);
        $repository->method('findOneBy')->willReturnCallback(
            static function () use (&$storedVote): ?VoteCounter {
                return $storedVote;
            }
        );

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::exactly(4))->method('lock');
        $entityManager->expects(self::exactly(4))->method('flush');
        $entityManager->expects(self::once())->method('persist')->willReturnCallback(
            static function (VoteCounter $voteCounter) use (&$storedVote): void {
                $storedVote = $voteCounter;
            }
        );
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback()
        );

        $service = new VoteService(
            $entityManager,
            $repository,
            (new ReflectionClass(SongService::class))->newInstanceWithoutConstructor(),
        );

        $service->setVoteState($song, $user, true);
        self::assertSame(1, $song->getVoteUp());
        self::assertSame(0, $song->getVoteDown());
        self::assertTrue($storedVote->getVotesIndc());

        $service->setVoteState($song, $user, true);
        self::assertSame(1, $song->getVoteUp(), 'A retried PUT must not increment the count.');
        self::assertSame(0, $song->getVoteDown());

        $service->setVoteState($song, $user, false);
        self::assertSame(0, $song->getVoteUp());
        self::assertSame(1, $song->getVoteDown());
        self::assertFalse($storedVote->getVotesIndc());

        $service->setVoteState($song, $user, null);
        self::assertSame(0, $song->getVoteUp());
        self::assertSame(0, $song->getVoteDown());
        self::assertNull($storedVote->getVotesIndc());
    }

    public function testCorruptAggregateCannotBecomeNegative(): void
    {
        $song = (new Song())->setVoteUp(0)->setVoteDown(0);
        $user = new Utilisateur();
        $vote = (new VoteCounter())->setSong($song)->setUser($user)->setVotesIndc(true);

        $repository = $this->createMock(VoteCounterRepository::class);
        $repository->method('findOneBy')->willReturn($vote);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $callback): mixed => $callback()
        );

        $service = new VoteService(
            $entityManager,
            $repository,
            (new ReflectionClass(SongService::class))->newInstanceWithoutConstructor(),
        );
        $service->setVoteState($song, $user, null);

        self::assertSame(0, $song->getVoteUp());
        self::assertSame(0, $song->getVoteDown());
    }
}
