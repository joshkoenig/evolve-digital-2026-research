<?php

/**
 * @file
 * Local-only: expose every available Tool API tool over the site's MCP server.
 *
 * Enables MCP Server, the Tool API bridge, Tool Belt, Canvas Tools and (when
 * present as a dev dependency) Tool Shop, then creates one bridge mapping per
 * tool so an MCP client can do content modelling, content, Canvas page
 * building and raw configuration work.
 *
 * This includes write and delete tools with no OAuth in front of them, so it
 * is meant for a throwaway local database and refuses to run on Pantheon.
 * Run it through `ddev site-setup`, or directly:
 *
 *   ddev drush php:script ../scripts/mcp-local-setup.php
 *
 * Safe to re-run; it only adds mappings that are missing.
 */

if (getenv('PANTHEON_ENVIRONMENT')) {
  throw new \RuntimeException('mcp-local-setup.php is for local development only and will not run on Pantheon. Use agent-access-setup.php there.');
}

$wanted = [
  'mcp_server',
  'mcp_server_tool_bridge',
  'tool',
  'tool_explorer',
  'tool_belt',
  'tool_belt_content',
  'tool_belt_entity',
  'tool_belt_content_moderation',
  'tool_belt_image_style',
  'tool_belt_system',
  'tool_belt_user',
  'canvas_tools',
  // Dev dependency: raw config, module install, cache and router rebuilds.
  'tool_shop',
  'tool_shop_config',
  'tool_shop_module',
  'tool_shop_system',
];

$available = \Drupal::service('extension.list.module')->getList();
$handler = \Drupal::moduleHandler();
$install = [];
foreach ($wanted as $module) {
  if (!isset($available[$module])) {
    print "Skipped $module (not in the codebase)\n";
  }
  elseif (!$handler->moduleExists($module)) {
    $install[] = $module;
  }
}
if ($install) {
  \Drupal::service('module_installer')->install($install);
  print 'Enabled: ' . implode(', ', $install) . "\n";
}

// Module installation rebuilt the container; fetch services fresh.
$storage = \Drupal::entityTypeManager()->getStorage('mcp_tool_config');
$mapped = [];
foreach ($storage->loadMultiple() as $config) {
  $mapped[$config->get('tool_id')] = TRUE;
}

$created = [];
foreach (\Drupal::service('plugin.manager.tool')->getDefinitions() as $tool_id => $definition) {
  if (isset($mapped[$tool_id])) {
    continue;
  }
  // "tool_belt:entity_bundle_add" is exposed as "tool_api__entity_bundle_add".
  $id = preg_replace('/[^a-z0-9_]+/', '_', strtolower(substr(strrchr(':' . $tool_id, ':'), 1)));
  if ($storage->load($id)) {
    $id = preg_replace('/[^a-z0-9_]+/', '_', strtolower($tool_id));
  }
  $storage->create(['id' => $id, 'tool_id' => $tool_id, 'status' => TRUE])->save();
  $created[] = $id;
}

drupal_flush_all_caches();

print 'Tools newly exposed over MCP: ' . count($created) . "\n";
print 'Total tools exposed over MCP: ' . count($storage->loadMultiple()) . "\n";
if (!\Drupal\Core\Site\Settings::get('tool_shop_enabled') && isset($available['tool_shop'])) {
  print "Note: Tool Shop is installed but its settings.php gate is closed, so its tools will refuse to run.\n";
}
