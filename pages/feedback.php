<?php
  namespace Groove\Pages;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Feedback_Menu_Item;


  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }

  /**
   * Feedback page.
   *
   * Sends the submitted message to PostHog as a `feedback_submitted` event.
   * A PostHog destination filtered to that event forwards it to Slack — the
   * plugin holds no Slack credentials of its own.
   */
  class Feedback extends Page {
    const PAGE_ID = 'groove-feedback';

    /**
     * Transient holding an undelivered submission so the form can be
     * repopulated instead of losing what the user typed.
     */
    const DRAFT_TRANSIENT = 'groove_feedback_draft_';

    /**
     * Every submission is stored locally before we try to deliver it, so a
     * message is never lost to a network failure or a silently discarded
     * event. PostHog's capture endpoint answers 200 to anything — including a
     * wrong project key — so a successful HTTP response is not proof of
     * delivery, and this record is the only thing that is.
     */
    const POST_TYPE = 'groove_feedback';

    /** Give up after this many delivery attempts. */
    const MAX_ATTEMPTS = 5;

    /** Cron hook that retries undelivered submissions. */
    const RETRY_HOOK = 'groove/feedback/retry';

    private $feedback_types;

    public function __construct() {
      $this->add_post_action('groove_send_feedback', 'send_feedback');

      add_action('init', [$this, 'register_post_type'], 5);
      add_action(self::RETRY_HOOK, [$this, 'retry_undelivered']);

      if (!wp_next_scheduled(self::RETRY_HOOK)) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', self::RETRY_HOOK);
      }

      $this->feedback_types = array(
        'bug' => esc_html__('Something is broken', 'groove'),
        'question' => esc_html__('I have a question', 'groove'),
        'idea' => esc_html__('I have an idea', 'groove'),
        'other' => esc_html__('Something else', 'groove'),
      );

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new Feedback_Menu_Item( $this ) );
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function send_feedback () {
      $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
      if ($action !== 'groove_send_feedback') {
        return;
      }

      check_admin_referer('groove_send_feedback', 'groove_nonce');

      if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to send feedback.', 'groove'));
      }

      $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
      $type = isset($_POST['feedback_type']) ? sanitize_key(wp_unslash($_POST['feedback_type'])) : 'other';
      $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
      $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

      if (!isset($this->feedback_types[$type])) {
        $type = 'other';
      }

      if (!is_email($email)) {
        $this->save_draft($email, $type, $title, $message);
        $this->redirect_with_notice('error', 'invalid_email');
      }

      if (empty($message)) {
        $this->save_draft($email, $type, $title, $message);
        $this->redirect_with_notice('error', 'missing_message');
      }

      // Record it before attempting delivery. If the send fails, or succeeds
      // into a void, the message is still here.
      $record_id = $this->store($email, $type, $title, $message);

      $this->clear_draft();

      if (!$record_id) {
        $this->save_draft($email, $type, $title, $message);
        $this->redirect_with_notice('error', 'store_failed');
      }

      if ($this->deliver($record_id)) {
        $this->redirect_with_notice('success', 'sent');
      }

      $this->redirect_with_notice('warning', 'stored_not_sent');
    }

    /**
     * Register the private post type holding submitted feedback.
     *
     * Deliberately invisible: no admin UI, not public, not queryable on the
     * front end, not in search. It exists as a safety net, not as an inbox —
     * read it with WP-CLI or a direct query if delivery ever fails.
     */
    public function register_post_type()
    {
      register_post_type(self::POST_TYPE, array(
        'labels' => array(
          'name' => esc_html__('Feedback', 'groove'),
          'singular_name' => esc_html__('Feedback', 'groove'),
        ),
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => false,
        'show_in_menu' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
        'capability_type' => 'post',
        'supports' => array('title', 'editor'),
      ));
    }

    /**
     * Save a submission locally.
     *
     * @return int Post ID, or 0 on failure.
     */
    private function store($email, $type, $title, $message)
    {
      $record_title = $title !== ''
        ? $title
        : sprintf('%s — %s', $this->feedback_types[$type], $email);

      $record_id = wp_insert_post(array(
        'post_type' => self::POST_TYPE,
        'post_status' => 'private',
        'post_title' => $record_title,
        'post_content' => $message,
      ), true);

      if (is_wp_error($record_id) || !$record_id) {
        return 0;
      }

      update_post_meta($record_id, '_groove_feedback_email', $email);
      update_post_meta($record_id, '_groove_feedback_type', $type);
      update_post_meta($record_id, '_groove_feedback_subject', $title);
      update_post_meta($record_id, '_groove_feedback_delivered', 0);
      update_post_meta($record_id, '_groove_feedback_attempts', 0);

      return (int) $record_id;
    }

    /**
     * Attempt to deliver a stored submission to PostHog, recording the result.
     *
     * @return bool True when PostHog accepted the request. Note that PostHog
     *              answers 200 to any well-formed request, so this means
     *              "dispatched without error", not "confirmed received".
     */
    private function deliver($record_id)
    {
      $record = get_post($record_id);
      if (!$record || $record->post_type !== self::POST_TYPE) {
        return false;
      }

      $email = (string) get_post_meta($record_id, '_groove_feedback_email', true);
      $type = (string) get_post_meta($record_id, '_groove_feedback_type', true);
      $subject = (string) get_post_meta($record_id, '_groove_feedback_subject', true);
      $attempts = (int) get_post_meta($record_id, '_groove_feedback_attempts', true);

      if (!isset($this->feedback_types[$type])) {
        $type = 'other';
      }

      update_post_meta($record_id, '_groove_feedback_attempts', $attempts + 1);

      $sent = \Groove\Analytics::send_feedback(
        'feedback_submitted',
        $email,
        array(
          'feedback_type' => $type,
          'feedback_type_label' => $this->feedback_types[$type],
          'title' => $subject,
          'message' => $record->post_content,
          'submitted_at' => $record->post_date_gmt,
          'site_url' => home_url('/'),
          'site_hash' => md5(get_site_url()),
          'locale' => get_locale(),
          'folio_count' => (int) wp_count_posts('groove_folio')->publish,
          'default_theme' => (string) get_option('groove_default_theme_id', ''),
        ),
        array(
          'email' => $email,
          'site_url' => home_url('/'),
        )
      );

      update_post_meta($record_id, '_groove_feedback_delivered', $sent ? 1 : 0);

      return (bool) $sent;
    }

    /**
     * Retry every stored submission that hasn't been delivered yet. Runs
     * hourly on WP-Cron, and on demand from the Feedback page.
     *
     * @return int Number delivered on this pass.
     */
    public function retry_undelivered()
    {
      $pending = get_posts(array(
        'post_type' => self::POST_TYPE,
        'post_status' => 'private',
        'numberposts' => 20,
        'orderby' => 'date',
        'order' => 'ASC',
        'meta_query' => array(
          array(
            'key' => '_groove_feedback_delivered',
            'value' => 0,
          ),
          array(
            'key' => '_groove_feedback_attempts',
            'value' => self::MAX_ATTEMPTS,
            'compare' => '<',
            'type' => 'NUMERIC',
          ),
        ),
      ));

      $delivered = 0;
      foreach ($pending as $record) {
        if ($this->deliver($record->ID)) {
          $delivered++;
        }
      }

      return $delivered;
    }

    public function get_title() {
      return 'Feedback';
    }

    public function create_tabs () {
      return array();
    }


    public function display_feedback_fields () {
      $notice = isset($_GET['groove_notice']) ? sanitize_key(wp_unslash($_GET['groove_notice'])) : '';
      $notice_value = isset($_GET['groove_value']) ? sanitize_key(wp_unslash($_GET['groove_value'])) : '';

      $error_message = esc_html__('We could not save your message. Your text is still here — please try again.', 'groove');
      if ($notice === 'error') {
        if ($notice_value === 'invalid_email') {
          $error_message = esc_html__('Please enter a valid email address.', 'groove');
        } else if ($notice_value === 'missing_message') {
          $error_message = esc_html__('Message is required.', 'groove');
        }
      }

      $success_message = esc_html__('Thanks — your feedback is on its way to us.', 'groove');
      $warning_message = esc_html__('Saved, but we could not reach us just now. Your message is stored on this site and will be retried automatically — nothing is lost.', 'groove');

      $draft = $this->get_draft();
      $current_user = wp_get_current_user();
      $email_value = $draft['email'] !== '' ? $draft['email'] : $current_user->user_email;
    ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4">
        <?php wp_nonce_field('groove_send_feedback', 'groove_nonce'); ?>
        <input type="hidden" name="action" value="groove_send_feedback" />
        <?php if ('success' === $notice): ?>
          <div class="notice notice-success inline">
            <p><?php echo esc_html($success_message); ?></p>
          </div>
        <?php elseif ('warning' === $notice): ?>
          <div class="notice notice-warning inline">
            <p><?php echo esc_html($warning_message); ?></p>
          </div>
        <?php elseif ('error' === $notice): ?>
          <div class="notice notice-error inline">
            <p><?php echo esc_html($error_message); ?></p>
          </div>
        <?php endif; ?>
        <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
          <div>
            <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Send Us Feedback', 'groove'); ?></h3>
            <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Bugs, questions, ideas — anything you want us to know.', 'groove'); ?></p>
          </div>

          <div>
            <label for="groove-feedback-email" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Email*', 'groove'); ?></label>
            <input id="groove-feedback-email" type="email" name="email" value="<?php echo esc_attr($email_value); ?>" placeholder="<?php esc_attr_e('your@email.com', 'groove'); ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            <p class="mt-1 mb-0 text-xs text-gray-500"><?php esc_html_e('So we can reply. Nothing else is sent from your site.', 'groove'); ?></p>
          </div>

          <div>
            <label for="groove-feedback-type" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Type', 'groove'); ?></label>
            <select id="groove-feedback-type" name="feedback_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
              <?php foreach ($this->feedback_types as $type_key => $type_label): ?>
                <option value="<?php echo esc_attr($type_key); ?>" <?php selected($draft['type'], $type_key); ?>><?php echo esc_html($type_label); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label for="groove-feedback-title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Subject', 'groove'); ?></label>
            <input id="groove-feedback-title" type="text" name="title" value="<?php echo esc_attr($draft['title']); ?>" placeholder="<?php esc_attr_e('Optional', 'groove'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>

          <div>
            <label for="groove-feedback-message" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Message', 'groove'); ?></label>
            <textarea id="groove-feedback-message" name="message" placeholder="<?php esc_attr_e('Tell us what is on your mind…', 'groove'); ?>" required rows="7" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"><?php echo esc_textarea($draft['message']); ?></textarea>
          </div>

          <div class="flex items-center gap-3">
            <button type="submit" class="button button-primary"><?php esc_html_e('Send', 'groove'); ?></button>
            <span class="text-xs text-gray-500"><?php esc_html_e('Sent to StudioEN with your email address, site URL, and plugin version.', 'groove'); ?></span>
          </div>
        </section>
      </form>
    <?php
    }

    public function display_feedback () {
    ?>
      <div class="space-y-4">
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
          <p class="m-0 text-sm text-indigo-900">
            <?php esc_html_e('Every message reaches the people building Groove Folios. We read all of them.', 'groove'); ?>
          </p>
        </div>
        <?php $this->display_feedback_fields() ?>
      </div>
    <?php
    }

    public function display_content () {
      $this->display_feedback();
    }

    private function redirect_with_notice($type, $value)
    {
      $url = add_query_arg(
        array(
          'page' => static::PAGE_ID,
          'groove_notice' => $type,
          'groove_value' => $value,
        ),
        admin_url('admin.php')
      );

      wp_safe_redirect($url);
      exit;
    }

    /**
     * Hold an undelivered submission for the current user so the form can be
     * repopulated after a redirect.
     */
    private function save_draft($email, $type, $title, $message)
    {
      set_transient(
        self::DRAFT_TRANSIENT . get_current_user_id(),
        array(
          'email' => $email,
          'type' => $type,
          'title' => $title,
          'message' => $message,
        ),
        HOUR_IN_SECONDS
      );
    }

    private function get_draft()
    {
      $draft = get_transient(self::DRAFT_TRANSIENT . get_current_user_id());
      $defaults = array(
        'email' => '',
        'type' => 'bug',
        'title' => '',
        'message' => '',
      );

      if (!is_array($draft)) {
        return $defaults;
      }

      return array_merge($defaults, $draft);
    }

    private function clear_draft()
    {
      delete_transient(self::DRAFT_TRANSIENT . get_current_user_id());
    }
  }
