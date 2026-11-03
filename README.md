# Ghostwriter

![Community Created Content](https://release.botc.app/resources/community/ccc-parchment.png)

Blood on the Clocktower Storyteller knowledge base served over MCP: ask an AI
client what a character does, who wakes when, and which jinxes are live on a
script.

Blood on the Clocktower is a trademark of Steven Medway and The Pandemonium
Institute. This project is community-created, non-commercial, and not
affiliated with or endorsed by The Pandemonium Institute. See the
[Community Created Content policy](https://bloodontheclocktower.com/pages/community-created-content-policy).

The tools return The Pandemonium Institute's official toolmaker data verbatim,
with its source. They never rule on character interactions: anything not
covered by a jinx is the Storyteller's call.

## What it provides

| Tool | Answers |
|---|---|
| `character` | Ability, team, edition, reminders, night text, jinxes |
| `edition` | Every character in an edition, by team |
| `search_characters` | Whole-word search over names, abilities, reminders |
| `night_order` | First or other night order for ids or a pasted script |
| `jinxes` | Official jinxes live between a set of characters |
| `setup` | Base team counts for a player count, plus the set's setup modifiers |
| `script_import` | Save a script JSON for reuse (needs `administer ghostwriter`) |
| `refresh_data` | Re-download the data (needs `administer ghostwriter`) |

`night_order`, `jinxes` and `setup` take `characters`, a pasted `script`, or
the `script_id` of a saved script.

Resources: `ghostwriter://character/{id}`, `ghostwriter://edition/{id}`,
`ghostwriter://script/{id}`, `ghostwriter://data/status`.

## Data

The official files (`roles.json`, `jinxes.json`, `nightsheet.json`) are
downloaded from release.botc.app on install and by `drush ghostwriter:sync`
into `private://ghostwriter/`. They are never part of this repository. A sync
either replaces all three validated files or keeps the previous ones.

## Install

This repository is the whole Drupal site: core 11.4, PHP 8.5, Drush 13,
`drupal/mcp_server` 2.0 (beta), `drupal/mcp_server_oauth` 1.0 (alpha), and
the module in `web/modules/custom/ghostwriter`. It runs on SQLite, locally and
in production, and needs [DDEV](https://ddev.com).

```
git clone https://github.com/pcambra/ghostwriter.git
cd ghostwriter
ddev start
ddev composer install
ddev restart
mkdir db keys
cat >> web/sites/default/settings.php <<'PHP'
$settings['file_private_path'] = '../private';
$settings['config_sync_directory'] = '../config/sync';
$databases['default']['default'] = [
  'database' => '../db/ghostwriter.sqlite',
  'prefix' => '',
  'driver' => 'sqlite',
  'namespace' => 'Drupal\\sqlite\\Driver\\Database\\sqlite',
  'autoload' => 'core/modules/sqlite/src/Driver/Database/sqlite/',
];
PHP
ddev drush site:install --existing-config --account-name=admin -y
ddev drush simple-oauth:generate-keys /var/www/html/keys
```

The site installs from the exported configuration in `config/sync` and
downloads the data. The database lives in `db/` and the OAuth signing keys in
`keys/`, both outside the web root and out of git.

Configuration changes are made on the local site, exported with
`ddev drush config:export`, and committed.

## Connect a client

Clients connect over STDIO through Drush, as a Drupal account (here `admin`).
The repository's `.mcp.json` already registers `ddev drush mcp:server admin`
as `ghostwriter` for Claude Code opened at the project root.

Check it with the MCP Inspector:

```
npx @modelcontextprotocol/inspector --cli ddev drush mcp:server admin --method tools/list
```

Over HTTP, `/mcp` needs a Drupal account. Connectors such as Claude's and
ChatGPT's register themselves and send the user to the site's login page
(OAuth 2.1 through `mcp_server_oauth` and Simple OAuth), so leave their client
ID and secret empty. Tokens carry the `mcp` scope, which limits them to
`access mcp server`, except for user 1: its tokens keep every permission on
every route, so never sign a connector in as user 1. Node-based clients also
need `NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"` to trust DDEV's
certificate.

Composer applies the fix proposed in
[mcp_server #3585961](https://git.drupalcode.org/project/mcp_server/-/work_items/3585961)
from `patches/mcp_server/`. Without it, a running `drush mcp:server` keeps
Drupal's in-memory caches between requests, so after a refresh or script
import made elsewhere it answers from the old data until the client
reconnects.

## Accounts

Visitors cannot register. An administrator creates each account, then sends
the person the one-time link the second command prints, where they set their
password:

```
drush user:create USERNAME --mail=ADDRESS
drush user:login --name=USERNAME --uri=https://SITE
```

`drush user:login` takes the account through `--name`, `--mail` or `--uid`:
its first argument is the path to land on, not a username. People log in with
their username, not their email address.

## Keep the data fresh

`drush ghostwriter:sync` (add `--force` to ignore ETags), or the
**Refresh data** button at Configuration > Web services > Ghostwriter data
(`/admin/config/services/ghostwriter`, needs `administer ghostwriter`). The
status report warns when the data is older than `stale_after_days`
(default 30) and links to that page.

## Scripts

`drush ghostwriter:script:import path/to/script.json` saves a script from the
official script tool as config (`ghostwriter.script.<id>`). The id defaults to
the `_meta` name; pass `--id` to choose it. Saving under an existing id
replaces that script. Every character must be an official id.

## Tests

Kernel tests use synthetic fixtures, never official data:

```
ddev exec -- env SIMPLETEST_DB=sqlite://localhost//tmp/test.sqlite vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ghostwriter/tests
```

## Deploy

A push to `main` runs `.github/workflows/deploy.yml`. It uploads
`composer.json`, `composer.lock`, `patches.lock.json`, `patches/`, `recipes/`,
`config/` and `web/modules/custom` to the Compute Engine VM over SSH, then
runs `composer install --no-dev` and `drush deploy` (database updates,
configuration import, cache rebuild) there. It needs the repository secrets
`SSH_PRIVATE_KEY` and `SSH_KNOWN_HOSTS`. The server's `settings.php` sets
`config_sync_directory` to `../config/sync`, and its signing keys are created
once on the server with `drush simple-oauth:generate-keys`.

## License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt). The official data files
belong to The Pandemonium Institute and are not covered by this license.
