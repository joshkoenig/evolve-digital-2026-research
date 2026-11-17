#!/usr/bin/env bash
#
# Starts this site's MCP server over stdio for a desktop MCP client, running
# inside the DDEV web container as the given Drupal account (default: admin).
#
# Point the client at this file's absolute path, for example:
#   { "mcpServers": { "drupal-evolve": { "command": "/path/to/repo/scripts/mcp-stdio.sh" } } }
#
# The DDEV project must be running (ddev start).

# Desktop apps launch with a minimal PATH; add the usual homes of ddev/docker.
export PATH="$PATH:/opt/homebrew/bin:/usr/local/bin:$HOME/.homebrew/bin:$HOME/.orbstack/bin"

cd "$(dirname "$0")/.." || exit 1
exec ddev exec -s web drush mcp:server "${1:-admin}"
