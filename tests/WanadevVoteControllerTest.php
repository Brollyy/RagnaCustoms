<?php

declare(strict_types=1);

namespace App\Tests;

use App\Controller\WanadevVoteController;
use App\Entity\Song;
use App\Entity\SongDifficulty;
use App\Entity\Utilisateur;
use App\Entity\VoteCounter;
use App\Repository\SongDifficultyRepository;
use App\Repository\UtilisateurRepository;
use App\Service\VoteService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class WanadevVoteControllerTest extends TestCase
{
    public function testUnknownApiKeyIsUnauthorized(): void
    {
        $users = $this->createMock(UtilisateurRepository::class);
        $users->method('findOneBy')->willReturn(null);

        $response = (new WanadevVoteController())->vote(
            Request::create('/wanapi/score/bad/vote', 'GET', ['beatmap' => 'abc']),
            'bad',
            $users,
            $this->createMock(SongDifficultyRepository::class),
            $this->createMock(VoteService::class),
        );

        self::assertSame(401, $response->getStatusCode());
        self::assertSame('unknown_api_key', $this->responseData($response)['error']);
    }

    public function testPutMapsDirectionAndReturnsCanonicalState(): void
    {
        [$song, $difficulty, $user] = $this->eligibleEntities();
        $users = $this->createMock(UtilisateurRepository::class);
        $users->method('findOneBy')->willReturn($user);
        $difficulties = $this->createMock(SongDifficultyRepository::class);
        $difficulties->method('findOneBy')->willReturn($difficulty);

        $vote = (new VoteCounter())->setSong($song)->setUser($user)->setVotesIndc(true);
        $voteService = $this->createMock(VoteService::class);
        $voteService->method('canUpDownVote')->willReturn(true);
        $voteService->expects(self::once())
            ->method('setVoteState')
            ->with($song, $user, true)
            ->willReturn($vote);

        $request = Request::create(
            '/wanapi/score/local-key/vote',
            'PUT',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['beatmap' => 'local-hash', 'direction' => 'up'], JSON_THROW_ON_ERROR),
        );
        $response = (new WanadevVoteController())->vote(
            $request,
            'local-key',
            $users,
            $difficulties,
            $voteService,
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame([
            'songId' => null,
            'beatmap' => 'local-hash',
            'currentVote' => 'up',
            'upvotes' => 7,
            'downvotes' => 2,
        ], $this->responseData($response));
    }

    public function testUnplayedSongIsRejected(): void
    {
        [, $difficulty, $user] = $this->eligibleEntities();
        $users = $this->createMock(UtilisateurRepository::class);
        $users->method('findOneBy')->willReturn($user);
        $difficulties = $this->createMock(SongDifficultyRepository::class);
        $difficulties->method('findOneBy')->willReturn($difficulty);
        $voteService = $this->createMock(VoteService::class);
        $voteService->method('canUpDownVote')->willReturn(false);

        $response = (new WanadevVoteController())->vote(
            Request::create('/wanapi/score/local-key/vote', 'GET', ['beatmap' => 'local-hash']),
            'local-key',
            $users,
            $difficulties,
            $voteService,
        );

        self::assertSame(409, $response->getStatusCode());
        self::assertSame('song_not_played', $this->responseData($response)['error']);
    }

    /** @return array{Song, SongDifficulty, Utilisateur} */
    private function eligibleEntities(): array
    {
        $song = (new Song())
            ->setVoteUp(7)
            ->setVoteDown(2)
            ->setIsWip(false)
            ->setActive(true)
            ->setIsDeleted(false)
            ->setProgrammationDate(new DateTimeImmutable('-1 day'));
        $song->setIsModerated(true);

        $difficulty = (new SongDifficulty())->setSong($song)->setWanadevHash('local-hash');

        return [$song, $difficulty, new Utilisateur()];
    }

    /** @return array<string, mixed> */
    private function responseData(\Symfony\Component\HttpFoundation\JsonResponse $response): array
    {
        return json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR);
    }
}
