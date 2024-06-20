<?php

/**
 * ---------------------------------------------------------------------
 *
 * GLPI - Gestionnaire Libre de Parc Informatique
 *
 * http://glpi-project.org
 *
 * @copyright 2015-2024 Teclib' and contributors.
 * @copyright 2003-2014 by the INDEPNET Development Team.
 * @licence   https://www.gnu.org/licenses/gpl-3.0.html
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * ---------------------------------------------------------------------
 */

if (!defined('GLPI_ROOT')) {
    define('GLPI_ROOT', dirname(__FILE__, 2));
}

// Check if dependencies are up to date
$needrun  = false;

// composer dependencies
$autoload = GLPI_ROOT . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    $needrun = true;
} else if (file_exists(GLPI_ROOT . '/composer.lock')) {
    if (!file_exists(GLPI_ROOT . '/.composer.hash')) {
       /* First time */
        $needrun = true;
    } else if (sha1_file(GLPI_ROOT . '/composer.lock') != file_get_contents(GLPI_ROOT . '/.composer.hash')) {
       /* update */
        $needrun = true;
    }
}

// node dependencies
if (!file_exists(GLPI_ROOT . '/public/lib')) {
    $needrun = true;
} else if (file_exists(GLPI_ROOT . '/package-lock.json')) {
    if (!file_exists(GLPI_ROOT . '/.package.hash')) {
       /* First time */
        $needrun = true;
    } else if (sha1_file(GLPI_ROOT . '/package-lock.json') != file_get_contents(GLPI_ROOT . '/.package.hash')) {
       /* update */
        $needrun = true;
    }
}

if ($needrun) {
    $deps_install_msg = 'Application dependencies are not up to date.' . PHP_EOL
      . 'Run "php bin/console dependencies install" in the glpi tree to fix this.' . PHP_EOL;
    if (isCommandLine()) {
        echo $deps_install_msg;
    } else {
        echo nl2br($deps_install_msg);
    }
    die(1);
}

// Check if locales are compiled.
$need_mo_compile = false;
$locales_files = scandir(GLPI_ROOT . '/locales');
$po_files = preg_grep('/\.po$/', $locales_files);
$mo_files = preg_grep('/\.mo$/', $locales_files);
if (count($mo_files) < count($po_files)) {
    $need_mo_compile = true;
} else if (file_exists(GLPI_ROOT . '/locales/glpi.pot')) {
   // Assume that `locales/glpi.pot` file only exists when installation mode is GIT
    foreach ($po_files as $po_file) {
        $po_file = GLPI_ROOT . '/locales/' . $po_file;
        $mo_file = preg_replace('/\.po$/', '.mo', $po_file);
        if (!file_exists($mo_file) || filemtime($mo_file) < filemtime($po_file)) {
            $need_mo_compile = true;
            break; // No need to scan the whole dir
        }
    }
}
if ($need_mo_compile) {
    $mo_compile_msg = 'Application locales have to be compiled.' . PHP_EOL
      . 'Run "php bin/console locales:compile" in the glpi tree to fix this.' . PHP_EOL;
    if (isCommandLine()) {
        echo $mo_compile_msg;
    } else {
        echo nl2br($mo_compile_msg);
    }
    die(1);
}

require_once $autoload;

// Check if web root is configured correctly
if (!isCommandLine()) {
    $included_files = array_filter(
        get_included_files(),
        function (string $included_file) {
            // prevent `tests/router.php` to be considered as initial script
            return realpath($included_file) !== realpath(sprintf('%s/tests/router.php', GLPI_ROOT));
        }
    );

    $initial_script = array_shift($included_files) ?? __FILE__;

    // If `auto_prepend_file` configuration is used, ignore first included files
    // as long as they are not located inside GLPI directory tree.
    $prepended_file = ini_get('auto_prepend_file');
    if ($prepended_file !== '' && $prepended_file !== 'none') {
        $prepended_file = stream_resolve_include_path($prepended_file);
        while (
            $initial_script !== null
            && !str_starts_with(
                realpath($initial_script) ?: '',
                realpath(GLPI_ROOT)
            )
        ) {
            $initial_script = array_shift($included_files);
        }
    }

    if (realpath($initial_script) !== realpath(sprintf('%s/public/index.php', GLPI_ROOT))) {
        echo sprintf(
            'Web server root directory configuration is incorrect, it should be "%s". See installation documentation for more details.' . PHP_EOL,
            realpath(sprintf('%s/public', GLPI_ROOT))
        );
        exit(1);
    }
}

(function () {
    // Define GLPI_* constants that can be customized by admin.
    //
    // Use a self-invoking anonymous function to:
    // - prevent any global variables/functions definition from `local_define.php` and `downstream.php` files;
    // - prevent any global variables definition from current function logic.

    $constants = [
        'default' => [
            // GLPI environment
            'GLPI_ENVIRONMENT_TYPE' => 'production',

            // Constants related to system paths
            'GLPI_CONFIG_DIR'      => GLPI_ROOT . '/config', // Path for configuration files (db, security key, ...)
            'GLPI_VAR_DIR'         => GLPI_ROOT . '/files',  // Path for all files
            'GLPI_MARKETPLACE_DIR' => GLPI_ROOT . '/marketplace', // Path for marketplace plugins
            'GLPI_DOC_DIR'         => '{GLPI_VAR_DIR}', // Path for documents storage
            'GLPI_CACHE_DIR'       => '{GLPI_VAR_DIR}/_cache', // Path for cache
            'GLPI_CRON_DIR'        => '{GLPI_VAR_DIR}/_cron', // Path for cron storage
            'GLPI_DUMP_DIR'        => '{GLPI_VAR_DIR}/_dumps', // Path for backup dump
            'GLPI_GRAPH_DIR'       => '{GLPI_VAR_DIR}/_graphs', // Path for graph storage
            'GLPI_LOCAL_I18N_DIR'  => '{GLPI_VAR_DIR}/_locales', // Path for local i18n files
            'GLPI_LOCK_DIR'        => '{GLPI_VAR_DIR}/_lock', // Path for lock files storage (used by cron)
            'GLPI_LOG_DIR'         => '{GLPI_VAR_DIR}/_log', // Path for log storage
            'GLPI_PICTURE_DIR'     => '{GLPI_VAR_DIR}/_pictures', // Path for picture storage
            'GLPI_PLUGIN_DOC_DIR'  => '{GLPI_VAR_DIR}/_plugins', // Path for plugins documents storage
            'GLPI_RSS_DIR'         => '{GLPI_VAR_DIR}/_rss', // Path for rss storage
            'GLPI_SESSION_DIR'     => '{GLPI_VAR_DIR}/_sessions', // Path for sessions storage
            'GLPI_TMP_DIR'         => '{GLPI_VAR_DIR}/_tmp', // Path for temp storage
            'GLPI_UPLOAD_DIR'      => '{GLPI_VAR_DIR}/_uploads', // Path for upload storage
            "GLPI_INVENTORY_DIR"   => '{GLPI_VAR_DIR}/_inventories', //Path for inventories
            'GLPI_THEMES_DIR'      => '{GLPI_VAR_DIR}/_themes', // Path for custom themes storage

            // Where to load plugins.
            // Order in this array is important (priority to first found).
            'PLUGINS_DIRECTORIES'  => [
                '{GLPI_MARKETPLACE_DIR}',
                GLPI_ROOT . '/plugins',
            ],

            // Security constants
            'GLPI_ALLOW_IFRAME_IN_RICH_TEXT' => false,
            'GLPI_SERVERSIDE_URL_ALLOWLIST'  => [
                // allowlist (regex format) of URL that can be fetched from server side (used for RSS feeds and external calendars, among others)
                // URL will be considered as safe as long as it matches at least one entry of the allowlist
                '/^(https?|feed):\/\/[^@:]+(\/.*)?$/', // only accept http/https/feed protocols, and reject presence of @ (username) and : (protocol) in host part of URL
            ],

            // Constants related to GLPI Project / GLPI Network external services
            'GLPI_TELEMETRY_URI'                => 'https://telemetry.glpi-project.org', // Telemetry project URL
            'GLPI_INSTALL_MODE'                 => is_dir(GLPI_ROOT . '/.git') ? 'GIT' : 'TARBALL', // Install mode for telemetry
            'GLPI_NETWORK_MAIL'                 => 'glpi@teclib.com',
            'GLPI_NETWORK_SERVICES'             => 'https://services.glpi-network.com', // GLPI Network services project URL
            'GLPI_NETWORK_REGISTRATION_API_URL' => '{GLPI_NETWORK_SERVICES}/api/registration/',
            'GLPI_MARKETPLACE_ENABLE'           => 3, // 0 = Completely disabled, 1 = CLI only, 2 = Web only, 3 = CLI and Web
            'GLPI_MARKETPLACE_PLUGINS_API_URI'  => '{GLPI_NETWORK_SERVICES}/api/marketplace/',
            'GLPI_MARKETPLACE_PRERELEASES'      => preg_match('/-(dev|alpha\d*|beta\d*|rc\d*)$/', GLPI_VERSION) === 1, // allow marketplace to expose unstable plugins versions
            'GLPI_MARKETPLACE_ALLOW_OVERRIDE'   => true, // allow marketplace to override a plugin found outside GLPI_MARKETPLACE_DIR
            'GLPI_MARKETPLACE_MANUAL_DOWNLOADS' => true, // propose manual download link of plugins which cannot be installed/updated by marketplace
            'GLPI_USER_AGENT_EXTRA_COMMENTS'    => '', // Extra comment to add to GLPI User-Agent
            'GLPI_DOCUMENTATION_ROOT_URL'       => 'https://links.glpi-project.org', // Official documentations root URL

            // SQL compatibility
            'GLPI_DISABLE_ONLY_FULL_GROUP_BY_SQL_MODE' => '1', // '1' to disable ONLY_FULL_GROUP_BY 'sql_mode'

            // Other constants
            'GLPI_AJAX_DASHBOARD'         => '1', // 1 for "multi ajax mode" 0 for "single ajax mode" (see Glpi\Dashboard\Grid::getCards)
            'GLPI_CALDAV_IMPORT_STATE'    => 0, // external events created from a caldav client will take this state by default (0 = Planning::INFO)
            'GLPI_DEMO_MODE'              => '0',
            'GLPI_CENTRAL_WARNINGS'       => '1', // display (1), or not (0), warnings on GLPI Central page
            'GLPI_TEXT_MAXSIZE'           => '4000' // character threshold for displaying read more button
        ],
        'testing' => [
            'GLPI_CONFIG_DIR'      => GLPI_ROOT . '/tests/config',
            'GLPI_VAR_DIR'         => GLPI_ROOT . '/tests/files',
            'PLUGINS_DIRECTORIES'  => [
                GLPI_ROOT . '/plugins',
                GLPI_ROOT . '/tests/fixtures/plugins',
            ],
        ],
    ];

    $constants_names = array_keys($constants['default']);

    // Define constants values based on server env variables (i.e. defined using apache SetEnv directive)
    foreach ($constants_names as $name) {
        if (!defined($name) && ($value = getenv($name)) !== false) {
            define($name, $value);
        }
    }

    // Define constants values from local configuration file
    if (file_exists(GLPI_ROOT . '/config/local_define.php') && !defined('TU_USER')) {
        require_once GLPI_ROOT . '/config/local_define.php';
    }

    // Define constants values from downstream distribution file
    if (file_exists(GLPI_ROOT . '/inc/downstream.php')) {
        include_once(GLPI_ROOT . '/inc/downstream.php');
    }

    // Check custom values
    $allowed_envs = ['production', 'staging', 'testing', 'development'];
    if (defined('GLPI_ENVIRONMENT_TYPE') && !in_array(GLPI_ENVIRONMENT_TYPE, $allowed_envs)) {
        throw new \UnexpectedValueException(
            sprintf(
                'Invalid GLPI_ENVIRONMENT_TYPE constant value `%s`. Allowed values are: `%s`',
                GLPI_ENVIRONMENT_TYPE,
                implode('`, `', $allowed_envs)
            )
        );
    }
    if (defined('PLUGINS_DIRECTORIES') && !is_array(PLUGINS_DIRECTORIES)) {
        throw new \Exception('PLUGINS_DIRECTORIES constant value must be an array');
    }

    // Configure environment type if not defined by user.
    if (!defined('GLPI_ENVIRONMENT_TYPE')) {
        define('GLPI_ENVIRONMENT_TYPE', $constants['default']['GLPI_ENVIRONMENT_TYPE']);
    }

    // Define constants values from defaults
    // 1. First, define constants that does not inherit from another one.
    // 2. Second, define constants that inherits from another one.
    // This logic is quiet simple and is not made to handle chain inheritance.
    $inherit_pattern = '/\{(?<name>GLPI_[\w]+)\}/';
    foreach ($constants_names as $name) {
        $value = $constants[GLPI_ENVIRONMENT_TYPE][$name] ?? $constants['default'][$name];
        if (!defined($name) && (!is_string($value) || !preg_match($inherit_pattern, $value))) {
            if (
                (!is_string($value) && !is_array($value))
                || (is_string($value) && !preg_match($inherit_pattern, $value))
                || (is_array($value) && count(preg_grep($inherit_pattern, $value)) === 0)
            ) {
                define($name, $value);
            }
        }
    }
    foreach ($constants_names as $name) {
        if (!defined($name)) {
            // Replace {GLPI_*} by value of corresponding constant
            $value = preg_replace_callback(
                '/\{(?<name>GLPI_[\w]+)\}/',
                function ($matches) {
                    return defined($matches['name']) ? constant($matches['name']) : '';
                },
                $constants[GLPI_ENVIRONMENT_TYPE][$name] ?? $constants['default'][$name]
            );

            define($name, $value);
        }
    }

    // Try to create sub directories of `GLPI_VAR_DIR`, if they are not existing.
    // Silently fail, as handling errors is not really possible here.
    foreach ($constants_names as $name) {
        if (preg_match('/^GLPI_[\w]+_DIR$/', $name) !== 1) {
            continue;
        }
        $value = constant($name);
        if (
            preg_match('/^GLPI_[\w]+_DIR$/', $name)
            && preg_match('/^' . preg_quote(GLPI_VAR_DIR, '/') . '\//', $value)
            && !is_dir($value)
        ) {
            @mkdir($value, recursive: true);
        }
    }
})();

define('GLPI_I18N_DIR', GLPI_ROOT . "/locales");

/**
 * @var array $PLUGIN_HOOKS
 * @var array $LANG
 */
global $PLUGIN_HOOKS,
    $LANG
;

// For plugins
$PLUGIN_HOOKS     = [];
$LANG             = [];
