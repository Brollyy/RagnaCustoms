<?php

namespace App\Service;

use App\Entity\RankedScores;
use App\Entity\Score;
use App\Entity\Song;
use App\Entity\Utilisateur;
use App\Repository\RankedScoresRepository;
use App\Repository\ScoreHistoryRepository;
use App\Repository\ScoreRepository;
use App\Repository\SongDifficultyRepository;
use App\Repository\UtilisateurRepository;

class RankingScoreService
{


    private RankedScoresRepository $rankedScoresRepository;
    private ScoreHistoryRepository $scoreHistoryRepository;
    private ScoreRepository $scoreRepository;
    private UtilisateurRepository $userRepository;

    public function __construct(ScoreRepository          $scoreRepository,
                                UtilisateurRepository    $userRepository,
                                RankedScoresRepository   $rankedScoresRepository,
                                SongDifficultyRepository $songDifficultyRepository)
    {
        $this->scoreRepository = $scoreRepository;
        $this->rankedScoresRepository = $rankedScoresRepository;
        $this->userRepository = $userRepository;
        $this->songDifficultyRepository = $songDifficultyRepository;
    }

    public function calculateForSong(Song $song)
    {
        foreach ($song->getSongDifficulties() as $difficulty) {
            foreach ($difficulty->getScores() as $score) {
                if (!$difficulty->isRanked()) {
                    break;
                }
                $rawPP = $this->calculateRawPP($score);
                $score->setRawPP($rawPP);
                $this->scoreRepository->add($score);

                $totalPondPPScore = $this->calculateTotalPondPPScore($score->getUser());
                //insert/update of the score into ranked_scores
                $rankedScore = $this->rankedScoresRepository->findOneBy([
                    'user' => $score->getUser()
                ]);

                if ($rankedScore == null) {
                    $rankedScore = new RankedScores();
                    $rankedScore->setUser($score->getUser());
                }
                $rankedScore->setTotalPPScore($totalPondPPScore);
                $this->rankedScoresRepository->add($rankedScore);
            }
        }
    }

    public function calculateAll()
    {
        //we get all the existing users with ranked scores
        foreach ($this->userRepository->findAllWithRankedScore() as $user) {
            foreach ($user->getScoresRanked() as $score) {
                $score->setRawPP($this->calculateRawPP($score));
                $this->scoreRepository->add($score);
            }
            /** @var RankedScores $rankedScore */
            $rankedScore = $this->rankedScoresRepository->findOneBy(['user' => $user]);
            $rankedScore->setTotalPPScore($this->calculateTotalPondPPScore($user));
            $this->rankedScoresRepository->add($rankedScore);
        }


//             //first we re calculate all the raw pp
//             foreach ($rankedSongs->getSongDifficulties() as $difficulty) {
//                 foreach ($difficulty->getScores() as $score) {
//
//                     $rawPP = $this->calculateRawPP($score);
//                     $score->setRawPP($rawPP);
//                     $this->scoreRepository->add($score);
//
//                 }
//
//             //then we recalculate all the weighted pp
//             foreach ($difficulty->getScores() as $score) {
//
//               $totalPondPPScore = $this->calculateTotalPondPPScore($score->getUser());
//                      //insert/update of the score into ranked_scores
//                      $rankedScore = $this->rankedScoresRepository->findOneBy([
//                          'user' => $score->getUser()
//                      ]);
//
//                      if ($rankedScore == null) {
//                          $rankedScore = new RankedScores();
//                          $rankedScore->setUser($score->getUser());
//                      }
//                      $rankedScore->setTotalPPScore($totalPondPPScore);
//                      $this->rankedScoresRepository->add($rankedScore);
//                  }
//             }
    }

    public function calculateTotalPondPPScore(Utilisateur $user)
    {
        $totalPP = 0;
        $scores = $this->scoreRepository->createQueryBuilder('score')->leftJoin('score.songDifficulty', 'diff')->where('score.user = :user')->andWhere('diff.isRanked = true')->setParameter('user', $user)->addOrderBy('score.rawPP', 'desc')->getQuery()->getResult();

        $index = 0;
        foreach ($scores as $score) {
            $rawPPScore = $score->getRawPP();
            $pondPPScore = $rawPPScore * pow(0.965, $index);
            $totalPP = $totalPP + $pondPPScore;

            //register the weighted PP score
            $score->setWeightedPP(round($pondPPScore, 2));

            $index++;
        }
        return round($totalPP, 2);
    }

    public function calculateRawPP(Score $score)
    {
        $userScore = $score->getScore() / 100;
        $songLevel = $score->getSongDifficulty()->getDifficultyRank()->getLevel();
        $maxSongScore = $score->getSongDifficulty()->getTheoricalMaxScore();
        $minSongScore = $score->getSongDifficulty()->getTheoricalMinScore();

        //We subtract the theoretical min score to the user effective score
        //this is because the boat as a minimum speed. Doing so we consider that if no runes as been hit, then the score is 0
        $realUserScore = $userScore - $minSongScore;
        if ($realUserScore < 0) {
            $realUserScore = 0;
        }

        // raw pp is calculated by making the ratio between the current score and the theoretical maximum score.
        // it is ponderated by the song level
        $rawPP = (($realUserScore / $maxSongScore) * (0.4 + 0.1 * $songLevel)) * 250;

        return round($rawPP, 2);
    }

    public function countRanked(Utilisateur $user)
    {
        $res = $this->scoreRepository->createQueryBuilder("s")
                                     ->select('COUNT(s) as count')
                                     ->where('s.user = :user')
                                     ->andWhere('s.rawPP IS NOT NULL')
                                     ->andWhere('s.rawPP != 0')
                                     ->setParameter('user', $user)
                                     ->groupBy('s.user')
                                     ->getQuery()->getArrayResult();
        return $res[0]['count'];
    }

    public function timeAgoShort(Utilisateur $user)
    {
        /** @var Score $res */
        $res = $this->scoreRepository->createQueryBuilder("s")
                                     ->select('s')
                                     ->where('s.user = :user')
                                     ->andWhere('s.rawPP IS NOT NULL')
                                     ->andWhere('s.rawPP != 0')
                                     ->setParameter('user', $user)
                                     ->orderBy("s.updatedAt",'Desc')
                                     ->setFirstResult(0)->setMaxResults(1)
                                     ->getQuery()->getOneOrNullResult();
        return StatisticService::dateDiplayerShort($res->getUpdatedAt());
    }
}