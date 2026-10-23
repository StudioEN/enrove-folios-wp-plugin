<?php
namespace Enrove\Contents\FolioPage;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Block_Converter
 *
 * The page the import picker loads in a hidden iframe to turn classic-editor
 * HTML into serialized blocks. Core's raw handler (wp.blocks.rawHandler(), what
 * the editor's Convert to blocks runs) only works with the core blocks
 * registered, and those come from wp-block-library, a script of several
 * megabytes. Loading that on the folio and list screens would slow every visit
 * for the sake of one feature, so it loads here instead: in a bare document
 * that exists only when the picker asks for it (enrove-block-converter-client.js
 * creates the iframe on demand), and the admin page holding the picker never
 * carries it.
 *
 * The document prints only the converter's script and its dependencies and
 * reads no request data. The content to convert, and the attachments a gallery
 * needs, arrive by postMessage from the parent window, which both sides accept
 * from their own origin only.
 */
class Block_Converter
{
  /** The admin-post action that serves the iframe document. */
  const ACTION = 'enrove_block_converter';

  /** Handle of the script that runs in the admin page and owns the iframe. */
  const CLIENT_HANDLE = 'enrove-block-converter-client';

  /** Handle of the script that runs inside the iframe. */
  const FRAME_HANDLE = 'enrove-block-converter';

  public static function register()
  {
    add_action('admin_post_' . self::ACTION, array(static::class, 'render'));
  }

  /**
   * Where the iframe document is served. Unescaped: the caller escapes it for
   * the context it prints it in (the client script receives it as JSON).
   */
  public static function get_url()
  {
    return admin_url('admin-post.php?action=' . self::ACTION);
  }

  /**
   * Loads the parent-side script, with the iframe's address. It is small and
   * loads nothing itself until preload() or convert() is called.
   */
  public static function enqueue_client()
  {
    $path = static::asset_path('enrove-block-converter-client.js');
    wp_enqueue_script(
      self::CLIENT_HANDLE,
      ENROVE_ASSETS_URL . 'js/enrove-block-converter-client.js',
      array(),
      static::asset_version($path),
      true
    );
    wp_add_inline_script(
      self::CLIENT_HANDLE,
      'window.ENROVE_BLOCK_CONVERTER = ' . wp_json_encode(array('url' => self::get_url()), JSON_HEX_TAG | JSON_HEX_AMP) . ';',
      'before'
    );
  }

  /**
   * Prints the iframe document. Anyone who can edit posts may load it; it
   * holds no data of its own.
   */
  public static function render()
  {
    if (!current_user_can('edit_posts')) {
      wp_die(esc_html__('You are not allowed to do this.', 'enrove-folios'), '', array('response' => 403));
    }

    $path = static::asset_path('enrove-block-converter.js');
    wp_register_script(
      self::FRAME_HANDLE,
      ENROVE_ASSETS_URL . 'js/enrove-block-converter.js',
      array('wp-blocks', 'wp-block-library', 'wp-autop', 'wp-dom-ready'),
      static::asset_version($path),
      true
    );

    nocache_headers();
    header('Content-Type: text/html; charset=' . get_option('blog_charset'));
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="robots" content="noindex"><title>';
    echo esc_html__('Block converter', 'enrove-folios');
    echo '</title></head><body>';
    wp_print_scripts(array(self::FRAME_HANDLE));
    echo '</body></html>';
    exit;
  }

  private static function asset_path($file)
  {
    return ENROVE_ASSETS_PATH . 'js/' . $file;
  }

  private static function asset_version($path)
  {
    return file_exists($path) ? (string) filemtime($path) : ENROVE_VERSION;
  }
}
