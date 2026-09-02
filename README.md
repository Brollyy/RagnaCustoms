[![Symfony](https://github.com/Mouhaha-editions/RagnaCustoms/actions/workflows/symfony.yml/badge.svg?branch=master)](https://github.com/Mouhaha-editions/RagnaCustoms/actions/workflows/symfony.yml)

## Custom-song vote API

The game-configured custom leaderboard endpoint has the form
`/wanapi/score/{apiKey}`. Clients may derive its vote child endpoint by
appending `/vote`; they should not require a second independently configured
server URL.

- `GET /wanapi/score/{apiKey}/vote?beatmap={wanadevHash}` returns the current
  user's vote and the aggregate counts.
- `PUT /wanapi/score/{apiKey}/vote` accepts JSON containing `beatmap` and a
  `direction` of `"up"`, `"down"`, or `null`.

PUT sets the desired state and is idempotent. The API returns `401` for an
unknown key, `404` for an unknown chart, and `409` when the song is unavailable
or the key owner has not submitted a score for that song.

`tests/e2e/vote_api_local.php` exercises the complete state sequence and
refuses to connect to anything except a loopback host.
