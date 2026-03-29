<?php
  namespace Groove\Pages;
  use Groove\Pages\Page;
  use Groove\Pages\Overview;
  use Groove\Menu\Menu_Manager;
  use Groove\Menu\Support_Menu_Item;
  

  if ( ! defined( 'ABSPATH' ) ) {
  	exit; // Exit if accessed directly.
  }

  class Support extends Page {
    const PAGE_ID = 'groove-support';

    private $supportTypes;

    public function __construct() {
      $this->add_post_action('groove_get_support', 'get_support');

      $this->supportTypes = array(
        array(
          'value' => 0,
          'label' => esc_html__('Get support', 'groove')
        ),
        array(
          'value' => 1,
          'label' => esc_html__('Get help', 'groove')
        ),
      );

      add_action( 'groove/menu/register', function( Menu_Manager $menu ) {
				$menu->register( static::PAGE_ID, new Support_Menu_Item( $this ) );
			}, Overview::MENU_PRIORITY + 20 );
    }

    public function get_support () {
      $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
      if ($action === 'groove_get_support') {
        check_admin_referer('groove_get_support', 'groove_nonce');

        if (!current_user_can('manage_options')) {
          wp_die(esc_html__('You do not have permission to contact support.', 'groove'));
        }

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (!is_email($email)) {
          $this->redirect_with_notice('error', 'invalid_email');
        }

        if (empty($message)) {
          $this->redirect_with_notice('error', 'missing_message');
        }

        $to = get_option('admin_email');
        $subject = sprintf('[Groove Support] %s', $title ? $title : 'Support Request');
        $body = "From: {$email}\n";
        $body .= "Site: " . home_url('/') . "\n\n";
        $body .= $message;
        $headers = array('Reply-To: ' . $email);

        $result = wp_mail($to, $subject, $body, $headers);
        if ($result) {
          $this->redirect_with_notice('success', 'sent');
        }

        $this->redirect_with_notice('error', 'mail_failed');
      }
    }

    public function get_title() {
      return 'Support';
    }

    public function create_tabs () {
      return array();
    }


    public function display_support_fields () {
      $notice = isset($_GET['groove_notice']) ? sanitize_key(wp_unslash($_GET['groove_notice'])) : '';
      $notice_value = isset($_GET['groove_value']) ? sanitize_key(wp_unslash($_GET['groove_value'])) : '';

      $error_message = esc_html__('Unable to send support message. Please verify your input and try again.', 'groove');
      if ($notice === 'error') {
        if ($notice_value === 'invalid_email') {
          $error_message = esc_html__('Please enter a valid email address.', 'groove');
        } else if ($notice_value === 'missing_message') {
          $error_message = esc_html__('Message is required.', 'groove');
        }
      }
    ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post" class="space-y-4">
        <?php wp_nonce_field('groove_get_support', 'groove_nonce'); ?>
        <?php if ('success' === $notice): ?>
          <div class="notice notice-success inline">
            <p><?php echo esc_html__('Support message sent.', 'groove'); ?></p>
          </div>
        <?php elseif ('error' === $notice): ?>
          <div class="notice notice-error inline">
            <p><?php echo esc_html($error_message); ?></p>
          </div>
        <?php endif; ?>
        <section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
          <div>
            <h3 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Send Us a Message', 'groove'); ?></h3>
            <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Describe your issue or question and we will get back to you.', 'groove'); ?></p>
          </div>

          <div>
            <label for="groove-support-email" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Email*', 'groove'); ?></label>
            <input id="groove-support-email" type="email" name="email" value="" placeholder="<?php esc_attr_e('your@email.com', 'groove'); ?>" required class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>

          <div>
            <label for="groove-support-title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Title', 'groove'); ?></label>
            <input id="groove-support-title" type="text" name="title" value="" placeholder="<?php esc_attr_e('Optional', 'groove'); ?>" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>

          <div>
            <label for="groove-support-message" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide"><?php esc_html_e('Message', 'groove'); ?></label>
            <textarea id="groove-support-message" name="message" placeholder="<?php esc_attr_e('Tell us what you need help with…', 'groove'); ?>" required rows="7" class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
          </div>

          <div>
            <button type="submit" class="button button-primary" name="action" value="groove_get_support"><?php esc_html_e('Send', 'groove'); ?></button>
          </div>
        </section>
      </form>
    <?php
    }

    public function display_support () {
    ?>
      <div class="space-y-4">
        <div class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-3">
          <p class="m-0 text-sm text-indigo-900">
            <?php esc_html_e('Need help with Groove Folios? Share the details and our team will follow up.', 'groove'); ?>
          </p>
        </div>
        <?php $this->display_support_fields() ?>
      </div>
    <?php
    }

    public function display_content () {
      $this->display_support();
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
  }
