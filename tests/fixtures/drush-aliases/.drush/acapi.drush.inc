<?php

/**
 * @file Acquia Cloud API commands
 */

/**
 * Implementation of hook_drush_command().
 */
function acapi_drush_command() {
  // All Acquia Cloud API commands accept a common set of options which are
  // centrally defined. Retrieve them with acapi_get_option().
  $options = acapi_common_options();

  //////////////////////////////////////////////////////////////////////
  // Acquia Update
  //////////////////////////////////////////////////////////////////////
  $items['acquia-update'] = array(
    'description' => 'Retrieve Drush aliases for all accessible Acquia Cloud sites.',
    'arguments' => array(
    ),
    'options' => $options,
  );

  // Most of the commands below use drush_acapi_ac_generic_callback, which
  // translates their 'method', 'resource', and 'arguments' properties into
  // the appropriate API call, executes it, and displays the result.

  //////////////////////////////////////////////////////////////////////
  // Login
  //////////////////////////////////////////////////////////////////////
  $items['ac-api-login'] = array(
    'description' => 'Store Acquia Cloud API credentials and configuration information.',
    'arguments' => array(
    ),
    'options' => $options + array(
      'reset' => array(
        'description' => 'Discard any existing stored values from a previous call. Without this option, new values will be merged with existing values.'
      ),
    ),
  );

  //////////////////////////////////////////////////////////////////////
  // Sites and environments
  //////////////////////////////////////////////////////////////////////
  $items['ac-site-list'] = array(
    'description' => 'List all sites available to the current user.',
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-site-info'] = array(
    'description' => 'Show information about a site.',
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-environment-list'] = array(
    'description' => "List a site's environments.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-environment-info'] = array(
    'description' => "Show information about a site environment.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-environment-install'] = array(
    'description' => "Install a Drupal distribution from a pre-selected list, URL, or Drush Makefile.",
    'arguments' => array(
      'type' => 'Type of distro source: distro_url or make_url.',
      'source' => 'A URL to a distro or URL to a Drush make file.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/install/:type',
    'params' => array('source'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-environment-livedev'] = array(
    'description' => "Configure Live Development on a site environment.",
    'arguments' => array(
      'action' => 'Action to take.  \'enable\' or \'disable\' live development.',
      'discard' => 'When action is \'disable\', set to 1 to discard uncommitted changes.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/livedev/:action',
    'params' => array('discard'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // Servers
  //////////////////////////////////////////////////////////////////////
  $items['ac-server-list'] = array(
    'description' => "List servers for a site and environment.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/servers',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-server-info'] = array(
    'description' => "Show information about a server.",
    'arguments' => array(
      'server' => 'Server name.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/servers/:server',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-server-php-procs'] = array(
    'description' => "Calculate the php max procs value based on possible memory limits and apc shm settings.",
    'arguments' => array(
      'server' => 'Server name.',
      'memory_limits' => 'Memory limits.',
      'apc_shm' => 'APC shm settings.'
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/servers/:server/php-procs',
    'params_array' => array(
      'memory_limits',
      'apc_shm',
    ),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // Databases
  //////////////////////////////////////////////////////////////////////
  $items['ac-database-list'] = array(
    'description' => "List a site's databases.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/dbs',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-info'] = array(
    'description' => "Show information about a site database.",
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/dbs/:db',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-add'] = array(
    'description' => 'Add a database.',
    'arguments' => array(
      'db' => 'The database.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/dbs',
    'body_fields' => array('db'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-delete'] = array(
    'description' => 'Delete a database.',
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/dbs/:db',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-list'] = array(
    'description' => "List a site environment's database instances.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/dbs',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-info'] = array(
    'description' => "Show information about a site environment's database instance.",
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-backup-list'] = array(
    'description' => "List a site environment's database instance backups.",
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-backup-info'] = array(
    'description' => "Show information about a site environment's database instance backup.",
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
      'backup' => 'Backup id.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups/:backup',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-backup-download'] = array(
    'description' => "Download a site environment database instance backup.",
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
      'backup' => 'Backup id.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups/:backup/download',
    'callback' => 'drush_acapi_ac_database_backup_download',
    'options' => array('result-file' =>
      array(
        'description' => 'Save to a file; specify the full path in which to store the backup. If not provided, the backup is sent the standard output.',
        'example-value' => '/path/to/file',
      )) + $options,
  );
  $items['ac-database-instance-backup'] = array(
    'description' => 'Create a database instance backup.',
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-backup-restore'] = array(
    'description' => 'Restore a database instance backup.',
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
      'backupid' => 'The backup id.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups/:backupid/restore',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-instance-backup-delete'] = array(
    'description' => 'Delete a database instance backup.',
    'arguments' => array(
      'db' => 'The environment-agnostic database name; this is the name shown on the Workflow page of the Cloud UI.',
      'backupid' => 'The backup id.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/envs/:env/dbs/:db/backups/:backupid',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // Tasks
  //////////////////////////////////////////////////////////////////////
  // API resource /sites/:realm::site/tasks returns an array of task records,
  // unlike other list resources which return a list of ids, so we need a
  // non-standard callback. We'd have to make N API calls to implement this the
  // way other data types work, which I think shows that the returning a list of
  // records is better.
  $items['ac-task-list'] = array(
    'description' => "List a site's tasks.",
    'arguments' => array(
    ),
    'options' => array(
      'state' => array(
        'description' => 'The task state to retrieve. If not specified, retrieve all tasks for the site.',
        'example-value' => 'done',
      ),
      'days' => array(
        'description' => 'The number of days worth of tasks to retrieve. If not specified, retrieve, at a maximum, 7 days worth of tasks.',
        'example-value' => '5',
      ),
      'limit' => array(
        'description' => 'The maximum number of tasks to retrieve. If not specified, retrieve a maximum of 50 tasks. The maximum value allowed is 1000.',
        'example-value' => '500'
      ),
    ) + $options,
  );

  $items['ac-task-info'] = array(
    'description' => "Show information about a site task.",
    'arguments' => array(
      'task' => 'The task id.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/tasks/:task',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // Workflow
  //////////////////////////////////////////////////////////////////////
  $items['ac-code-deploy'] = array(
    'description' => 'Deploy code from one site environment to another.',
    'arguments' => array(
      'target' => 'The target environment.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/code-deploy/:env/:target',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-code-path-deploy'] = array(
    'description' => 'Deploy a specific branch or tag in an environment.',
    'arguments' => array(
      'path' => 'The branch or tag to deploy.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/code-deploy',
    'params' => array('path'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-database-copy'] = array(
    'description' => 'Copy a database from one site environment to another.',
    'arguments' => array(
      'db' => 'The database.',
      'target' => 'The target environment.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/dbs/:db/db-copy/:env/:target',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-files-copy'] = array(
    'description' => 'Copy user-uploaded files from one site environment to another.',
    'arguments' => array(
      'target' => 'The target environment.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/files-copy/:env/:target',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-domain-move'] = array(
    'description' => 'Move a domain from one site environment to another.',
    'arguments' => array(
      'target' => 'The target environment.',
      'domains' => 'Comma separated list of domains, or * for all.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/domain-move/:env/:target',
    'body_fields_array' => array('domains'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // SSH keys
  //////////////////////////////////////////////////////////////////////
  $items['ac-sshkey-list'] = array(
    'description' => "List a site's SSH keys.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/sshkeys',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-sshkey-info'] = array(
    'description' => "Show information about a site SSH key.",
    'arguments' => array(
      'sshkeyid' => 'SSH key id.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/sshkeys/:sshkeyid',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-sshkey-add'] = array(
    'description' => 'Add an SSH key to a site.',
    'arguments' => array(
      'ssh_pub_key' => 'File containing the SSH public key.',
      'nickname' => 'The SSH key nickname.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/sshkeys',
    'params' => array('nickname'),
    'body_fields_path' => array('ssh_pub_key'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options + array(
      'shell_access' =>
        array('description' => 'Set shell access for this key. (true or false)',
          'param' => 'shell_access',),
      'vcs_access' =>
        array('description' => 'Set git access for this key. (true or false)',
          'param' => 'vcs_access',),
      'blacklist' =>
        array('description' => 'Array containing a list of environments to disallow access for this key',
          'param' => 'blacklist',),
    ),
  );
  $items['ac-sshkey-delete'] = array(
    'description' => 'Delete an SSH key from a site.',
    'arguments' => array(
      'sshkeyid' => 'The SSH key id to delete.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/sshkeys/:sshkeyid',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // SVN users
  //////////////////////////////////////////////////////////////////////
  $items['ac-svnuser-list'] = array(
    'description' => "List a site's SVN users.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/svnusers',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-svnuser-info'] = array(
    'description' => "Show information about a site SVN user.",
    'arguments' => array(
      'svnuserid' => 'SVN user id.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/svnusers/:svnuserid',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-svnuser-add'] = array(
    'description' => 'Add an SVN user to a site.',
    'arguments' => array(
      'username' => 'SVN username.',
      'password' => 'SVN password.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/svnusers/:username',
    'body_fields' => array('password'),
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-svnuser-delete'] = array(
    'description' => 'Delete an SVN user from a site.',
    'arguments' => array(
      'svnuserid' => 'The SVN user id to delete.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/svnusers/:svnuserid',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  //////////////////////////////////////////////////////////////////////
  // Domains
  //////////////////////////////////////////////////////////////////////
  $items['ac-domain-list'] = array(
    'description' => "List a site's domains.",
    'arguments' => array(
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/domains',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-domain-info'] = array(
    'description' => "Show information about a site domain.",
    'arguments' => array(
      'domain' => 'Domain name.',
    ),
    'method' => 'GET',
    'resource' => '/sites/:realm::site/envs/:env/domains/:domain',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-domain-add'] = array(
    'description' => "Add a domain name to an environment.",
    'arguments' => array(
      'domain' => 'Domain name.',
    ),
    'method' => 'POST',
    'resource' => '/sites/:realm::site/envs/:env/domains/:domain',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-domain-delete'] = array(
    'description' => "Delete a domain name from an environment.",
    'arguments' => array(
      'domain' => 'Domain name.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/envs/:env/domains/:domain',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );
  $items['ac-domain-purge'] = array(
    'description' => "Purge a domain from the Varnish cache.",
    'arguments' => array(
      'domain' => 'Domain name.',
    ),
    'method' => 'DELETE',
    'resource' => '/sites/:realm::site/envs/:env/domains/:domain/cache',
    'callback' => 'drush_acapi_ac_generic_callback',
    'options' => $options,
  );

  foreach ($items as $command => $item) {
    $items[$command]['orig_options'] = $item['options'];
    if (DRUSH_VERSION < 5) {
      // Make our command structure work with previous versions of drush while
      // still supporting the way we handle arguments, options, and defaults.
      $items[$command]['argument-description'] = $item['arguments'];
      foreach ($item['options'] as $option => $info) {
        $items[$command]['options'][$option] = $info['description'];
      }
    }
    else {
      $items[$command]['handle-remote-commands'] = TRUE;
    }

    $items[$command]['bootstrap'] = DRUSH_BOOTSTRAP_DRUSH;
    $items[$command]['required-arguments'] = TRUE;
  }

  return $items;
}

function acapi_drush_help($section) {
  switch ($section) {
    case 'meta:acapi:title':
      return dt('Acquia commands');
    case 'meta:acapi:summary':
      return dt('Acquia Cloud commands.');
    case 'drush:ac-api-login':
      $file = '$HOME/.acquia/cloudapi.conf';
      return dt("Store Acquia Cloud API credentials and endpoint information.

This command stores default email, key, and optionally endpoint values for future Acquia Cloud API commands in @file. File location can be altered with the ac-config option on all Acquia Cloud commands.", array('@file' => $file));
  }
}

/**
 * Write content to a file if the file does not already have that content.
 * Log a message when the file is updated.
 *
 * @param $path
 *   The full path to update.
 * @param $content
 *   The content to write.
 * @return
 *   TRUE if the file is updated, FALSE otherwise.
 */
function drush_acapi_update_file($path, $content) {
  $current = @file_get_contents($path);
  if ($current != $content) {
    file_put_contents($path, $content);
    drush_log(dt('Updated %path.', array('%path' => $path)), 'ok');
    return TRUE;
  }
  return FALSE;
}

/**
 * Update the Drush aliases for all accessible Cloud sites.
 */
function drush_acapi_acquia_update() {
  list($status, $all_aliases) = acapi_call('GET', '/me/drushrc', array(), array(), array(), array('display' => FALSE));
  if ($status == 200) {
    foreach ($all_aliases as $realm_site => $aliases) {
      list($realm, $site) = explode(':', $realm_site);
      $file = _drush_config_file('home.drush', "$site.aliases");
      $content = "<?php
// DO NOT MODIFY THIS FILE.
// This file was created by the drush acquia-update command. Changes will be
// lost the next time drush acquia-update runs.

";

      // $aliases is keyed by environment name, but the JSON data is not
      // guaranteed to arrive in a consistent order. Sort by env name to avoid
      // unnecessary rewrites.
      $aliases = (array)$aliases;
      ksort($aliases);
      $content .= implode($aliases, "\n");
      drush_acapi_update_file($file, $content);
    }
  }
}

/**
 * Login. Store default options for use by future calls. Preserve options from
 * previous logins unless they are replaced this time.
 */
function drush_acapi_ac_api_login() {
  $defaults = acapi_common_options();
  $hide_default = '<existing value>';

  // Preserve existing defaults by loading them unless the user said not to.
  $acapi_options = array();
  if (! drush_get_option('reset', FALSE)) {
    $acapi_options = acapi_load_options();
  }

  // Collect specified values for each option.
  foreach ($defaults as $k => $info) {
    // Get the value from the command line, if provided.
    $option = drush_get_option($k, NULL);

    if (!isset($option)) {
      // No value on cli. The default is the previous value, if any, or what
      // the option declaration specifies.
      $default = isset($acapi_options[$k]) ? $acapi_options[$k] : $info['default_value'];

      // Prompt for some options, and just use the default for the others.
      if (!empty($info['prompt'])) {
        // Hide the existing key, if there is one.
        if ($info['prompt'][3] && !empty($default)) {
          $info['prompt'][1] = $hide_default;
        }
        else {
          $info['prompt'][1] = $default;
        }
        $option = call_user_func_array('drush_prompt', $info['prompt']);
        if ($option == $hide_default) {
          $option = $default;
        }
      }
      else {
        $option = $default;
      }
    }

    // Only save non-default values so we can update the defaults without pain.
    if ($option == $info['default_value']) {
      unset($acapi_options[$k]);
    }
    else {
      $acapi_options[$k] = $option;
    }
  }

  // We can't store the default path to the config file in the config file.
  unset($acapi_options['ac-config']);

  acapi_save_options($acapi_options);
}

/**
 * Save acapi options to the config file.
 *
 * @param $options
 *   Hash of options to save.
 */
function acapi_save_options($options) {
  $file = acapi_get_option_file();
  $dir = dirname($file);
  $dt_args = array('@file' => $file, '@dir' => $dir);
  $verbose = drush_get_option('verbose', FALSE);
  $simulate = drush_get_option('simulate', FALSE);
  if ($verbose || $simulate) {
    drush_log(dt('Storing Acquia Cloud API defaults in @file.', $dt_args), 'ok');
  }
  if ($verbose) {
    drush_print($output);
  }
  if (! $simulate) {
    $output = json_encode($options) . "\n";
    if (!is_dir($dir)) {
      if (@mkdir($dir) === FALSE) {
        return drush_set_error('ACAPI_CANNOT_WRITE_LOGIN', dt('Cannot create Acquia Cloud API defaults directory @dir.', $dt_args));
      }
    }
    if (file_put_contents($file, $output) === FALSE) {
      return drush_set_error('ACAPI_CANNOT_WRITE_LOGIN', dt('Cannot write to Acquia Cloud API defaults file @file.', $dt_args));
    }
  }
}

/**
 * Get saved values for acapi common options from the ac-config file.
 *
 * @returns
 *   A hash of saved acapi option names and values.
 */
function acapi_load_options() {
  $ret = array();
  $file = acapi_get_option_file();
  $contents = @file_get_contents($file);
  if ($contents !== FALSE) {
    // Parse old-style Drush PHP config files, and save them in the new format.
    if (preg_match('@^<\?php@', $contents) && preg_match_all('@^\$options\[\'acapi\'\]\[\'(\w+)\'\]\s*=\s*\'(.*)\';$@m', $contents, $matches, PREG_SET_ORDER)) {
      foreach ($matches as $match) {
        $ret[$match[1]] = $match[2];
      }
      acapi_save_options($ret);
    }
    else {
      $data = @json_decode($contents, TRUE);
      if (is_array($data)) {
        $ret = $data;
      }
      else {
        drush_set_error('ACAPI_INVALID_CONF', dt('Acquia Cloud API config file @file is invalid. Run drush ac-api-login.', array('@file' => $file)));
      }
    }
  }
  return $ret;
}

/**
 * Return the path for the acapi option file, either --ac-config or
 * $HOME/.acquia/cloudapi.conf.
 */
function acapi_get_option_file() {
  $defaults = acapi_common_options();
  return drush_get_option('ac-config', $defaults['ac-config']['default_value']);
}

//////////////////////////////////////////////////////////////////////
// Custom callbacks
//////////////////////////////////////////////////////////////////////

/**
 * List a site's tasks. See the command definition for why this function is
 * different.
 */
function drush_acapi_ac_database_backup_download($db, $backupid) {
  $command = drush_get_context('command');
  list($site, $env) = acapi_get_site();
  $simulate = drush_get_option('simulate', FALSE);
  $result_file = drush_get_option('result-file', '');
  // Similar to drush_sql_build_dump_command(). If the user has set
  // $options['result-file'] = TRUE, then we will generate an SQL dump file in
  // an automatically-generated backup directory based on site and env values.
  if ($result_file === TRUE) {
    // User did not pass a specific value for --result-file. Make one.
    $backup = drush_include_engine('version_control', 'backup');
    $backup_dir = $backup->prepare_backup_dir($site . '.' . $env);
    if (empty($backup_dir)) {
      $backup_dir = "/tmp";
    }
    $result_file = $backup_dir . '/' . $db . '-' . $backupid .'.sql.gz';
  }
  if ($result_file == '') {
    $fp = STDOUT;
  }
  else {
    $fp = fopen($result_file, 'w');
    if ($fp == NULL) {
      return drush_set_error('ACAPI_ENOENT', dt('Cannot write to result file @result_file.', array('@name' => $result_file)));
    }
  }

  $api_args = acapi_get_site_args() + array(
    ':db' => $db,
    ':backup' => $backupid,
  );
  list($status, $result) = acapi_call(
    $command['method'],
    $command['resource'],
    $api_args,
    array(),
    array(),
    array('result_stream' => $fp, 'redirect' => 1, 'display' => FALSE)
  );
}

/**
 * List a site's tasks. See the command definition for why this function is
 * different.
 */
function drush_acapi_ac_task_list() {
  $api_args = acapi_get_site_args();
  $format = acapi_get_option('format');
  $state = drush_get_option('state', NULL);
  $days = drush_get_option('days', NULL);
  $limit = drush_get_option('limit', NULL);
  $params = array();
  if (isset($state)) {
    $params['state'] = $state;
  }
  if (isset($days)) {
    $params['days'] = $days;
  }
  if (isset($limit)) {
    $params['limit'] = $limit;
  }

  list($status, $result) = acapi_call(
    'GET',
    '/sites/:realm::site/tasks',
    $api_args,
    $params,
    array(),
    array('display' => !empty($format))
  );

  $simulate = drush_get_option('simulate', FALSE);
  if ($simulate) {
    return;
  }

  if (empty($format)) {
    $display = array();
    foreach ($result as $id => $task) {
      $display[$task->id] = $task->description;
    }
    drush_print_table(drush_key_value_to_array_table($display));
  }
}

//////////////////////////////////////////////////////////////////////
// Utility functions
//////////////////////////////////////////////////////////////////////

/**
 * Define common options for Acquia Cloud API commands, and their defaults.
 */
function acapi_common_options() {
  $options = array(
    'email' => array(
      'description' => 'Email address for your Acquia Network user account',
      'default_value' => '',
      'prompt' => array('Email', NULL, TRUE, FALSE),
      'example-value' => 'example@acquia.com',
    ),
    'key' => array(
      'description' => 'Private Cloud API key for your Acquia Network user account',
      'default_value' => '',
      'prompt' => array('Key', NULL, TRUE, TRUE),
      'example-value' => 'apikey',
    ),
    'acapi-conf-path' => array(
      'description' => 'Acquia Cloud API config files location. If not specified config will be loaded from $HOME/.drush',
      'default_value' => '',
      'example-value' => '/home/user/acapi-site-configs',
    ),
    'ac-config' => array(
      'description' => 'Acquia Cloud API user config file location. If not specified config will be loaded from $HOME',
      'default_value' => drush_server_home() . '/.acquia/cloudapi.conf',
      'example-value' => drush_server_home() . '/.acquia/cloudapi-site-specific.conf',
    ),
    'endpoint' => array(
      'description' => 'Acquia Cloud API endpoint URL.',
      'default_value' => 'https://cloudapi.acquia.com/v1',
      'prompt' => array('Endpoint URL', NULL, TRUE, FALSE),
      'example-value' => 'https://cloudapi.acquia.com/v1',
    ),
    'cainfo' => array(
      'description' => 'Path to a file containing the SSL certificates needed to verify the ac-api-endpoint.',
      'default_value' => dirname(__FILE__) . '/cloudapi.acquia.com.pem',
      'example-value' => 'cloudapi.acquia.com.pem',
    ),
    'format' => array(
      'description' => 'Format to output the object. Use "print_r" for print_r, "export" for var_export, and "json" for JSON. If not provided, the output is printed in a human-readable format.',
      'default_value' => '',
      'example-value' => 'json',
    ),
  );
  return $options;
}

/**
 * Retrieve an Acquia Cloud API option, in priority order:
 *
 * - command line
 * - ac-config file ($HOME/.acquia/cloudapi.conf by default)
 * - per-site acapi file ($HOME/.drush/<site>.acapi.drushrc.php)
 * - default from acapi_common_options()
 *
 * @param $name
 *   An ac-api option name.
 * @return
 *   The option value, or NULL.
 */
function acapi_get_option($name) {
  // Make sure $name is an acapi option.
  $options = acapi_common_options();
  if (!isset($options[$name])) {
    return drush_set_error('ACAPI_UNKNOWN_OPTION', dt('Unknown ac-api option @name.', array('@name' => $name)));
  }

  // If the user specified --$name=<value> on the command line, return <value>.
  $value = drush_get_option($name, NULL);
  if (isset($value)) {
    return $value;
  }

  // If the ac-config file sets $name, return the value.
  $values = acapi_load_options($name);
  if (isset($values[$name])) {
    return $values[$name];
  }

  // If $name has a default value, return it.
  if (!empty($options[$name]['default_value'])) {
    return $options[$name]['default_value'];
  }

  // No specified value, no default, return NULL.
  return;
}

/**
 * A generic callback for API commands. The command must have:
 *
 * 'method': $method for acapi_call().
 * 'resource': $resource for acapi_call(). API resource argument names can
 * include any argument name from the command's arguments in addition to :site
 * and :env which are taken from the site alias.
 *
 * The command calls acapi_call() with arguments for the specified method,
 * resource, and arguments, calling the API and displaying the results.
 *
 * @return NULL
 *   This function always returns NULL to avoid invalid JSON.
 */
function drush_acapi_ac_generic_callback() {
  $command = drush_get_context('command');
  $api_args = preg_match('@:site@', $command['resource']) ? acapi_get_site_args() : array();
  $params = array();
  $body = array();

  if (isset($command['default_params'])) {
    $params += $command['default_params'];
  }

  foreach ($command['argument-description'] as $k => $desc) {
    if (isset($command['params']) && array_search($k, $command['params']) !== FALSE) {
      $params[$k] = array_shift($command['arguments']);
    }
    elseif (isset($command['params_array']) && array_search($k, $command['params_array']) !== FALSE) {
      $params[$k] = explode(',', array_shift($command['arguments']));
    }
    elseif (isset($command['body_fields']) && array_search($k, $command['body_fields']) !== FALSE) {
      $body[$k] = array_shift($command['arguments']);
    }
    elseif (isset($command['body_fields_array']) && array_search($k, $command['body_fields_array']) !== FALSE) {
      $body[$k] = explode(',', array_shift($command['arguments']));
    }
    elseif (isset($command['body_fields_path']) && array_search($k, $command['body_fields_path']) !== FALSE) {
      $path = array_shift($command['arguments']);
      $body[$k] = file_get_contents($path);
      if ($body[$k] === FALSE) {
        drush_set_error('ACAPI_ENOENT', dt('Cannot read @arg path @path.', array('@arg' => $k, '@path' => $path)));
        return;
      }
    }
    else {
      $api_args[":$k"] = array_shift($command['arguments']);
    }
  }

  foreach ($command['orig_options'] as $option => $info) {
    if (!empty($info['param'])) {
      if (drush_get_option($option, FALSE)) {
        $params[$info['param']] = $info['value'];
      }
    }
  }

  // acapi_call() will print the results, so returning here would result in
  // invalid JSON.
  acapi_call($command['method'], $command['resource'], $api_args, $params, $body);
}

/**
 * Return the Acquia Cloud site information specified via the site
 * alias.
 *
 * @param $site_required (TRUE)
 *   Set an error if site alias options are not found.
 * @return
 *   An array of three elements, site name, environment and realm unless the
 *   alias file pre-dates the addition of realm.
 */
function acapi_get_site($site_required = TRUE) {
  return array_values(acapi_get_site_args($site_required));
}

/**
 * Get arguments aboud the Acquia Cloud site ready to be used for replacement
 * in a URI.
 *
 * @param $site_required (TRUE)
 *   Set an error if site alias options are not found.
 * @return array
 *   An associative array containing :site, :env and :realm or an
 *   empty array if site alias option not found.
 */
function acapi_get_site_args($site_required = TRUE) {
  $params = array(
    ':site' => drush_get_option('ac-site'),
    ':env' => drush_get_option('ac-env'),
    ':realm' => drush_get_option('ac-realm'),
  );

  $missing = array_intersect($params, array(NULL));
  if ($missing) {
    if ($site_required) {
      $missing = str_replace(':', 'ac-', implode(', ', array_keys($missing)));
      $error = dt(
        'Alias file is missing Acquia Cloud information: !missing. Be sure to specify a complete Acquia Cloud alias name, such as @mysite.dev.',
        array('!missing' => $missing)
      );
      drush_set_error('ACAPI_SITE_REQUIRED', $error);
    }
    return array();
  }

  return $params;
}

/**
 * Call an Acquia Cloud API resource.
 *
 * @param $method
 *   The HTTP method; e.g. GET.
 * @param $resource
 *   The API function to call; e.g. /sites/:realm::site.
 * @param $args = array()
 *   An array of argument values for the resource; e.g: array(':site' =>
 *   'mysite').
 * @params $params = array()
 *   An array of query parameters to append to the URL.
 * @params $body = array()
 *   An array of parameters to include in the POST body in JSON format.
 * @params $options = array()
 *   An array of options:
 *   - display (TRUE): whether to output the result to stdout
 *   - result_stream: open stream to which to write the response body
 *   - redirect: the maximum number of redirects to allow
 */
function acapi_call($method, $resource, $args, $params = array(), $body = array(), $options = array()) {
  $default_options = array(
    'display' => TRUE,
  );
  $options = array_merge($default_options, $options);

  $debug = drush_get_option('debug', FALSE);
  $verbose = drush_get_option('verbose', FALSE);
  $simulate = drush_get_option('simulate', FALSE);
  $format = acapi_get_option('format');

  // Build the API call URL.
  $url = acapi_get_option('endpoint');
  $url .= acapi_dt($resource, $args);
  $url .= '.json';

  foreach ($params as $k => $v) {
    if (is_array($v)) {
      unset($params[$k]);
      foreach ($v as $key => $val) {
        $params["$k-$key"] = "$k%5B%5D=" . urlencode($val);
      }
    }
    else {
      $params[$k] = "$k=" . urlencode($v);
    }
  }

  $url .= '?' . implode('&', $params);

  $creds = acapi_get_creds();
  if (!$creds) {
    return FALSE;
  }

  // Build the body.
  $json_body = json_encode($body);

  $display = "curl -X $method '$url'";
  if ($debug) {
    $display .= " ($creds)";
  }
  if ($debug || $verbose || $simulate) {
    drush_print($display, 0, STDERR);
    if (!empty($body)) {
      drush_print("  $json_body", 0, STDERR);
    }
  }

  if ($simulate) {
    return;
  }

  $headers = array();
  $ch = curl_init($url);
  // Basic request settings
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
  curl_setopt($ch, CURLOPT_USERAGENT, basename(__FILE__));
  if (!empty($options['result_stream'])) {
    curl_setopt($ch, CURLOPT_FILE, $options['result_stream']);
  }
  else {
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
  }
  // User authentication
  curl_setopt($ch, CURLOPT_HTTPAUTH, TRUE);
  curl_setopt($ch, CURLOPT_USERPWD, $creds);
  // SSL
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, preg_match('@^https:@', acapi_get_option('endpoint')));
  curl_setopt($ch, CURLOPT_CAINFO, acapi_get_option('cainfo'));
  // Redirects
  if (!empty($options['redirect'])) {
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($ch, CURLOPT_MAXREDIRS, $options['redirect']+1);
  }
  /* Body
     We need to set a Content-Length header even on empty POST requests, or the webserver
     will throw a 411 Length Required.
  */

  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_body);
  $headers[] = 'Content-Type: application/json;charset=utf-8';
  $headers[] = 'Content-Length: ' . strlen($json_body);
  // Headers
  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }
  // Debugging
  curl_setopt($ch, CURLOPT_VERBOSE, $debug);
  // Go
  $content = curl_exec($ch);
  if (curl_errno($ch) > 0) {
    return drush_set_error('ACAPI_CURL_ERROR', dt('Error accessing @url: @err', array('@url' => $url, '@err' => curl_error($ch))));
  }

  $result = json_decode($content);
  $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if (!empty($format)) {
    drush_print(drush_format($result, NULL, $format));
  }
  else if ($options['display']) {
    if (is_array($result)) {
      foreach ($result as $item) {
        if (! is_scalar($item)) {
          drush_print_table(drush_key_value_to_array_table(acapi_convert_values($item)));
        }
        else {
          drush_print($item);
        }
      }
    }
    else {
      if ($method == 'POST') {
        // All POST actions return a task. Display something helpful.
        drush_log(dt('Task @taskid started.', array('@taskid' => $result->id)), 'ok');
      }
      else {
        drush_print_table(drush_key_value_to_array_table(acapi_convert_values($result)));
      }
    }
  }

  if ($status != 200) {
    return drush_set_error('ACAPI_HTTP_STATUS_' . $status, dt('API status code @status', array('@status' => $status)));
  }

  return array($status, $result);
}

/**
 * Return Acquia Cloud API credentials as username:password, or log an error
 * if they are unavailable.
 */
function acapi_get_creds() {
  $user = acapi_get_option('email');
  $pass = acapi_get_option('key');
  if (empty($user) || empty($pass)) {
    return drush_set_error('ACAPI_CREDS_MISSING', dt('Email and api key required; specify --email/--key or run drush ac-api-login'));
  }
  return "$user:$pass";
}

/**
 * Convert NULL, array and object values to appropriate string representations
 * so they are printed correctly.
 */
function acapi_convert_values($arr) {
  foreach ($arr as $k => $v) {
    if (!isset($v)) {
      $arr->{$k} = '';
    }
    elseif (is_array($v) || is_object($v)) {
      $arr->{$k} = '...';
    }
  }
  return (array) $arr;
}

/**
 * dt() wrapper that URL-encodes all substituted parameters that begin with
 * a colon (':').
 */
function acapi_dt($string, $args = array()) {
  foreach ($args as $k => $v) {
    if ($k[0] == ':') {
      $args[$k] = urlencode($v);
    }
  }
  return dt($string, $args);
}
