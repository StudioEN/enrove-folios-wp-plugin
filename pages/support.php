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

        $type = isset($_POST['type']) ? (int) wp_unslash($_POST['type']) : 0;
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
        $message = isset($_POST['message']) ? sanitize_textarea_field(wp_unslash($_POST['message'])) : '';

        if (!is_email($email)) {
          $this->redirect_with_notice('error', 'invalid_email');
        }

        if (empty($message)) {
          $this->redirect_with_notice('error', 'missing_message');
        }

        $labels = wp_list_pluck($this->supportTypes, 'label', 'value');
        $type_label = isset($labels[$type]) ? $labels[$type] : $labels[0];

        $to = get_option('admin_email');
        $subject = sprintf('[Groove Support] %s', $title ? $title : 'Support Request');
        $body = "Type: {$type_label}\n";
        $body .= "From: {$email}\n";
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
    ?>
      <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
        <?php wp_nonce_field('groove_get_support', 'groove_nonce'); ?>
        <?php if ('success' === $notice): ?>
          <div class="notice notice-success inline">
            <p><?php echo esc_html__('Support message sent.', 'groove'); ?></p>
          </div>
        <?php elseif ('error' === $notice): ?>
          <div class="notice notice-error inline">
            <p><?php echo esc_html__('Unable to send support message. Please verify your input and try again.', 'groove'); ?></p>
          </div>
        <?php endif; ?>
        <div class="g-folio__support postbox-container">
          <div class="postbox">
            <div class="g-folio__fields-header">
              <h3 class="g-folio__fields-title">Send us a message</h3>
            </div>
            <div class="inside">
              <div class="g-row_field">
                <label for="type">I'D LIKE TO</label>
                <select name="type">
                  <?php
                    $supportTypes = $this->supportTypes;

                    foreach ($supportTypes as $type) {
                      ?>
                        <option value="<?php echo esc_attr($type['value']); ?>"><?php echo esc_html($type['label']) ?></option>
                      <?php
                    }
                  ?>
                </select>
              </div>
              <div class="g-row_field">
                <label for="email">EMAIL*</label>
                <input type="email" name="email" value="" placeholder="YOUR EMAIL" required />
              </div>
              <div class="g-row_field">
                <label for="title">TITLE</label>
                <input type="text" name="title" value="" placeholder="Optional" />
              </div>

              <div class="g-row_field">
                <label for="message">MESSAGE</label>
                <textarea type="text" name="message" value="" placeholder="Optional" required></textarea>
              </div>
            </div>
            <div class="g-folio__fields-footer">
              <button type="submit" class="g-folio__button g-folio__button-primary" name="action" value="groove_get_support">SEND</button>
            </div>
          </div>
        </div>
      </form>
    <?php
    }

    public function display_support () {
    ?>
      <div class="g-folio__fields">
        <div class="g-folio__fields-left g-folio__fields-section">
          <?php $this->display_support_fields() ?>
        </div>
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
?>
