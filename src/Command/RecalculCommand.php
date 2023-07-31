<?php


namespace App\Command;


use App\Entity\RankedScores;
use App\Entity\Score;
use App\Repository\RankedScoresRepository;
use App\Repository\UtilisateurRepository;
use App\Service\RankingScoreService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RecalculCommand extends Command
{
    protected static $defaultName = 'ranking:math';

    public function __construct(
        private EntityManagerInterface $entityManager,
        private RankingScoreService $rankingScoreService,
        private RankedScoresRepository $rankedScoresRepository,
        private UtilisateurRepository $utilisateurRepository
    ) {
        return parent::__construct();
    }

    protected function configure(): void
    {
        // ...
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->utilisateurRepository->findAll();
        foreach ($users as $user) {
            echo "start: ".$user->getUsername()."\r\n";
            $scores = $user->getScores()->filter(function(Score $score){
                return $score->isRankable() ;
            });
                echo "scores: ".count($scores)."\r\n";
            /** @var Score $score */
            $i = 0;
            foreach ($scores as $score) {
                $i++;
                echo "score: ".($i)."/".count($scores)."\r\n";
                $rawPP = $this->rankingScoreService->calculateRawPP($score);
                $score->setRawPP($rawPP);

                $this->rankingScoreService->calculateRawPP($score);

                $totalPondPPScore = $this->rankingScoreService->calculateTotalPondPPScore($user);
                //insert/update of the score into ranked_scores
                $rankedScore = $this->rankedScoresRepository->findOneBy([
                    'user' => $user
                ]);

                if ($rankedScore == null) {
                    $rankedScore = new RankedScores();
                    $rankedScore->setUser($user);
                    $this->entityManager->persist($rankedScore);
                }
                $rankedScore->setTotalPPScore($totalPondPPScore);
            }
            echo "save: ".$user->getUsername()."\r\n";
            $this->entityManager->flush();
            echo "end: ".$user->getUsername()."\r\n";
        }

        return Command::SUCCESS;
    }
}