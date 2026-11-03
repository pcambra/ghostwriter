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

Requires Drupal 11.3+, PHP 8.3+, Drush 13, `drupal/mcp_server` 2.0 (beta)
and a private file path.

1. In `settings.php`: `$settings['file_private_path'] = '../private';`
2. Add this repository and require the module:

   ```
   composer config repositories.ghostwriter vcs https://github.com/pcambra/ghostwriter
   composer require 'drupal/mcp_server:^2.0@beta' 'pcambra/ghostwriter:dev-main'
   ```

3. `drush en ghostwriter` enables `mcp_server` too and downloads the data.

## Connect a client

Clients connect over STDIO through Drush, as a Drupal account (here `admin`).
With DDEV, from the project root:

```
claude mcp add -s project ghostwriter -- ddev drush mcp:server admin
```

Check it with the MCP Inspector:

```
npx @modelcontextprotocol/inspector --cli ddev drush mcp:server admin --method tools/list
```

The HTTP endpoint `/mcp` only accepts a browser session cookie. Node-based
clients also need `NODE_EXTRA_CA_CERTS="$(mkcert -CAROOT)/rootCA.pem"` to
trust DDEV's certificate.

A running `drush mcp:server` keeps Drupal's in-memory caches between
requests, so after a refresh or script import made elsewhere it can answer
from the old data until the client reconnects. The fix is proposed in
[mcp_server #3585961](https://git.drupalcode.org/project/mcp_server/-/work_items/3585961);
apply its patch or reconnect after a refresh.

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
ddev exec -- env SIMPLETEST_DB=mysql://db:db@db/db vendor/bin/phpunit -c web/core/phpunit.xml.dist web/modules/custom/ghostwriter/tests
```

## License

GPL-2.0-or-later, see [LICENSE.txt](LICENSE.txt). The official data files
belong to The Pandemonium Institute and are not covered by this license.
