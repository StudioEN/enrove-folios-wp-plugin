<?php
namespace Enrove\Pexels;

use Enrove\Pages\Settings;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Development only: the Pexels API key on Settings → Imagery, its save, remove
 * and connection test handlers, and what the Credits and Privacy panels say
 * about curating.
 *
 * The key serves bin/curate-pexels.php and nothing else. The WordPress.org
 * package leaves this file out with that script and the Key and Client classes
 * (.claude/scripts/package-plugin.sh), so a released plugin has no API key
 * screen and no code that reaches api.pexels.com; Settings finds no class and
 * shows none of this.
 */
class Key_Settings
{
  /** @var Settings */
  private $settings;

  /** Whether this copy of the plugin has what the key is for. */
  public static function is_available()
  {
    return is_readable(ENROVE_PATH . 'bin/curate-pexels.php')
      && class_exists(Key::class)
      && class_exists(Client::class);
  }

  public function __construct(Settings $settings)
  {
    $this->settings = $settings;

    add_action('admin_post_save_enrove_pexels_key', array($this, 'handle_save'));
    add_action('admin_post_delete_enrove_pexels_key', array($this, 'handle_delete'));
    add_action('admin_post_test_enrove_pexels_connection', array($this, 'handle_connection_test'));
  }

  private function get_source_label($source)
  {
    switch ($source) {
      case 'constant':
        return __('ENROVE_PEXELS_API_KEY in wp-config.php', 'enrove-folios');
      case 'env':
        return __('PEXELS_API_KEY environment variable', 'enrove-folios');
      case 'file':
        return __('.pexels-key file in the plugin folder', 'enrove-folios');
      case 'option':
        return __('This settings field', 'enrove-folios');
    }

    return __('Not configured', 'enrove-folios');
  }

  /**
   * Save the Pexels API key. An empty submission leaves the stored key alone.
   */
  public function handle_save()
  {
    check_admin_referer('enrove_save_pexels_key', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    if (Key::is_locked_by_constant()) {
      $this->settings->set_tab_notice(
        'warning',
        __('ENROVE_PEXELS_API_KEY is defined in wp-config.php, so the stored key was not changed.', 'enrove-folios'),
        __('The constant wins over anything saved here. Remove it from wp-config.php if you want to manage the key from this screen.', 'enrove-folios'),
        '#enrove-save-pexels-key'
      );
      $this->settings->redirect_to_imagery_tab();
    }

    $submitted = isset($_POST['pexels_api_key']) ? sanitize_text_field(wp_unslash($_POST['pexels_api_key'])) : '';

    if ($submitted === '') {
      $this->settings->set_tab_notice('info', __('No key entered — the stored key was left unchanged.', 'enrove-folios'));
      $this->settings->redirect_to_imagery_tab();
    }

    // update_option() cannot change the autoload flag of an existing option,
    // so delete first and re-add with autoload explicitly off.
    delete_option('enrove_pexels_api_key');
    add_option('enrove_pexels_api_key', $submitted, '', 'no');

    $this->settings->set_tab_notice('success', __('Pexels API key saved.', 'enrove-folios'));
    $this->settings->redirect_to_imagery_tab();
  }

  /**
   * Clear the stored Pexels API key.
   */
  public function handle_delete()
  {
    check_admin_referer('enrove_delete_pexels_key', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    delete_option('enrove_pexels_api_key');

    $this->settings->set_tab_notice('success', __('Stored Pexels API key removed.', 'enrove-folios'));
    $this->settings->redirect_to_imagery_tab();
  }

  /**
   * Probe the Pexels API. Only ever runs from an explicit button press —
   * never on a page load.
   */
  public function handle_connection_test()
  {
    check_admin_referer('enrove_test_pexels_connection', 'enrove_nonce');

    if (!current_user_can('manage_options')) {
      wp_die(esc_html__('You do not have permission to modify settings.', 'enrove-folios'));
    }

    $client = new Client();

    if (!$client->has_key()) {
      $this->settings->set_tab_notice(
        'error',
        __('No Pexels API key is configured.', 'enrove-folios'),
        __('Paste a key into the field above and save it, then test the connection again.', 'enrove-folios'),
        '#enrove-test-pexels-connection'
      );
      $this->settings->redirect_to_imagery_tab();
    }

    $result = $client->verify();

    if (is_wp_error($result)) {
      $this->settings->set_tab_notice(
        'error',
        sprintf(
          /* translators: %s: error message returned by the Pexels API. */
          __('Pexels rejected the request: %s', 'enrove-folios'),
          $result->get_error_message()
        ),
        __('Check the key is still active in your Pexels account and paste it again. A key created moments ago can take a few minutes to work.', 'enrove-folios'),
        '#enrove-test-pexels-connection'
      );
      $this->settings->redirect_to_imagery_tab();
    }

    $remaining = is_array($result) && isset($result['remaining']) ? (int) $result['remaining'] : 0;
    $limit = is_array($result) && isset($result['limit']) ? (int) $result['limit'] : 0;

    $this->settings->set_tab_notice('success', sprintf(
      /* translators: 1: remaining requests, 2: hourly request limit. */
      __('Connected to Pexels. %1$s of %2$s requests remaining this hour.', 'enrove-folios'),
      number_format_i18n($remaining),
      number_format_i18n($limit)
    ));
    $this->settings->redirect_to_imagery_tab();
  }

  /** The API key card on the Imagery tab. */
  public function display_card()
  {
    $locked = Key::is_locked_by_constant();
    $source = Key::source();
    $masked = Key::masked();
    $has_key = $source !== '';
    $has_option_key = get_option('enrove_pexels_api_key', '') !== '';
    ?>
  <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
    <div>
      <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Pexels API Key', 'enrove-folios'); ?></h3>
      <p class="mt-1 mb-0 text-sm text-gray-600">
        <?php
        printf(
          /* translators: %s: the curation command. */
          esc_html__('Used only by the imagery curation script in a development checkout (%s), which picks the covers and sample photos and records where each can be downloaded from. Folios never call the Pexels API when they are viewed or edited.', 'enrove-folios'),
          '<code>php bin/curate-pexels.php --help</code>'
        );
        ?>
      </p>
    </div>

    <?php if ($locked): ?>
    <div class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
      <p class="m-0">
        <?php
        printf(
          /* translators: %s: the wp-config.php constant name. */
          esc_html__('%s is defined in wp-config.php and takes precedence. The field below is disabled.', 'enrove-folios'),
          '<code>ENROVE_PEXELS_API_KEY</code>'
        );
        ?>
      </p>
    </div>
    <?php endif; ?>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4" autocomplete="off" data-enrove-track-changes>
      <?php wp_nonce_field('enrove_save_pexels_key', 'enrove_nonce'); ?>
      <input type="hidden" name="action" value="save_enrove_pexels_key" />

      <div>
        <label for="enrove-pexels-api-key" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">
          <?php esc_html_e('API Key', 'enrove-folios'); ?>
        </label>
        <input
          id="enrove-pexels-api-key"
          type="password"
          name="pexels_api_key"
          value=""
          autocomplete="new-password"
          spellcheck="false"
          class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm disabled:bg-gray-100 disabled:text-gray-400"
          placeholder="<?php echo esc_attr($masked !== '' ? $masked : __('Paste your Pexels API key', 'enrove-folios')); ?>"
          <?php disabled($locked, true); ?> />
        <p class="mt-1 mb-0 text-xs text-gray-400">
          <?php esc_html_e('The stored key is never displayed. Leave this empty to keep the current key.', 'enrove-folios'); ?>
        </p>
      </div>

      <div>
        <button
          type="submit"
          id="enrove-save-pexels-key"
          class="button button-primary"
          data-enrove-save
          data-enrove-save-idle="<?php echo esc_attr__('Paste a key into the field above to save it.', 'enrove-folios'); ?>"
          <?php disabled($locked, true); ?>><?php esc_html_e('Save Key', 'enrove-folios'); ?></button>
      </div>
    </form>

    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm text-gray-700 space-y-1">
      <p class="m-0">
        <strong><?php esc_html_e('Key source:', 'enrove-folios'); ?></strong>
        <?php echo esc_html($this->get_source_label($source)); ?>
        <?php if ($masked !== ''): ?>
        <code><?php echo esc_html($masked); ?></code>
        <?php endif; ?>
      </p>
      <p class="m-0 text-xs text-gray-500">
        <?php esc_html_e('Checked in order: wp-config.php constant, PEXELS_API_KEY environment variable, .pexels-key file, then this setting.', 'enrove-folios'); ?>
      </p>
    </div>

    <div class="flex items-center gap-2">
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('enrove_test_pexels_connection', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="test_enrove_pexels_connection" />
        <button type="submit" id="enrove-test-pexels-connection" class="button button-secondary" <?php disabled(!$has_key, true); ?>>
          <?php esc_html_e('Test Connection', 'enrove-folios'); ?>
        </button>
      </form>

      <?php if ($has_option_key): ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" data-enrove-confirm="<?php esc_attr_e('Remove the stored Pexels API key?', 'enrove-folios'); ?>">
        <?php wp_nonce_field('enrove_delete_pexels_key', 'enrove_nonce'); ?>
        <input type="hidden" name="action" value="delete_enrove_pexels_key" />
        <button type="submit" class="button-link delete"><?php esc_html_e('Remove Key', 'enrove-folios'); ?></button>
      </form>
      <?php endif; ?>
    </div>

    <p class="m-0 text-xs text-gray-500">
      <?php esc_html_e('Testing the connection is the only action on this screen that contacts Pexels, and it only happens when you press the button.', 'enrove-folios'); ?>
    </p>
  </section>
    <?php
  }

  /** What the Credits panel adds while there is nothing to credit. */
  public function display_curation_hint()
  {
    ?>
      <p class="mt-2 mb-0">
        <?php esc_html_e('Run the curation script to download imagery and build the credits file:', 'enrove-folios'); ?>
        <code>php bin/curate-pexels.php</code>
      </p>
    <?php
  }

  /** The Privacy tab's line on the API, which only a checkout can call. */
  public function display_privacy_item()
  {
    ?>
        <li>
          <?php esc_html_e('Pexels API — only if you add your own API key on the Imagery tab, and only when you run the image curation script or press the connection test yourself.', 'enrove-folios'); ?>
        </li>
    <?php
  }
}
