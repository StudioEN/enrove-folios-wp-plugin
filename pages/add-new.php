<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Add_New_Menu_Item;


if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}


class Add_New extends Page
{
  const PAGE_ID = 'groove-add-new';
  const POST_TYPE = 'groove_folio_page';

  public function __construct()
  {
    $this->add_post_action('groove_create_folio', 'create_folio');

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Add_New_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);

    add_action('admin_init', function () {
      if (!isset($_GET['page']) || sanitize_key(wp_unslash($_GET['page'])) !== static::PAGE_ID) {
        return;
      }
      wp_safe_redirect(admin_url('admin.php?page=groove-all-folios&open_add_new=1'));
      exit;
    });
  }

  public function create_folio()
  {
    $action = isset($_POST['action']) ? sanitize_key(wp_unslash($_POST['action'])) : '';
    if ($action === 'groove_create_folio') {
      check_admin_referer('groove_create_folio_action', 'groove_nonce');

      if (!current_user_can('edit_posts')) {
        wp_die(esc_html__('You do not have permission to create folios.', 'groove'));
      }

      $themes = \Groove\Themes\Themes_Manager::get_all_themes();
      if (empty($themes)) {
        wp_die(esc_html__('No themes are available. Install a theme first.', 'groove'));
      }

      $default_theme_id = (string) get_option('groove_default_theme_id', '');
      if ($default_theme_id === '' || !isset($themes[$default_theme_id])) {
        $default_theme_id = (string) array_key_first($themes);
      }

      $theme_id = isset($_POST['themeId']) ? sanitize_key(wp_unslash($_POST['themeId'])) : $default_theme_id;
      if (empty($theme_id) || !isset($themes[$theme_id])) {
        wp_die(esc_html__('Invalid theme selection.', 'groove'));
      }
      $seed_proposal_sample = $theme_id === 'groove-proposal'
        && isset($_POST['seed_proposal_sample'])
        && (string) wp_unslash($_POST['seed_proposal_sample']) === '1';

      $default_status = (string) get_option('groove_default_folio_status', 'draft');
      if (!in_array($default_status, ['draft', 'publish'], true)) {
        $default_status = 'draft';
      }

      $title = (string) get_option('groove_default_folio_title', '');
      if ($title === '') {
        $title = esc_html__('A new folio', 'groove');
      }

      $fields = array(
        'post_type' => 'groove_folio',
        'post_status' => $default_status,
        'post_title' => $title,
        'post_name' => sanitize_title($title),
        'post_content' => '',
        'meta_input' => array(
          'theme_id' => $theme_id,
          'subtitle' => '',
          'use_folio' => '1',
          'show_logo' => '1',
          'copyright' => '',
        ),
      );

      if ($theme_id === 'groove-proposal') {
        $fields['meta_input']['proposal_show_in_page_nav'] = '1';
        $fields['meta_input']['proposal_color_scheme'] = 'default';
      }

      if ($seed_proposal_sample) {
        $fields['meta_input']['subtitle'] = __('Strategic proposal overview', 'groove');
        $fields['meta_input']['proposal_version'] = 'v0.1';
        $fields['meta_input']['proposal_status'] = __('Draft', 'groove');
        $fields['meta_input']['proposal_prepared_for'] = __('Client Name', 'groove');
        $fields['meta_input']['proposal_client_name'] = __('Client Name', 'groove');
        $fields['meta_input']['proposal_prepared_by'] = __('Your Agency Name', 'groove');
        $fields['meta_input']['proposal_contact_name'] = __('Engagement Lead', 'groove');
        $fields['meta_input']['proposal_contact_role'] = __('Principal Consultant', 'groove');
        $fields['meta_input']['proposal_contact_email'] = 'hello@example.com';
        $fields['meta_input']['proposal_contact_phone'] = '+1 (555) 010-2020';
        $fields['meta_input']['proposal_date'] = wp_date('Y-m-d');
      }

      $folio_id = wp_insert_post($fields);

      if (!is_wp_error($folio_id)) {
        if ($seed_proposal_sample) {
          $this->create_proposal_sample_pages((int) $folio_id, $default_status);
        }

        \Groove\Analytics::track('folio_created', [
          'theme'  => $theme_id,
          'status' => $default_status,
        ]);

        $redirect_url = admin_url('admin.php?page=groove-folio&folio_id=' . $folio_id);
        wp_safe_redirect($redirect_url);
        exit;
      }
      else {
        wp_die(esc_html($folio_id->get_error_message()));
      }
    }
  }

  public function get_title()
  {
    return esc_html__('Add New', 'groove');
  }

  public function create_tabs()
  {
    return array();
  }

  public function display__themes()
  {
    $themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $first_theme_id = '';
    $first_theme_name = '';
    if (!empty($themes)) {
      $saved_default = (string) get_option('groove_default_theme_id', '');
      if ($saved_default !== '' && isset($themes[$saved_default])) {
        $first_theme_id = $saved_default;
      } else {
        $first_theme_id = (string) array_key_first($themes);
      }

      if ($first_theme_id !== '' && isset($themes[$first_theme_id]['name'])) {
        $first_theme_name = (string) $themes[$first_theme_id]['name'];
      }
    }

    if (empty($themes)) {
      echo '<p>' . esc_html__('No themes available. Please install a theme first.', 'groove') . '</p>';
      return;
    }

    $theme_count = count($themes);
    $show_sample_toggle = $first_theme_id === 'groove-proposal';
?>
<p class="g-folio__themes-desc">
  <?php
  /* translators: %s: number of available themes */
  $themes_help_text = sprintf(
    _n('Choose a theme to get started. %s theme available.', 'Choose a theme to get started. %s themes available.', $theme_count, 'groove'),
    number_format_i18n($theme_count)
  );
  echo esc_html($themes_help_text);
  ?>
</p>
<p class="g-folio__themes-selected">
  <?php echo esc_html__('Selected theme:', 'groove'); ?>
  <strong id="g-folio-selected-theme-name"><?php echo esc_html($first_theme_name); ?></strong>
</p>
<form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
  <?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
  <input type="hidden" name="action" value="groove_create_folio" />

  <div class="g-folio__themes" role="radiogroup" aria-label="<?php echo esc_attr__('Available themes', 'groove'); ?>">
    <?php
    foreach ($themes as $id => $theme) {
      $is_first = ($id === $first_theme_id);
      $card_classes = 'g-folio__theme-option g-folio__theme-option--add-new relative cursor-pointer rounded-lg border-2 transition-all';
      $card_classes .= $is_first ? ' border-indigo-600 ring-1 ring-indigo-600' : ' border-gray-200 hover:border-gray-300';
      echo '<div class="g-folio__theme-card-wrap">';
      echo '<button type="button" class="' . esc_attr($card_classes) . '"
                 data-theme-id="' . esc_attr($id) . '"
                 data-theme-name="' . esc_attr($theme['name']) . '"
                 role="radio"
                 aria-checked="' . ($is_first ? 'true' : 'false') . '"
                 tabindex="' . ($is_first ? '0' : '-1') . '">';
      echo '<div class="g-folio__theme-option-thumb aspect-w-16 aspect-h-9 overflow-hidden rounded-t-lg rounded-b-none border-b border-gray-200">';
      echo '<img src="' . esc_url($theme['thumbnail_url']) . '" alt="' . esc_attr($theme['name']) . '" class="object-cover w-full h-full" loading="lazy" />';
      echo '</div>';
      echo '<div class="g-folio__theme-option-name p-2 text-center text-sm font-medium text-gray-900 border-t border-gray-100 bg-gray-50/50 rounded-b-lg">';
      echo esc_html($theme['name']);
      echo '</div>';
      echo '<span class="active-badge absolute -top-2 -right-2 inline-flex items-center rounded-full bg-indigo-600 px-2.5 py-0.5 text-xs font-medium text-white shadow-sm ring-2 ring-white ' . ($is_first ? '' : 'hidden') . '">';
      echo esc_html__('Selected', 'groove');
      echo '</span>';
      echo '</button>';
      echo '<button type="button" class="g-theme-preview-btn" data-theme-id="' . esc_attr($id) . '" aria-label="' . esc_attr(sprintf(__('Preview %s theme', 'groove'), $theme['name'])) . '">';
      echo esc_html__('Preview', 'groove');
      echo '</button>';
      echo '</div>';
    }
?>
  </div>
  <input type="hidden" id="g-add-new-theme-id" name="themeId" value="<?php echo esc_attr($first_theme_id)?>" />
  <div class="<?php echo $show_sample_toggle ? '' : 'hidden'; ?> my-5" data-add-new-theme-target="groove-proposal"
    data-disable-hidden-fields="1">
    <label for="seed_proposal_sample" class="inline-flex items-center text-sm text-gray-800">
      <input type="hidden" name="seed_proposal_sample" value="0" />
      <input type="checkbox" id="seed_proposal_sample" name="seed_proposal_sample" value="1"
        class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
      <?php echo esc_html__('Create with sample proposal content', 'groove'); ?>
    </label>
    <p class="m-0 mt-2 text-xs text-gray-500">
      <?php echo esc_html__('Seeds cover details and five sample pages that showcase every proposal content block.', 'groove'); ?>
    </p>
  </div>
  <div class="g-folio__theme-button">
    <button type="submit" class="button button-primary">
      <?php echo esc_html__('Continue', 'groove'); ?>
    </button>
  </div>
</form>
<?php
  }

  private function create_proposal_sample_pages(int $folio_id, string $status): void
  {
    $status = in_array($status, array('draft', 'publish', 'private', 'pending'), true) ? $status : 'draft';
    $pages = $this->get_proposal_sample_pages();

    foreach ($pages as $index => $page) {
      wp_insert_post(array(
        'post_type' => 'groove_folio_page',
        'post_status' => $status,
        'post_title' => $page['title'],
        'post_content' => $page['content'],
        'menu_order' => $index + 1,
        'meta_input' => array(
          'folio_id' => $folio_id,
        ),
      ));
    }
  }

  private function get_proposal_sample_pages(): array
  {
    $acme_logo_url      = GROOVE_URL . 'themes/groove-proposal/assets/images/sample-logos/acme.svg';
    $nordlight_logo_url = GROOVE_URL . 'themes/groove-proposal/assets/images/sample-logos/nordlight.svg';
    $vale_logo_url      = GROOVE_URL . 'themes/groove-proposal/assets/images/sample-logos/vale.svg';
    $zhou_logo_url      = GROOVE_URL . 'themes/groove-proposal/assets/images/sample-logos/zhou-studio.svg';

    return array(
      array(
        'title' => __('Executive Summary', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Context</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Your team is preparing to scale delivery while improving positioning in a more competitive market. This proposal outlines a focused engagement to align strategy, service narrative, and execution priorities in one practical roadmap.</p>
<!-- /wp:paragraph -->
<!-- wp:paragraph -->
<p>We've partnered with organizations facing a similar inflection point, from early-stage teams to established players resetting their story for a new market.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/logo-strip {"logos":[{"url":"{$acme_logo_url}","name":"Acme Inc.","link":""},{"url":"{$nordlight_logo_url}","name":"Nordlight Group","link":""},{"url":"{$vale_logo_url}","name":"Vale & Co.","link":""},{"url":"{$zhou_logo_url}","name":"Zhou Studio","link":""}]} /-->
<!-- wp:heading {"level":2} -->
<h2>Engagement goals</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We will clarify your growth priorities, refine your offer architecture, and define operating rhythms that support consistent delivery. The objective is measurable progress in pipeline quality, decision speed, and account confidence.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":2} -->
<h2>Expected outcomes</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul><li>Sharper positioning and value communication.</li><li>Prioritized delivery plan with ownership.</li><li>Clear implementation milestones for the next 90 days.</li></ul>
<!-- /wp:list -->
<!-- wp:groove-proposal/key-metrics /-->
HTML,
      ),
      array(
        'title' => __('Scope and Approach', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Workstreams</h2>
<!-- /wp:heading -->
<!-- wp:list -->
<ul><li>Discovery interviews and signal analysis.</li><li>Offer and messaging calibration.</li><li>Delivery model refinement with role clarity.</li></ul>
<!-- /wp:list -->
<!-- wp:groove-proposal/process-steps /-->
<!-- wp:heading {"level":2} -->
<h2>Collaboration model</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We run weekly working sessions with concise decision memos and a shared action board. Stakeholders receive asynchronous updates between sessions to reduce meeting overhead while preserving momentum.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/team-grid /-->
<!-- wp:heading {"level":2} -->
<h2>Deliverables</h2>
<!-- /wp:heading -->
<!-- wp:groove-proposal/deliverables /-->
<!-- wp:groove-proposal/callout-box {"title":"A note on scope","body":"This proposal assumes access to existing brand assets and a single point of contact on your side. If either isn't available yet, we'll adjust the Phase 1 timeline together.","style":"important"} /-->
HTML,
      ),
      array(
        'title' => __('Case Studies', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>The clearest way to evaluate a partner is to see how they've handled a comparable challenge. Here's a recent engagement with a client in a similar position.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/case-study {"clientName":"Nordlight Group","clientLogo":"{$nordlight_logo_url}","projectTitle":"Repositioning Nordlight for a category shift","tagsText":"Brand, Positioning, Web","stats":[{"value":"3.2x","label":"Qualified pipeline growth"},{"value":"6 weeks","label":"Kickoff to launch"}],"layout":"spotlight"} -->
<!-- wp:heading {"level":3} -->
<h3>The Challenge</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Nordlight had outgrown the positioning that took them to their first major revenue milestone. Every proposal was competing on price because prospects couldn't tell them apart from larger, better-funded competitors.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3>Our Approach</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>We ran structured interviews with their best and lost accounts, mapped where Nordlight actually won, and rebuilt the narrative and site around that evidence rather than aspiration.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"level":3} -->
<h3>The Results</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Within two quarters of launch, the new positioning was showing up directly in sales conversations and win rates.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/key-metrics {"items":[{"value":"41%","label":"Shorter sales cycle"},{"value":"9","label":"New logos in Q1"}]} /-->
<!-- /wp:groove-proposal/case-study -->
<!-- wp:groove-proposal/testimonial-grid /-->
<!-- wp:groove-proposal/pull-quote /-->
HTML,
      ),
      array(
        'title' => __('Timeline and Investment', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:heading {"level":2} -->
<h2>Timeline</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>The engagement is planned over twelve weeks, structured in four phases with a decision checkpoint at the end of each one.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/timeline /-->
<!-- wp:heading {"level":2} -->
<h2>Investment</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>Project investment is structured as a fixed engagement fee with a staged payment schedule tied to phase completion. Optional follow-on support can be added as a monthly advisory retainer.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/pricing-table /-->
<!-- wp:heading {"level":2} -->
<h2>Package options</h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>If a broader or lighter-touch engagement suits your team better, here's how the tiers compare.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/comparison-columns /-->
HTML,
      ),
      array(
        'title' => __('Next Steps', 'groove'),
        'content' => (string) <<<HTML
<!-- wp:paragraph -->
<p>Here's what happens once you're ready to move forward, along with answers to the questions we hear most often at this stage.</p>
<!-- /wp:paragraph -->
<!-- wp:groove-proposal/faq /-->
<!-- wp:groove-proposal/cta /-->
HTML,
      ),
    );
  }

  public function display_content()
  {
?>
<div class="g-folio__content g-folio__postbox-themes">
  <div class="g-folio__postbox postbox-container" style="width: 656px">
    <div class="postbox">
      <div class="g-folio__postbox-header">
        <div class="g-folio__postbox-header-left">
          <h2 class="g-folio__postbox-title">
            <?php echo esc_html__('Themes', 'groove'); ?>
          </h2>
        </div>
        <div class="g-folio__postbox-header-right">
          <div class="g-folio__postbox-close">
            <?php
            $from = isset($_GET['from']) ? sanitize_key(wp_unslash($_GET['from'])) : 'groove-overview';
            $allowed_from = array('groove-overview', 'groove-all-folios');
            if (!in_array($from, $allowed_from, true)) {
              $from = 'groove-overview';
            }
            ?>
            <a type="submit" class="g-folio__postbox-icon gicon-close"
              href="<?php echo esc_url(add_query_arg(array('page' => $from), admin_url('admin.php'))); ?>"></a>
          </div>
        </div>
      </div>
      <div class="g-folio__postbox-body">
        <?php $this->display__themes()?>
      </div>
    </div>
  </div>
</div>
<?php
  }
}
