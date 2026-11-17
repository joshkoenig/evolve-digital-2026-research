#!/usr/bin/env bash
#
# Starts this site's MCP server over stdio for a desktop MCP client, running
# inside the DDEV web container as the given Drupal account (default: admin).
#
# Point the client at this file's absolute path, for example:
#   { "mcpServers": { "drupal-evolve": { "command": "/path/to/repo/scripts/mcp-stdio.sh" } } }
#
# Before starting the server it brings the local site up to date, so a change
# pushed to the repository takes effect with a restart of the MCP server:
#
#   1. git pull --ff-only
#   2. composer install (or update, when the local lock file is stale), only
#      when composer.json changed
#   3. scripts/mcp-local-setup.php, only when the checkout changed
#
# Stdout belongs to the MCP protocol, so everything above logs to
# .ddev/mcp-sync.log instead. A start that has to run Composer can take a few
# minutes; if the client gives up waiting, restart the server once more.
#
# Set MCP_SKIP_SYNC=1 to start the server without syncing.

# Everything lives in functions and runs from the last line, so bash has read
# the whole file before git pull can replace it underneath us.

log() {
  printf '%s %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*" >>"$LOG"
}

# Runs a command with all of its output sent to the log, never to stdout.
quiet() {
  "$@" >>"$LOG" 2>&1 </dev/null
}

sync_site() {
  local lock="$ROOT/.ddev/mcp-sync.lock" stamp="$ROOT/.ddev/mcp-sync.stamp"
  local waited=0

  # Only one start syncs at a time; a client retry waits for the first.
  until mkdir "$lock" 2>/dev/null; do
    if [ "$waited" -ge 900 ]; then
      log "Sync lock still held after 15 minutes; taking it over."
      rm -rf "$lock"
    fi
    sleep 5
    waited=$((waited + 5))
  done
  trap 'rm -rf "$ROOT/.ddev/mcp-sync.lock"' EXIT

  if ! quiet ddev exec -s web true; then
    log "DDEV project is not running; starting it."
    quiet ddev start || log "ddev start failed."
  fi

  local before after
  before="$(git rev-parse HEAD 2>/dev/null)"
  if quiet git pull --ff-only; then
    after="$(git rev-parse HEAD 2>/dev/null)"
    if [ "$before" != "$after" ]; then
      log "Pulled $before -> $after."
      # Run the new version of this script rather than the one in memory.
      if [ -z "$MCP_SYNC_REEXEC" ]; then
        rm -rf "$lock"
        trap - EXIT
        MCP_SYNC_REEXEC=1 exec "$ROOT/scripts/mcp-stdio.sh" "$@"
      fi
    fi
  else
    log "git pull --ff-only failed; continuing with the current checkout."
  fi

  local head composer_hash state
  head="$(git rev-parse HEAD 2>/dev/null)"
  composer_hash="$(git hash-object composer.json 2>/dev/null)"
  state="$head $composer_hash"
  if [ -f "$stamp" ] && [ "$(cat "$stamp")" = "$state" ]; then
    rm -rf "$lock"
    trap - EXIT
    return 0
  fi

  local ok=1
  if [ ! -f "$stamp" ] || [ "$(cut -d' ' -f2 "$stamp")" != "$composer_hash" ]; then
    log "composer.json changed; installing dependencies."
    if ! quiet ddev exec -s web composer install --no-interaction; then
      log "composer install failed (stale lock file?); running composer update."
      quiet ddev exec -s web composer update --no-interaction || { log "composer update failed."; ok=0; }
    fi
  fi

  if ddev exec -s web drush status --field=bootstrap 2>>"$LOG" </dev/null | grep -q Successful; then
    log "Running scripts/mcp-local-setup.php."
    quiet ddev exec -s web drush php:script ../scripts/mcp-local-setup.php || { log "mcp-local-setup.php failed."; ok=0; }
  else
    log "Drupal is not installed yet; run 'ddev site-setup' once."
    ok=0
  fi

  if [ "$ok" = 1 ]; then
    printf '%s\n' "$state" >"$stamp"
    log "Sync complete at $head."
  else
    log "Sync finished with errors; it will be retried on the next start."
  fi
  rm -rf "$lock"
  trap - EXIT
}

main() {
  # Desktop apps launch with a minimal PATH; add the usual homes of ddev/docker.
  export PATH="$PATH:/opt/homebrew/bin:/usr/local/bin:$HOME/.homebrew/bin:$HOME/.orbstack/bin"

  ROOT="$(cd "$(dirname "$0")/.." && pwd)" || exit 1
  LOG="$ROOT/.ddev/mcp-sync.log"
  cd "$ROOT" || exit 1

  if [ -z "$MCP_SKIP_SYNC" ]; then
    sync_site "$@"
  fi

  exec ddev exec -s web drush mcp:server "${1:-admin}"
}

main "$@"
