<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Song;
use App\Entity\Utilisateur;
use App\Entity\VoteCounter;
use App\Repository\SongDifficultyRepository;
use App\Repository\UtilisateurRepository;
use App\Service\VoteService;
use JsonException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WanadevVoteController extends AbstractController
{
    #[Route(path: '/wanapi/score/{apiKey}/vote', name: 'wd_api_song_vote', methods: ['GET', 'PUT'])]
    public function vote(
        Request $request,
        string $apiKey,
        UtilisateurRepository $utilisateurRepository,
        SongDifficultyRepository $songDifficultyRepository,
        VoteService $voteService,
    ): JsonResponse {
        /** @var Utilisateur|null $user */
        $user = $utilisateurRepository->findOneBy(['apiKey' => $apiKey]);
        if ($user === null) {
            return $this->error('unknown_api_key', 'The API key is not recognized.', 401);
        }

        if ($request->isMethod('PUT')) {
            try {
                $payload = json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return $this->error('invalid_json', 'The request body must be valid JSON.', 400);
            }

            if (!is_array($payload) || !array_key_exists('direction', $payload)) {
                return $this->error('invalid_request', 'The beatmap and direction fields are required.', 400);
            }
            $beatmap = $payload['beatmap'] ?? null;
            $direction = $payload['direction'];
        } else {
            $beatmap = $request->query->get('beatmap');
            $direction = null;
        }

        if (!is_string($beatmap) || $beatmap === '' || strlen($beatmap) > 255) {
            return $this->error('invalid_beatmap', 'The beatmap must be a non-empty string.', 400);
        }

        if ($request->isMethod('PUT') && !in_array($direction, ['up', 'down', null], true)) {
            return $this->error('invalid_direction', 'The direction must be up, down, or null.', 400);
        }

        $songDifficulty = $songDifficultyRepository->findOneBy(['wanadevHash' => $beatmap]);
        if ($songDifficulty === null || $songDifficulty->getSong() === null) {
            return $this->error('unknown_beatmap', 'The beatmap is not recognized.', 404);
        }

        $song = $songDifficulty->getSong();
        if (!$song->isAvailable()) {
            return $this->error('song_unavailable', 'The song is not available for voting.', 409);
        }
        if (!$voteService->canUpDownVote($song, $user)) {
            return $this->error('song_not_played', 'A score for this song is required before voting.', 409);
        }

        $voteCounter = $request->isMethod('PUT')
            ? $voteService->setVoteState($song, $user, $this->directionToVote($direction))
            : $voteService->getLast($song, $user);

        return new JsonResponse($this->responsePayload($song, $beatmap, $voteCounter));
    }

    private function directionToVote(?string $direction): ?bool
    {
        return match ($direction) {
            'up' => true,
            'down' => false,
            default => null,
        };
    }

    /** @return array<string, int|string|null> */
    private function responsePayload(Song $song, string $beatmap, ?VoteCounter $voteCounter): array
    {
        return [
            'songId' => $song->getId(),
            'beatmap' => $beatmap,
            'currentVote' => match ($voteCounter?->getVotesIndc()) {
                true => 'up',
                false => 'down',
                null => null,
            },
            'upvotes' => $song->getVoteUp() ?? 0,
            'downvotes' => $song->getVoteDown() ?? 0,
        ];
    }

    private function error(string $code, string $message, int $status): JsonResponse
    {
        return new JsonResponse(['error' => $code, 'message' => $message], $status);
    }
}
