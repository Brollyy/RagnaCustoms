<?php

namespace App\Service;

use App\Controller\WanadevApiController;
use App\Entity\DownloadCounter;
use App\Entity\Score;
use App\Entity\ScoreHistory;
use App\Entity\Song;
use App\Entity\SongDifficulty;
use App\Entity\Utilisateur;
use App\Service\RankingScoreService;
use DateTime;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;


class StatisticService
{
    private static array $StatisticsOnScoreHistory;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RankingScoreService $rankingScoreService
    ) {
    }

    public static function dateDisplay(?DateTimeInterface $date = null): string
    {
        if (!$date) {
            return 'no date';
        }

        $difference = $date->diff(new DateTime(), true);

        $etime = date_create('@0')->add($difference)->getTimestamp();

        if ($etime < 1) {
            return '0 seconds';
        }

        $a = array(
            365 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second',
        );
        $a_plural = [
            'year' => 'years',
            'month' => 'months',
            'day' => 'days',
            'hour' => 'hours',
            'minute' => 'minutes',
            'second' => 'seconds',
        ];

        foreach ($a as $secs => $str) {
            $d = $etime / $secs;
            if ($d >= 1) {
                $r = round($d);

                return $r.' '.($r > 1 ? $a_plural[$str] : $str).' ago';
            }
        }

        return '99999 seconds';
    }

    public static function dateDisplayedShort(?DateTimeInterface $date = null): string
    {
        if (!$date) {
            return 'soon';
        }

        $difference = $date->diff(new DateTime(), true);

        $etime = date_create('@0')->add($difference)->getTimestamp();

        if ($etime < 1) {
            return '0 seconds';
        }

        $a = array(
            365 * 24 * 60 * 60 => 'y',
            30 * 24 * 60 * 60 => 'mo',
            24 * 60 * 60 => 'd',
            60 * 60 => 'h',
            60 => 'm',
            1 => 's',
        );

        foreach ($a as $secs => $str) {
            $d = $etime / $secs;
            if ($d >= 1) {
                $r = round($d);

                return $r.$str.' ago';
            }
        }

        return '99999 seconds';
    }

    public function getLastXDays($days = 30): array
    {
        $first = (new DateTime())->modify("-".$days." days");
        $days = [];
        while ($first <= new DateTime()) {
            $days[] = $first->format('Y-m-d');
            $first->modify("+1 day");
        }

        return $days;
    }

    public function getDownloadsLastXDays($days, Song $song): array
    {
        $first = (new DateTime())->modify("-".$days." days");
        $data = [];
        $i = 0;
        while ($first <= new DateTime()) {
            $result = $this->em->getRepository(DownloadCounter::class)->createQueryBuilder("d")
                ->select('COUNT(d) AS nb')
                ->where("d.song = :song")
                ->andWhere("d.createdAt LIKE :date")
                ->setParameter('song', $song)
                ->setParameter('date', $first->format('Y-m-d')."%")
                ->setFirstResult(0)->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();
            $first->modify("+1 day");

            if ($i == 0) {
                $data[] = $result['nb'];
            } else {
                $data[] = $data[$i - 1] + $result['nb'];
            }
            $i++;
        }

        return $data;
    }

    public function getPlayedLastXDays($days, Song $song): array
    {
        $hashes = $song->getHashes();

        $first = (new DateTime())->modify("-".$days." days");
        $data = [];
        $i = 0;
        while ($first <= new DateTime()) {
            $result = $this->em->getRepository(ScoreHistory::class)->createQueryBuilder("d")
                ->select('COUNT(d) AS nb')
                ->where("d.hash IN (:hashes)")
                ->andWhere("d.createdAt LIKE :date")
                ->setParameter('hashes', $hashes)
                ->setParameter('date', $first->format('Y-m-d')."%")
                ->setFirstResult(0)->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();
            $first->modify("+1 day");
            if ($i == 0) {
                $data[] = $result['nb'];
            } else {
                $data[] = $data[$i - 1] + $result['nb'];
            }
            $i++;
        }

        return $data;
    }

    public function getTotalDistance(Utilisateur $user)
    {
        $this->getStatisticsScoreHistory($user);

        return self::$StatisticsOnScoreHistory['distance'] ?? 0;
    }

    public function getStatisticsScoreHistory(Utilisateur $user): void
    {
        if (self::$StatisticsOnScoreHistory == null || count(self::$StatisticsOnScoreHistory) == 0) {
            $result = $this->em->getRepository(ScoreHistory::class)->createQueryBuilder("d")
                ->select('SUM(d.score) AS distance')
                ->addSelect('SUM(d.hit) AS count_notes_hit')
                ->addSelect('SUM(d.missed) AS count_notes_missed')
                ->addSelect('0 AS count_notes_not_processed')
                ->where("d.user = :user")
                ->setParameter('user', $user)
                ->groupBy('d.user')
                ->setFirstResult(0)->setMaxResults(1)
                ->getQuery()->getOneOrNullResult();
            if ($result != null) {
                foreach ($result as $k => $v) {
                    self::$StatisticsOnScoreHistory[$k] = $v ?? 0;
                }
            }
        }
    }

    public function getTotalNotesMissed(Utilisateur $user): int
    {
        $this->getStatisticsScoreHistory($user);

        return self::$StatisticsOnScoreHistory['count_notes_missed'] ?? 0;
    }

    public function getTotalNotesHit(Utilisateur $user): int
    {
        $this->getStatisticsScoreHistory($user);

        return self::$StatisticsOnScoreHistory['count_notes_hit'] ?? 0;
    }

    public function getTotalNotesNotProcessed(Utilisateur $user): int
    {
        $this->getStatisticsScoreHistory($user);

        return self::$StatisticsOnScoreHistory['count_notes_not_processed'] ?? 0;
    }

    /** Idéalement travailler avec une interface plutôt que deux faire deux méthodes */
    public function getScatterDataSetsByScore(?Score $sh): array
    {
        $raw_data = json_decode(json_decode(($sh->getExtra())))->HitDeltaTimes;
        $song_file = "../public/".$sh->getSongDifficulty()->getDifficultyFile('..');

        return $this->getScatterDatasets($raw_data, $song_file);
    }

    private function getScatterDatasets($raw_data, string $song_file): array
    {
        $json = json_decode(file_get_contents($song_file));
        $notes = $json->_notes;
        for ($i = 0; $i < count($raw_data); $i++) {
            if ($raw_data[$i] == -1000) {
                $raw_data[$i] = -100;
            }
        }
        $df = array();
        foreach ($notes as $note) {
            $df[] = (array)$note;
        }
        $datasets = [
            [
                "label" => "miss",
                "data" => [],
                'pointBackgroundColor' => '#f55142',
            ],
            [
                "label" => "ok",
                "data" => [],
                'pointBackgroundColor' => '#42c8f5',
            ],
            [
                "label" => "perfect",
                "data" => [],
                'pointBackgroundColor' => '#42f581',

            ],
        ];
        foreach ($df as $k => $note) {
            $note['x'] = $note['_time'];
            $note['y'] = $raw_data[$k];
            $note['drum'] = $note['_lineIndex'] == 0 ? "left" : ($note['_lineIndex'] == 1 ? "center-left" : ($note['_lineIndex'] == 2 ? "center-right" : "right"));
            unset($note['_time']);
            unset($note['_lineLayer']);
            unset($note['_lineIndex']);
            unset($note['_type']);
            unset($note['_cutDirection']);
            if ($note['y'] == -100) {
                $datasets[0]['data'][] = $note;
            } elseif ($note['y'] <= -15 || $note['y'] >= 15) {
                $datasets[1]['data'][] = $note;
            } else {
                $datasets[2]['data'][] = $note;
            }
        }

        return (['datasets' => $datasets]);
    }

    public function getScatterDataSetsByScorehistory(?ScoreHistory $sh): array
    {
        $raw_data = json_decode(json_decode(($sh->getExtra())))->HitDeltaTimes;
        $song_file = "../public/".$sh->getSongDifficulty()->getDifficultyFile('..');

        return $this->getScatterDatasets($raw_data, $song_file);
    }

    public function getFullDatasetByScorehistory(?ScoreHistory $sh): array
    {
        $raw_data = json_decode(json_decode(($sh->getExtra())))->HitDeltaTimes;
        $song_file = "../public/".$sh->getSongDifficulty()->getDifficultyFile('..');

        return $this->getFullDataset($raw_data, $song_file);
    }

    public function getFullDataset($raw_data, string $song_file): array
    {
        $json = json_decode(file_get_contents($song_file));
        $notes = $json->_notes;
        $datasets = [];
        for ($i = 0; $i < count($raw_data); $i++) {
            if ($raw_data[$i] == -1000) {
                $raw_data[$i] = -100;
            }
        }
        $df = array();
        foreach ($notes as $note) {
            $df[] = (array)$note;
        }

        foreach ($df as $k => $note) {
            $note['x'] = $note['_time'];
            $note['y'] = $raw_data[$k];
            $note['drum'] = $note['_lineIndex'] == 0 ? "left" : ($note['_lineIndex'] == 1 ? "center-left" : ($note['_lineIndex'] == 2 ? "center-right" : "right"));
            unset($note['_time']);
            unset($note['_lineLayer']);
            unset($note['_lineIndex']);
            unset($note['_type']);
            unset($note['_cutDirection']);
            $datasets['data'][] = $note;
        }

        return $datasets;
    }

    public function getPPChartDataSetsBySongDiff(SongDifficulty $diff, ?Utilisateur $hightlightUser, bool $isVR, bool $isOKOD, bool $showAvgLines, bool $recalculatePPScores): mixed
    {
        $qb = $this->em->getRepository(Score::class)
            ->createQueryBuilder('s')
            ->select('s')
            ->where('s.songDifficulty = :diff')
            ->andWhere('s.plateform IN (:type)')
            ->setParameter('diff', $diff)
            ->groupBy('s.user');
        
        if ($isVR) {
            $qb->setParameter('type', WanadevApiController::VR_PLATEFORM);
        } else if ($isOKOD) {
            $qb->setParameter('type', WanadevApiController::OKOD_PLATEFORM);
        } else {
            $qb->setParameter('type', WanadevApiController::FLAT_PLATEFORM);
        }

        $scores = $qb->getQuery()->getResult();
        return ['datasets' => array_merge( 
            $this->getPPScoresDataSets($scores, $hightlightUser, $recalculatePPScores), 
            $this->getPPCurveDataSets($diff, $scores, $showAvgLines)
        )];
    }

    /**
     * @param Score[] $scores
     */
    public function getPPCurveDataSets(SongDifficulty $diff, array $scores, bool $showAvgLines):array 
    {
        $datasets = [
            [
                "label" => "est. average",
                "type" => "line",
                "data" => [],
                "borderColor" => "#F4505D",
                "backgroundColor" => "#F4505D"
            ],
            [
                "label" => "act. average",
                "type" => "line",
                "data" => [],
                "borderColor" => "#32B027",
                "backgroundColor" => "#32B027"
            ],
            [
                "label" => "curve",
                "type" => "line",
                "data" => [],
                "fill" => true,
                "cubicInterpolationMode" => "monotone",
                "pointBackgroundColor" => "#ffffff",
                "pointBorderColor" => "#ffffff",
                "pointRadius" => 0,
                "pointHitRadius" => 5,
                "backgroundColor" => "rgba(255, 255, 255, 0.3)",
                "borderColor" => "#ffffff",
                "borderWidth" => 1.5
            ]
        ];

        $minDistance = $diff->getTheoricalMinScore();
        $maxDistance = $diff->getTheoricalMaxScore();

        if ($showAvgLines) {
            $estAvgDistance = round($minDistance + ($maxDistance - $minDistance) * $diff->getEstAvgAccuracy() / 100, 2);
            $datasets[0]['data'] = [
                ['x' => $estAvgDistance, 'y' => 0, 'accuracy' => round($diff->getEstAvgAccuracy(), 3)],
                ['x' => $estAvgDistance, 'y' => $diff->getPPCurveMax(), 'accuracy' => round($diff->getEstAvgAccuracy(), 3)]
            ];

            if (count($scores) > 0) {
                $actualAvgDistance = 0.0;
                foreach ($scores as $score) {
                    $actualAvgDistance += $score->getScore();
                }
                $actualAvgDistance /= 100 * count($scores);
                $actualAvgAccuracy = round(($actualAvgDistance - $minDistance) / ($maxDistance - $minDistance) * 100, 3);
                $datasets[1]['data'] = [
                    ['x' => $actualAvgDistance, 'y' => 0, 'accuracy' => $actualAvgAccuracy],
                    ['x' => $actualAvgDistance, 'y' => $diff->getPPCurveMax(), 'accuracy' => $actualAvgAccuracy]
                ];
            } else {
                unset($datasets[1]);
            }
        } else {
            unset($datasets[0]);
            unset($datasets[1]);
        }

        for($i = 0; $i <= 100; $i++) {
            $distance = round($minDistance + ($maxDistance - $minDistance) * $i / 100, 2);
            $mockScore = new Score();
            $mockScore->setSongDifficulty($diff);
            $mockScore->setScore($distance * 100);
            $datasets[2]['data'][] = [
                'x' => $distance,
                'y' => $this->rankingScoreService->calculateRawPP($mockScore)
            ];
        }

        return $datasets;
    }

    /**
     * @param Score[] $scores
     */
    public function getPPScoresDataSets(array $scores, ?Utilisateur $highlightUser, bool $recalculatePPScores):array
    {
        $datasets = [
            [
                "label" => $highlightUser?->getUsername(),
                "type" => "scatter",
                "data" => [],
                'pointBackgroundColor' => 'rgb(255, 193, 7)',
                "pointRadius" => 4,
                "pointHitRadius" => 5,
                "pointHoverRadius" => 5,
            ],
            [
                "label" => "players",
                "type" => "scatter",
                "data" => [],
                'pointBackgroundColor' => '#4B9CE2',
                "pointRadius" => 4,
                "pointHitRadius" => 5,
                "pointHoverRadius" => 5,
            ],
        ];
        
        foreach ($scores as $score) {
            $point = [
                'x' => $score->getScore() / 100.0,
                'y' => $recalculatePPScores ? $this->rankingScoreService->calculateRawPP($score) : $score->getRawPP(),
                'username' => $score->getUser()->getUsername()
            ];
            if ($score->getUser() == $highlightUser) {
                $datasets[0]['data'][] = $point;
            } else {
                $datasets[1]['data'][] = $point;
            }
        }

        if (count($datasets[0]['data']) == 0) {
            unset($datasets[0]);
        }

        return $datasets;
    }

    public function getPPHistogramDataSet(Utilisateur $user, bool $isVR, bool $isOKOD): mixed
    {
        $qb = $this->em->getRepository(Score::class)
            ->createQueryBuilder('s')
            ->select('s')
            ->join('s.songDifficulty', 'sd')
            ->where('s.user = :user')
            ->andWhere('sd.isRanked = true')
            ->andWhere('s.plateform IN (:type)')
            ->setParameter('user', $user);
        
        if ($isVR) {
            $qb->setParameter('type', WanadevApiController::VR_PLATEFORM);
        } else if ($isOKOD) {
            $qb->setParameter('type', WanadevApiController::OKOD_PLATEFORM);
        } else {
            $qb->setParameter('type', WanadevApiController::FLAT_PLATEFORM);
        }

        $scores = $qb->getQuery()->getResult();
        $datasets = [
            [
                "label" => '# of scores with Raw PP',
                "data" => [],
                "backgroundColor" => '#ffffff',
                "borderColor" => '#ffffff',
                "barPercentage" => 1,
                "categoryPercentage" => 1
            ],
        ];
        
        $ppBuckets=[];

        foreach ($scores as $score) {
            $ppBucket = round($score->getRawPP(), -1);
            $ppBuckets[$ppBucket] = ($ppBuckets[$ppBucket] ?? 0) + 1;
        }

        foreach ($ppBuckets as $pp => $count) {
            $datasets[0]['data'][] = ['x' => $pp, 'y' => $count];
        }

        return ['datasets' => $datasets];
    }
}

