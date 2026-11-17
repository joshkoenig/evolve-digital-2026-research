<?php

/**
 * @file
 * One-time Agent Access setup for a Pantheon environment.
 *
 * Applies the drupal/agent_access recipe, generates the OAuth key pair in the
 * environment's private files directory, points Simple OAuth at the keys, and
 * clears the saved registration endpoint so it is derived from each request.
 *
 * Run it once per environment (keys live in that environment's files):
 *
 *   terminus drush <site>.<env> -- php:script ../scripts/agent-access-setup.php
 *
 * Safe to re-run: existing keys are kept, never regenerated.
 *
 * It creates no roles, permissions, accounts, or OAuth clients. Whoever
 * connects an agent signs in with their own Drupal account, which needs the
 * "grant simple_oauth codes" and "access mcp server" permissions.
 */

use Drupal\Core\Recipe\Recipe;
use Drupal\Core\Recipe\RecipeRunner;
use Drupal\Core\Site\Settings;

// 1. Apply the recipe. Drush 13 has no recipe command, so use the core API.
$recipe = Recipe::createFromDirectory(DRUPAL_ROOT . '/../recipes/agent_access');
RecipeRunner::processRecipe($recipe);
print "Applied recipe: {$recipe->name}\n";

// 2. Generate the OAuth keys in private files. On Pantheon that directory is
// writable, per-environment, and blocked from web access by pantheon.yml.
$private = Settings::get('file_private_path');
if (!$private) {
  throw new \RuntimeException('No private file path is configured; refusing to write OAuth keys under the public web root.');
}
if (!str_starts_with($private, '/')) {
  $private = DRUPAL_ROOT . '/' . $private;
}
$dir = $private . '/oauth-keys';
if (!is_dir($dir) && !mkdir($dir, 0770, TRUE) && !is_dir($dir)) {
  throw new \RuntimeException("Could not create $dir");
}
if (is_file("$dir/private.key") && is_file("$dir/public.key")) {
  print "Kept existing OAuth keys in $dir\n";
}
else {
  \Drupal::service('simple_oauth.key.generator')->generateKeys($dir);
  print "Generated OAuth keys in $dir\n";
}

// 3. Point Simple OAuth at the keys.
\Drupal::configFactory()->getEditable('simple_oauth.settings')
  ->set('public_key', "$dir/public.key")
  ->set('private_key', "$dir/private.key")
  ->save();

// 4. Applying a recipe from the CLI can save a wrong hostname as the client
// registration endpoint. Clear it so Drupal derives it from each request.
\Drupal::configFactory()->getEditable('simple_oauth_server_metadata.settings')
  ->clear('registration_endpoint')
  ->save();

drupal_flush_all_caches();
print "Done. Check /.well-known/oauth-authorization-server, then add <site URL>/mcp to your agent as a remote MCP server.\n";
