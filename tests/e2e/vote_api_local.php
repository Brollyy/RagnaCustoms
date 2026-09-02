<?php

declare(strict_types=1);

/**
 * End-to-end smoke test for a locally seeded vote API.
 *
 * The URL guard prevents this script from targeting production. Override the
 * defaults with VOTE_API_BASE, VOTE_API_KEY, and VOTE_BEATMAP when using
 * another disposable fixture.
 */

$baseUrl = rtrim(getenv('VOTE_API_BASE') ?: 'http://127.0.0.1:18080', '/');
$apiKey = getenv('VOTE_API_KEY') ?: 'local-vote-key';
$beatmap = getenv('VOTE_BEATMAP') ?: 'local-beatmap-hash';
$host = parse_url($baseUrl, PHP_URL_HOST);

if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Refusing to run the vote smoke test against a non-loopback host.');
}

/** @return array<string, mixed> */
function request(string $method, string $baseUrl, string $apiKey, string $beatmap, ?string $direction = null): array
{
    $url = sprintf('%s/wanapi/score/%s/vote', $baseUrl, rawurlencode($apiKey));
    if ($method === 'GET') {
        $url .= '?'.http_build_query(['beatmap' => $beatmap]);
    }

    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    if ($method === 'PUT') {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode([
            'beatmap' => $beatmap,
            'direction' => $direction,
        ], JSON_THROW_ON_ERROR));
    }

    $body = curl_exec($curl);
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    curl_close($curl);

    if ($body === false || $status !== 200) {
        throw new RuntimeException(sprintf('Local vote request failed with HTTP %d: %s', $status, $error));
    }

    return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
}

function assertState(array $response, ?string $vote, int $upvotes, int $downvotes): void
{
    $actual = [$response['currentVote'], $response['upvotes'], $response['downvotes']];
    $expected = [$vote, $upvotes, $downvotes];
    if ($actual !== $expected) {
        throw new RuntimeException(sprintf(
            'Unexpected vote state: expected %s, received %s.',
            json_encode($expected, JSON_THROW_ON_ERROR),
            json_encode($actual, JSON_THROW_ON_ERROR),
        ));
    }
}

assertState(request('GET', $baseUrl, $apiKey, $beatmap), null, 0, 0);
assertState(request('PUT', $baseUrl, $apiKey, $beatmap, 'up'), 'up', 1, 0);
assertState(request('PUT', $baseUrl, $apiKey, $beatmap, 'up'), 'up', 1, 0);
assertState(request('PUT', $baseUrl, $apiKey, $beatmap, 'down'), 'down', 0, 1);
assertState(request('PUT', $baseUrl, $apiKey, $beatmap, null), null, 0, 0);
assertState(request('GET', $baseUrl, $apiKey, $beatmap), null, 0, 0);

fwrite(STDOUT, "Local vote API smoke test passed.\n");
