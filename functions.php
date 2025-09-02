<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once get_template_directory() . '/src/functions/compatibility.php';

use EndrockTheme\Classes\StarterSite;
use Timber\Timber;

define('SB_VERSION', '1.0.0');
define('SB_THEME_DIR', get_template_directory(__FILE__));
define('SB_THEME_URL', get_template_directory_uri(__FILE__));

global $sections_builder_theme;

/**
 * Sets the directories (inside your theme) to find .twig files
 */
Timber::$dirname = array('views');
if(class_exists('Timber\Timber')){
  Timber::$locations = array(
      get_template_directory() . '/sections',
      get_template_directory() . '/snippets',
  );
}

/**
 * By default, Timber does NOT autoescape values. Want to enable Twig's autoescape?
 * No prob! Just set this value to true
 */
Timber::$autoescape = false;

new StarterSite();

