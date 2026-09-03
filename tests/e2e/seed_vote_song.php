<?php

declare(strict_types=1);

use App\Entity\DifficultyRank;
use App\Entity\Score;
use App\Entity\Song;
use App\Entity\Utilisateur;
use App\Kernel;
use App\Service\SongService;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';
(new Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');

$fixtureDir = $argv[1] ?? '';
if ($fixtureDir === '' || !is_dir($fixtureDir)) {
    throw new RuntimeException('Usage: php tests/e2e/seed_vote_song.php /path/to/custom-song');
}

$kernel = new Kernel('test', true);
$kernel->boot();
$container = $kernel->getContainer()->get('test.service_container');
$databaseUrl = (string) ($_SERVER['DATABASE_URL'] ?? $_ENV['DATABASE_URL'] ?? '');
$host = parse_url($databaseUrl, PHP_URL_HOST);
if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Refusing to seed a non-loopback database.');
}

$infoPath = rtrim($fixtureDir, '/').'/info.dat';
$info = json_decode((string) file_get_contents($infoPath), true, 512, JSON_THROW_ON_ERROR);
$requiredFiles = [$infoPath, rtrim($fixtureDir, '/').'/'.$info['_songFilename'], rtrim($fixtureDir, '/').'/'.$info['_coverImageFilename']];
foreach ($info['_difficultyBeatmapSets'][0]['_difficultyBeatmaps'] as $difficulty) {
    $requiredFiles[] = rtrim($fixtureDir, '/').'/'.$difficulty['_beatmapFilename'];
}
foreach ($requiredFiles as $requiredFile) {
    if (!is_file($requiredFile)) {
        throw new RuntimeException('Fixture file is missing: '.$requiredFile);
    }
}

$entityManager = $container->get('doctrine')->getManager();
$user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['apiKey' => 'local-vote-key']);
if (!$user instanceof Utilisateur) {
    throw new RuntimeException('The disposable local-vote-key user must be seeded first.');
}

$rankLevel = (int) $info['_difficultyBeatmapSets'][0]['_difficultyBeatmaps'][0]['_difficultyRank'];
$rank = $entityManager->getRepository(DifficultyRank::class)->findOneBy(['level' => $rankLevel]);
if (!$rank instanceof DifficultyRank) {
    $rank = (new DifficultyRank())->setLevel($rankLevel)->setColor('#ffffff');
    $entityManager->persist($rank);
}

$song = $entityManager->getRepository(Song::class)->findOneBy([
    'name' => trim((string) $info['_songName']),
    'authorName' => (string) $info['_songAuthorName'],
]);
if (!$song instanceof Song) {
    $song = (new Song())
        ->setName(trim((string) $info['_songName']))
        ->setFileName((string) $info['_songFilename'])
        ->setCoverImageFileName((string) $info['_coverImageFilename'])
        ->setLevelAuthorName((string) $info['_levelAuthorName'])
        ->setDownloads(0)
        ->setVoteUp(0)
        ->setVoteDown(0)
        ->setIsWip(false)
        ->setActive(true)
        ->setPrivate(false)
        ->setBestPlatform(['vr', 'flat'])
        ->setProgrammationDate(new DateTime('-1 day'))
        ->addMapper($user);
    $song->setIsModerated(true);
    $entityManager->persist($song);
    $entityManager->flush();
}

$zipDirectory = dirname(__DIR__, 2).'/public/songs-files';
if (!is_dir($zipDirectory) && !mkdir($zipDirectory, 0775, true) && !is_dir($zipDirectory)) {
    throw new RuntimeException('Could not create local songs-files directory.');
}
$zipPath = $zipDirectory.'/'.$song->getId().'.zip';
$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    throw new RuntimeException('Could not create fixture archive.');
}
foreach (new DirectoryIterator($fixtureDir) as $entry) {
    if ($entry->isFile() && !$entry->isDot()) {
        $zip->addFile($entry->getPathname(), $entry->getFilename());
    }
}
$zip->close();

$container->get(SongService::class)->processFile(null, $song, false);
$song->setIsWip(false)->setActive(true)->setProgrammationDate(new DateTime('-1 day'));
$song->setIsModerated(true);
$entityManager->flush();

$difficulty = $song->getSongDifficulties()->first();
if ($difficulty === false || $difficulty->getWanadevHash() === null) {
    throw new RuntimeException('RagnaCustoms did not create a hashed difficulty.');
}

$score = $entityManager->getRepository(Score::class)->findOneBy([
    'user' => $user,
    'songDifficulty' => $difficulty,
    'plateform' => 'pc',
]);
if (!$score instanceof Score) {
    $score = (new Score())->setUser($user)->setSongDifficulty($difficulty)->setScore(12345);
    $score->setPlateform('pc');
    $score->setPlayedAt(new DateTimeImmutable());
    $entityManager->persist($score);
    $entityManager->flush();
}

fwrite(STDOUT, json_encode([
    'songId' => $song->getId(),
    'songName' => $song->getName(),
    'beatmap' => $difficulty->getWanadevHash(),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES).PHP_EOL);

$kernel->shutdown();
