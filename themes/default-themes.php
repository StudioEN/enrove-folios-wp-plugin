<?php
namespace Groove\Themes;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Default_Themes
 *
 * Now a thin wrapper around Themes_Manager::get_all_themes().
 * Kept for backward compatibility with any code that still calls
 * $default->get_themes(), but internally the registry is the
 * single source of truth.
 *
 * @deprecated Use Themes_Manager::get_all_themes() directly.
 */
class Default_Themes
{

  public function get_themes()
  {
    return Themes_Manager::get_all_themes();
  }
}