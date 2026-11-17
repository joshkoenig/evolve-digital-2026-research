# Composer-enabled Drupal template

This is Pantheon's recommended starting point for forking new [Drupal](https://www.drupal.org/) upstreams
that work with the Platform's Integrated Composer build process. It is also the
Platform's standard Drupal 9 upstream.

Unlike with earlier Pantheon upstreams, files such as Drupal Core that you are
unlikely to adjust while building sites are not in the main branch of the 
repository. Instead, they are referenced as dependencies that are installed by
Composer.

For more information and detailed installation guides, please visit the
Integrated Composer Pantheon documentation: https://pantheon.io/docs/integrated-composer

## Contributing

Contributions are welcome in the form of GitHub pull requests. However, the
`pantheon-upstreams/drupal-composer-managed` repository is a mirror that does not
directly accept pull requests.

Instead, to propose a change, please fork [pantheon-systems/drupal-composer-managed](https://github.com/pantheon-systems/drupal-composer-managed)
and submit a PR to that repository.

## Local site building over MCP (DDEV)

A throwaway local copy of this site whose site-building tools are exposed to
an MCP client such as the Claude desktop app, with no OAuth in the way.

```bash
git clone https://github.com/joshkoenig/evolve-digital-2026-research.git
cd evolve-digital-2026-research
ddev start
ddev site-setup
```

`ddev site-setup` runs `composer install`, installs Drupal from the Convivial
Gov site template, then runs `scripts/mcp-local-setup.php`, which enables MCP
Server, the Tool API bridge, Tool Belt, Canvas Tools and Tool Shop and exposes
every tool they provide (77 at the time of writing): content types and fields,
content, moderation, image styles, Canvas pages and templates, raw
configuration and module install. It is safe to re-run.

Then register the stdio server with your MCP client, using this repo's
absolute path:

```json
{
  "mcpServers": {
    "drupal-evolve": {
      "command": "/absolute/path/to/evolve-digital-2026-research/scripts/mcp-stdio.sh"
    }
  }
}
```

The server runs as the Drupal `admin` account; pass another user name as the
first argument to change that. The DDEV project must be running.

Tool Shop (raw configuration, module install) is a dev dependency and only
runs where `settings.php` opens its gate, which this repo does under DDEV
only. `mcp-local-setup.php` refuses to run on Pantheon; use
`scripts/agent-access-setup.php` there.
