<?php
namespace Groove\Pages;
use Groove\Fields\FolioFields;
use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Folio_Menu_Item;
use Groove\List\Folio_Page_List_Table;
use Groove\Themes\Default_Themes;
use Groove\Utils\Utils;



if (!defined('ABSPATH')) {
  exit; // Exit if accessed directly.
}

class Folio extends Page
{
  const PAGE_ID = 'groove-folio';
  const POST_TYPE = 'groove_folio_page';

  private $folio;
  private $fields;

  public function __construct()
  {
    $this->add_post_action('save_groove_folio_draft', 'save_folio_draft');
    $this->add_post_action('save_groove_folio', 'save_folio_publish');
    $this->add_post_action('auto_save_groove_folio', 'auto_save_folio');

    $this->left_button_items = [
      array(
        'text' => 'Add Page',
        'type' => '',
        'link' => add_query_arg(
          array(
            'post_type' => 'groove_folio_page',
            'folio_id' => (int) Utils::get_groove_post_id(),
          ),
          admin_url('post-new.php')
        )
      )
    ];

    $this->right_button_items = [
      array(
        'text' => 'Preview',
        'type' => 'secondary',
        'ui' => 'wp',
        'link' => Utils::get_folio_permalink_by_id(Utils::get_groove_post_id())
      ),
      array(
        'text' => 'Save Draft',
        'type' => 'secondary',
        'ui' => 'wp',
        'action' => 'save_groove_folio_draft'
      ),
      array(
        'text' => 'Publish',
        'type' => 'primary',
        'ui' => 'wp',
        'action' => 'save_groove_folio'
      )
    ];

    add_action('save_post', [$this, 'save_post']);

    add_action('wp_ajax_folio_inline_save', [$this, 'inline_save']);

    add_action('groove/menu/register', function (Menu_Manager $menu) {
      $menu->register(static::PAGE_ID, new Folio_Menu_Item($this));
    }, Overview::MENU_PRIORITY + 20);

    add_action('current_screen', function () {
      $current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
      if ($current_page !== static::PAGE_ID) {
        return;
      }

      $folio_id = isset($_GET['folio_id']) ? (int) wp_unslash($_GET['folio_id']) : 0;

      if (!isset($_GET['folio_id'])) {
        $this->redirect_to_all_folios();
      } else if (!$folio_id) {
        $this->redirect_to_all_folios();
      } else {
        $this->get_folio_fields();
      }
    });
  }

  public function inline_save()
  {
    global $mode;

    check_ajax_referer('inlineeditnonce', '_inline_edit');

    if (!isset($_POST['post_ID']) || !(int) wp_unslash($_POST['post_ID'])) {
      wp_die();
    }

    $post_id = (int) wp_unslash($_POST['post_ID']);


    $post_type = isset($_POST['post_type']) ? sanitize_key(wp_unslash($_POST['post_type'])) : '';
    if ('page' === $post_type) {
      if (!current_user_can('edit_page', $post_id)) {
        wp_die(__('Sorry, you are not allowed to edit this page.'));
      }
    } else {
      if (!current_user_can('edit_post', $post_id)) {
        wp_die(__('Sorry, you are not allowed to edit this post.'));
      }
    }

    $last = wp_check_post_lock($post_id);
    if ($last) {
      $last_user = get_userdata($last);
      $last_user_name = $last_user ? $last_user->display_name : __('Someone');

      /* translators: %s: User's display name. */
      $msg_template = __('Saving is disabled: %s is currently editing this post.');

      if ('page' === $post_type) {
        /* translators: %s: User's display name. */
        $msg_template = __('Saving is disabled: %s is currently editing this page.');
      }

      printf($msg_template, esc_html($last_user_name));
      wp_die();
    }

    $data = &$_POST;
    $post = get_post($post_id, ARRAY_A);

    // Since it's coming from the database.
    $post = wp_slash($post);

    $data['content'] = $post['post_content'];
    $data['excerpt'] = $post['post_excerpt'];

    // Rename.
    $data['user_ID'] = get_current_user_id();

    if (isset($data['post_parent'])) {
      $data['parent_id'] = $data['post_parent'];
    }

    // Status.
    if (isset($data['keep_private']) && 'private' === $data['keep_private']) {
      $data['visibility'] = 'private';
      $data['post_status'] = 'private';
    } else {
      $data['post_status'] = $data['_status'];
    }

    if (empty($data['comment_status'])) {
      $data['comment_status'] = 'closed';
    }

    if (empty($data['ping_status'])) {
      $data['ping_status'] = 'closed';
    }

    // Exclude terms from taxonomies that are not supposed to appear in Quick Edit.
    if (!empty($data['tax_input'])) {
      foreach ($data['tax_input'] as $taxonomy => $terms) {
        $tax_object = get_taxonomy($taxonomy);
        /** This filter is documented in wp-admin/includes/class-wp-posts-list-table.php */
        if (!apply_filters('quick_edit_show_taxonomy', $tax_object->show_in_quick_edit, $taxonomy, $post['post_type'])) {
          unset($data['tax_input'][$taxonomy]);
        }
      }
    }

    // Hack: wp_unique_post_slug() doesn't work for drafts, so we will fake that our post is published.
    if (!empty($data['post_name']) && in_array($post['post_status'], array('draft', 'pending'), true)) {
      $post['post_status'] = 'publish';
      $data['post_name'] = wp_unique_post_slug($data['post_name'], $post['ID'], $post['post_status'], $post['post_type'], $post['post_parent']);
    }

    // Update the post.
    edit_post();


    $post_type = static::POST_TYPE;
    $table = new Folio_Page_List_Table($this, $post_type);

    $post_view = isset($_POST['post_view']) ? sanitize_key(wp_unslash($_POST['post_view'])) : '';
    $mode = 'excerpt' === $post_view ? 'excerpt' : 'list';

    $level = 0;
    if (is_post_type_hierarchical($table->screen->post_type)) {
      $request_post = array(get_post($post_id));
      $parent = $request_post[0]->post_parent;

      while ($parent > 0) {
        $parent_post = get_post($parent);
        $parent = $parent_post->post_parent;
        $level++;
      }
    }

    $table->ajax_rows(array(get_post($post_id)), $level);

    wp_die();
  }

  public function save_post($post_id)
  {
    if ('groove_folio_page' === get_post_type($post_id)) {
      if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
      }
      if (!current_user_can('edit_post', $post_id)) {
        return;
      }
      $folio_id = get_post_meta($post_id, 'folio_id', true);
      if (!$folio_id && isset($_POST['folio_id'])) {
        $folio_id = (int) wp_unslash($_POST['folio_id']);
      }
      if (!$folio_id && isset($_GET['folio_id'])) {
        $folio_id = (int) wp_unslash($_GET['folio_id']);
      }
      if ($folio_id && 'groove_folio' !== get_post_type($folio_id)) {
        return;
      }
      update_post_meta($post_id, 'folio_id', $folio_id);
    }
  }

  public function get_fields()
  {
    return $this->get_folio_fields();
  }

  public function get_folio_fields()
  {
    if ($this->folio == null) {
      if (isset($_GET['folio_id'])) {
        $id = (int) wp_unslash($_GET['folio_id']);
        if (!$id) {
          $this->redirect_to_all_folios();
        }
        $args = array(
          'posts_per_page' => 1, // 获取一条数据
          'post__in' => array($id),
          'post_type' => 'groove_folio',
          'post_status' => array('draft', 'publish', 'pending', 'private')
        );
        $wp_query = new \WP_Query($args);

        if (!$wp_query->have_posts()) {
          $this->redirect_to_all_folios();
        } else {
          $this->folio = $wp_query->post;
          $this->fields = new FolioFields($this->folio);
        }
      }
    }

    return $this->fields;
  }

  public function redirect_to_all_folios()
  {
    $redirect_url = add_query_arg(array('page' => 'groove-all-folios'), admin_url('admin.php'));
    wp_safe_redirect($redirect_url);
    exit;
  }

  public function save_folio_draft()
  {
    $this->save_folio('draft');
  }

  public function save_folio_publish()
  {
    $this->save_folio('publish');
  }

  public function auto_save_folio()
  {
    $id = isset($_POST['folio_id']) ? (int) wp_unslash($_POST['folio_id']) : 0;
    $current_status = 'draft';
    if ($id) {
      $post = get_post($id);
      if ($post && $post->post_status === 'publish') {
        $current_status = 'publish';
      }
    }
    $this->save_folio($current_status);
  }

  public function save_folio($post_status = 'publish')
  {
    if (!is_string($post_status) || empty($post_status)) {
      $post_status = 'publish';
    }

    // Nonce check must come before reading any POST data.
    check_admin_referer('groove_save_folio', 'groove_nonce');

    if (empty($_POST['folio_id'])) {
      wp_send_json(array('code' => 400, 'message' => 'Missing folio ID.'));
      return;
    }

    $id = (int) wp_unslash($_POST['folio_id']);

    if (!current_user_can('edit_post', $id)) {
      wp_send_json(array('code' => 403, 'message' => 'Forbidden'));
      return;
    }

    $folio_post = get_post($id);
    if (!$folio_post || $folio_post->post_type !== 'groove_folio') {
      wp_send_json(array('code' => 404, 'message' => 'Folio not found.'));
      return;
    }

    $fields = new FolioFields($folio_post);

    $post_title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : $fields->title;
    $post_author = isset($_POST['author']) ? (int) wp_unslash($_POST['author']) : $fields->author;
    $subtitle = isset($_POST['subtitle']) ? sanitize_text_field(wp_unslash($_POST['subtitle'])) : $fields->subtitle;
    $password = isset($_POST['password']) ? sanitize_text_field(wp_unslash($_POST['password'])) : $fields->password;
    $copyright = isset($_POST['copyright']) ? sanitize_text_field(wp_unslash($_POST['copyright'])) : $fields->copyright;
    $permission = $fields->permission;
    if (isset($_POST['permission'])) {
      $permission_raw = (string) wp_unslash($_POST['permission']);
      $permission = in_array($permission_raw, array('on', '1', '2'), true) ? '2' : '4';
    }
    $theme_id = isset($_POST['theme_id']) ? sanitize_key(wp_unslash($_POST['theme_id'])) : $fields->theme_id;

    // feature_image_id: empty-string means 'clear image', positive int means 'set image'.
    // A missing or null field means 'keep existing' — we do NOT delete in that case.
    $feature_image_id = isset($_POST['feature_image_id']) ? (int) wp_unslash($_POST['feature_image_id']) : -1;

    // Derive slug from title and ensure it is unique for this post.
    $desired_slug = sanitize_title($post_title);
    $post_name = wp_unique_post_slug(
      $desired_slug,
      $id,
      $post_status,
      'groove_folio',
      0
    );

    $update_args = array(
      'ID' => $id,
      'post_status' => $post_status,
      'post_password' => $password,
      'post_title' => $post_title,
      'post_name' => $post_name,
      'post_author' => $post_author,
      'post_content' => '',
      'meta_input' => array(
        'theme_id' => $theme_id,
        'subtitle' => $subtitle,
        'copyright' => $copyright,
        'permission' => $permission
      )
    );

    $folio_result = wp_update_post($update_args);

    if ($feature_image_id === 0) {
      // Explicitly cleared (value was sent as empty string -> 0).
      delete_post_thumbnail($id);
    } elseif ($feature_image_id > 0) {
      set_post_thumbnail($id, $feature_image_id);
    }
    // $feature_image_id === -1 means field was not sent; keep existing thumbnail.

    if ($post_status === 'publish' && !is_wp_error($folio_result)) {
      $pages_query = new \WP_Query(array(
        'post_type' => 'groove_folio_page',
        'post_status' => 'any',
        'meta_query' => array(
          array(
            'key' => 'folio_id',
            'value' => $id,
            'compare' => '='
          )
        ),
        'posts_per_page' => -1
      ));

      if ($pages_query->have_posts()) {
        foreach ($pages_query->posts as $child_page) {
          if ($child_page->post_status !== 'publish') {
            wp_update_post(array(
              'ID' => $child_page->ID,
              'post_status' => 'publish'
            ));
          }
        }
      }
    }

    if (!is_wp_error($folio_result)) {
      // The JS button handler uses AJAX and expects JSON {code:0} to trigger location.reload().
      wp_send_json(array(
        'code' => 0,
        'post_id' => $id,
        'slug' => $post_name,
      ));
    } else {
      wp_send_json(array(
        'code' => 500,
        'message' => $folio_result->get_error_message(),
      ));
    }
  }

  public function get_title()
  {
    $fields = $this->get_folio_fields();
    if ($fields && !empty($fields->title)) {
      return $fields->title;
    }

    return esc_html__('Folio', 'groove');
  }

  public function create_tabs()
  {
    $tabs = [
      'setup' => [
        'label' => esc_html__('Setup', 'groove'),
      ],
      'pages' => [
        'label' => esc_html__('Pages', 'groove'),
      ]
    ];

    return $tabs;
  }

  public function display_content()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup';
    ?>
    <div class="g-top-bar-tabs-content">
      <?php
      foreach ($tabs as $tab_id => $tab) {
        $style = 'display: none';

        if ($tab_key === $tab_id) {
          $style = 'display: block';
        }

        $sanitized_tab_id = esc_attr($tab_id);

        echo '<div data-tab-content-id="' . $sanitized_tab_id . '" style="' . $style . '">';

        if ('setup' === $tab_id) {
          $this->display_tab_fields();
        } else {
          $this->display_tab_pages();
        }
        echo "</div>";
      }
      ?>
    </div>
    <?php
  }

  public function display_tab_fields()
  {
    $fields = $this->get_folio_fields();
    ?>
    <div class="mt-6 grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <input hidden name="folio_id" value="<?php echo esc_attr($fields->ID) ?>" />
      <div class="lg:col-span-2 space-y-6">
        <?php $this->display_essentials() ?>
        <?php $this->display_theme_selection() ?>
      </div>

      <div class="space-y-6">
        <?php $this->display_customization() ?>
        <?php $this->display_publishing() ?>
      </div>
    </div>
    <?php
  }

  public function display_customization()
  {
    $fields = $this->get_fields();

    // $fonts = $fields->fonts;
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Customization</h3>
      </div>
      <div class="p-4">
        <div class="mb-4">
          <label for="fonts" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">PRIMARY
            FONT</label>
          <select name="fonts"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
            <option value="Arial">Arial</option>
            <option value="PingFong">PingFong</option>
          </select>
        </div>
      </div>
    </div>
    <?php
  }

  public function display_publishing()
  {
    $fields = $this->get_fields();

    $permission = $fields->permission;
    $is_allowed_download = $permission == '2';

    $permalink = isset($fields->permalink) ? $fields->permalink : '';
    $base_slug = \Groove\Utils\Utils::get_folio_base_slug();
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Publishing</h3>
      </div>
      <div class="p-4 space-y-4">
        <div class="flex items-center">
          <input type="checkbox" id="permission" name="permission"
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?php echo checked($is_allowed_download, 1, false) ?> />
          <label for="permission" class="ml-2 block text-sm text-gray-900">Allow PDF Downloads</label>
        </div>
        <div>
          <label for="password"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">PASSWORD</label>
          <input type="password" id="password" placeholder="Enter your password" name="password"
            value="<?php echo esc_attr($fields->password) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
        <div>
          <label for="permalink" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">PERMALINK
            (Read-only)</label>
          <div class="mt-1 flex rounded-md shadow-sm">
            <span
              class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">/<?php echo esc_html($base_slug); ?>/</span>
            <input disabled type="text" id="permalink" name="permalink" value="<?php echo esc_attr($fields->name) ?>"
              class="block w-full min-w-0 flex-1 rounded-none rounded-r-md border-gray-300 px-3 py-2 bg-gray-100 text-gray-500 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>
        </div>
      </div>
    </div>
    <?php
  }

  public function display_essentials()
  {
    $fields = $this->get_fields();
    $selected_author = get_post_field('post_author', $fields->ID);

    $feature_image = isset($fields->feature_image) ? $fields->feature_image : '';

    $subtitle = isset($fields->subtitle) ? $fields->subtitle : '';
    $copyright = isset($fields->copyright) ? $fields->copyright : '';

    // Get current theme info for default image fallback
    $all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $current_theme = $all_themes[$fields->theme_id] ?? reset($all_themes);
    $theme_url = $current_theme["cover_url"] ?? '';
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Essentials</h3>
      </div>
      <div class="p-4 space-y-4">
        <?php wp_nonce_field('groove_save_folio', 'groove_nonce'); ?>
        <div>
          <label for="title" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">TITLE</label>
          <input type="text" id="title" name="title" value="<?php echo esc_attr($fields->title) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
        <div>
          <label for="subtitle"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">SUBTITLE</label>
          <input placeholder="Option" type="text" id="subtitle" name="subtitle" value="<?php echo esc_attr($subtitle) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">FEATURE IMAGE</label>
          <input value="<?= isset($feature_image->ID) ? $feature_image->ID : '' ?>" type="hidden" name="feature_image_id"
            id="media_id">
          <div class="flex items-start space-x-4">
            <img id="feature-preview" class="h-32 w-auto object-cover rounded border border-gray-200"
              src="<?= empty($feature_image->guid) ? esc_url($theme_url) : esc_url($feature_image->guid) ?>" />
            <div class="flex flex-col space-y-2">
              <button type="button" id="feature-image" class="button button-secondary">Replace
                image</button>
              <button type="button" data-default-url="<?= esc_url($theme_url) ?>" id="use-default-image"
                class="button-link">Use
                default</button>
            </div>
          </div>
        </div>
        <div>
          <label for="author" class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">AUTHOR</label>
          <?php wp_dropdown_users(array('name' => 'author', 'selected' => $selected_author, 'class' => 'block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm')) ?>
        </div>
        <div>
          <label for="copyright"
            class="block text-xs font-semibold text-gray-500 mb-2 uppercase tracking-wide">COPYRIGHT</label>
          <input placeholder="Option" type="text" id="copyright" name="copyright" value="<?php echo esc_attr($copyright) ?>"
            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
        </div>
      </div>
    </div>
    <?php
  }

  public function display_theme_selection()
  {
    $fields = $this->get_fields();
    $all_themes = \Groove\Themes\Themes_Manager::get_all_themes();
    $current_id = $fields->theme_id;
    ?>
    <div class="bg-white border mb-5 border-gray-200 rounded-lg shadow-sm">
      <div class="px-4 py-3 border-b border-gray-200 bg-gray-50/50 rounded-t-lg">
        <h3 class="text-sm font-semibold text-gray-800 m-0">Active Theme</h3>
      </div>
      <div class="p-4">
        <div class="grid gap-4 grid-cols-[repeat(auto-fill,minmax(150px,1fr))]">
          <?php foreach ($all_themes as $id => $theme):
            $active = ($id === $current_id);
            ?>
            <div
              class="g-folio__theme-option relative cursor-pointer rounded-lg border-2 transition-all <?php echo $active ? 'border-indigo-600 ring-1 ring-indigo-600' : 'border-gray-200 hover:border-gray-300'; ?>"
              data-theme-id="<?php echo esc_attr($id); ?>">
              <div class="aspect-w-16 aspect-h-9 overflow-hidden rounded-t-lg rounded-b-none border-b border-gray-200">
                <img src="<?php echo esc_url($theme['thumbnail_url']); ?>" alt="<?php echo esc_attr($theme['name']); ?>"
                  class="object-cover w-full h-full" />
              </div>
              <div
                class="p-2 text-center text-sm font-medium text-gray-900 border-t border-gray-100 bg-gray-50/50 rounded-b-lg">
                <?php echo esc_html($theme['name']); ?>
              </div>
              <span
                class="active-badge absolute -top-2 -right-2 inline-flex items-center rounded-full bg-indigo-600 px-2.5 py-0.5 text-xs font-medium text-white shadow-sm ring-2 ring-white <?php echo $active ? '' : 'hidden'; ?>">Active</span>
            </div>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="theme_id" id="g-active-theme-id" value="<?php echo esc_attr($current_id); ?>" />
      </div>
    </div>
    <?php
  }

  public function display_tabs()
  {
    $tabs = $this->get_tabs();
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup';

    $q = $this->parse_query();
    ?>
    <nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Folio tabs', 'groove'); ?>">
      <?php
      foreach ($tabs as $tab_id => $tab) {
        $active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
        $q['tab_key'] = $tab_id;
        $sanitized_tab_label = esc_html($tab['label']);
        $tab_url = add_query_arg($q, admin_url('admin.php'));
        echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . $active_class . '">' . $sanitized_tab_label . '</a>';
      }
      ?>
    </nav>
    <?php
  }

  private function get_pages_tab_current_status()
  {
    $status = isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : 'all';
    $allowed_statuses = array('all', 'publish', 'draft', 'pending', 'private', 'trash');

    if (!in_array($status, $allowed_statuses, true)) {
      return 'all';
    }

    return $status;
  }

  private function get_pages_tab_search_term()
  {
    return isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
  }

  private function get_pages_tab_current_paged()
  {
    return max(1, isset($_GET['paged']) ? (int) wp_unslash($_GET['paged']) : 1);
  }

  private function get_pages_tab_current_orderby()
  {
    $orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'modified';
    $allowed_orderby = array('title', 'menu_order', 'modified');

    if (!in_array($orderby, $allowed_orderby, true)) {
      return 'modified';
    }

    return $orderby;
  }

  private function get_pages_tab_current_order()
  {
    $order = isset($_GET['order']) ? strtoupper(sanitize_key(wp_unslash($_GET['order']))) : 'DESC';
    return $order === 'ASC' ? 'ASC' : 'DESC';
  }

  private function build_pages_tab_url($folio_id, $args = array())
  {
    return add_query_arg(
      array_merge(
        array(
          'page' => static::PAGE_ID,
          'tab_key' => 'pages',
          'folio_id' => (int) $folio_id,
        ),
        $args
      ),
      admin_url('admin.php')
    );
  }

  private function get_pages_tab_status_label($status)
  {
    switch ($status) {
      case 'publish':
        return esc_html__('Published', 'groove');
      case 'draft':
        return esc_html__('Draft', 'groove');
      case 'pending':
        return esc_html__('Pending', 'groove');
      case 'private':
        return esc_html__('Private', 'groove');
      case 'trash':
        return esc_html__('Trash', 'groove');
      default:
        return esc_html__('All', 'groove');
    }
  }

  private function get_pages_tab_status_counts($folio_id)
  {
    global $wpdb;

    $folio_id = (int) $folio_id;
    if ($folio_id <= 0) {
      return array(
        'all' => 0,
        'publish' => 0,
        'draft' => 0,
        'pending' => 0,
        'private' => 0,
        'trash' => 0,
      );
    }

    $rows = $wpdb->get_results(
      $wpdb->prepare(
        "SELECT p.post_status, COUNT(*) AS num_posts
         FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
         WHERE p.post_type = %s
           AND pm.meta_key = %s
           AND CAST(pm.meta_value AS UNSIGNED) = %d
         GROUP BY p.post_status",
        static::POST_TYPE,
        'folio_id',
        $folio_id
      )
    );

    $counts = array(
      'publish' => 0,
      'draft' => 0,
      'pending' => 0,
      'private' => 0,
      'future' => 0,
      'trash' => 0,
    );

    foreach ((array) $rows as $row) {
      if (!isset($row->post_status, $row->num_posts)) {
        continue;
      }
      $status = (string) $row->post_status;
      if (array_key_exists($status, $counts)) {
        $counts[$status] = (int) $row->num_posts;
      }
    }

    return array(
      'all' => $counts['publish'] + $counts['draft'] + $counts['pending'] + $counts['private'] + $counts['future'],
      'publish' => $counts['publish'],
      'draft' => $counts['draft'],
      'pending' => $counts['pending'],
      'private' => $counts['private'],
      'trash' => $counts['trash'],
    );
  }

  private function get_pages_tab_results($folio_id, $status, $search, $paged, $orderby, $order, $per_page = 20)
  {
    $query_args = array(
      'post_type' => static::POST_TYPE,
      'post_status' => $status === 'all' ? array('publish', 'draft', 'pending', 'private', 'future') : $status,
      'posts_per_page' => $per_page,
      'paged' => $paged,
      'order' => $order,
      'meta_query' => array(
        array(
          'key' => 'folio_id',
          'value' => (int) $folio_id,
          'compare' => '=',
          'type' => 'NUMERIC',
        ),
      ),
    );

    if ($orderby === 'menu_order') {
      $query_args['orderby'] = 'menu_order title';
    } elseif ($orderby === 'title') {
      $query_args['orderby'] = 'title';
    } else {
      $query_args['orderby'] = 'modified';
    }

    if ($search !== '') {
      $query_args['s'] = $search;
    }

    $query = new \WP_Query($query_args);

    return array(
      'posts' => (array) $query->posts,
      'total_items' => (int) $query->found_posts,
      'total_pages' => (int) $query->max_num_pages,
    );
  }

  private function get_pages_tab_sort_url($folio_id, $column, $current_orderby, $current_order, $status, $search)
  {
    $next_order = 'ASC';
    if ($column === $current_orderby && $current_order === 'ASC') {
      $next_order = 'DESC';
    }

    return $this->build_pages_tab_url(
      $folio_id,
      array_filter(array(
        'post_status' => $status !== 'all' ? $status : null,
        's' => $search !== '' ? $search : null,
        'orderby' => $column,
        'order' => $next_order,
      ), function ($value) {
        return $value !== null;
      })
    );
  }

  private function get_pages_tab_available_bulk_actions($status)
  {
    if ($status === 'trash') {
      return array(
        'untrash' => esc_html__('Restore', 'groove'),
        'delete' => esc_html__('Delete Permanently', 'groove'),
      );
    }

    return array(
      'trash' => esc_html__('Move to Trash', 'groove'),
    );
  }

  private function get_pages_tab_current_bulk_action()
  {
    $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '-1';
    $action2 = isset($_REQUEST['action2']) ? sanitize_key(wp_unslash($_REQUEST['action2'])) : '-1';

    if ($action !== '-1') {
      return $action;
    }
    if ($action2 !== '-1') {
      return $action2;
    }

    return false;
  }

  private function process_pages_tab_bulk_action($folio_id, $status, $search, $orderby, $order, $paged)
  {
    $action = $this->get_pages_tab_current_bulk_action();
    if (!$action) {
      return;
    }

    $available_actions = $this->get_pages_tab_available_bulk_actions($status);
    if (!isset($available_actions[$action])) {
      return;
    }

    check_admin_referer('groove_bulk_pages_action', '_groove_bulk_nonce');

    $post_ids = isset($_REQUEST['post']) ? (array) wp_unslash($_REQUEST['post']) : array();
    $post_ids = array_values(array_filter(array_map('intval', $post_ids)));
    if (empty($post_ids)) {
      return;
    }

    $updated_count = 0;
    foreach ($post_ids as $post_id) {
      if (get_post_type($post_id) !== static::POST_TYPE) {
        continue;
      }

      $page_folio_id = (int) get_post_meta($post_id, 'folio_id', true);
      if ($page_folio_id !== (int) $folio_id) {
        continue;
      }

      if (!current_user_can('delete_post', $post_id)) {
        continue;
      }

      switch ($action) {
        case 'trash':
          if (wp_trash_post($post_id)) {
            $updated_count++;
          }
          break;
        case 'untrash':
          if (wp_untrash_post($post_id)) {
            $updated_count++;
          }
          break;
        case 'delete':
          if (wp_delete_post($post_id, true)) {
            $updated_count++;
          }
          break;
      }
    }

    $redirect_args = array_filter(array(
      'post_status' => $status !== 'all' ? $status : null,
      's' => $search !== '' ? $search : null,
      'orderby' => $orderby !== 'modified' ? $orderby : null,
      'order' => $order !== 'DESC' ? $order : null,
      'paged' => $paged > 1 ? $paged : null,
      'bulk_action' => $action,
      'bulk_count' => $updated_count,
    ), function ($value) {
      return $value !== null;
    });

    wp_safe_redirect($this->build_pages_tab_url($folio_id, $redirect_args));
    exit;
  }

  private function display_pages_tab_bulk_notice()
  {
    if (!isset($_GET['bulk_action']) || !isset($_GET['bulk_count'])) {
      return;
    }

    $action = sanitize_key(wp_unslash($_GET['bulk_action']));
    $count = (int) wp_unslash($_GET['bulk_count']);
    if ($count < 1) {
      return;
    }

    $message = '';
    switch ($action) {
      case 'trash':
        $message = sprintf(
          /* translators: %s: number of pages moved to trash */
          esc_html(_n('%s page moved to Trash.', '%s pages moved to Trash.', $count, 'groove')),
          esc_html(number_format_i18n($count))
        );
        break;
      case 'untrash':
        $message = sprintf(
          /* translators: %s: number of pages restored */
          esc_html(_n('%s page restored.', '%s pages restored.', $count, 'groove')),
          esc_html(number_format_i18n($count))
        );
        break;
      case 'delete':
        $message = sprintf(
          /* translators: %s: number of pages deleted */
          esc_html(_n('%s page deleted permanently.', '%s pages deleted permanently.', $count, 'groove')),
          esc_html(number_format_i18n($count))
        );
        break;
    }

    if ($message === '') {
      return;
    }
    ?>
    <div class="notice notice-success is-dismissible">
      <p><?php echo esc_html($message); ?></p>
    </div>
    <?php
  }

  private function get_pages_tab_last_modified_by($post_id)
  {
    $last_editor_id = (int) get_post_meta($post_id, '_edit_last', true);
    if ($last_editor_id > 0) {
      $user = get_userdata($last_editor_id);
      if ($user) {
        return $user->display_name;
      }
    }

    $author = get_userdata((int) get_post_field('post_author', $post_id));
    return $author ? $author->display_name : esc_html__('Unknown user', 'groove');
  }

  public function display_tab_pages()
  {
    $folio_id = isset($_GET['folio_id']) ? (int) wp_unslash($_GET['folio_id']) : 0;
    $status = $this->get_pages_tab_current_status();
    $search = $this->get_pages_tab_search_term();
    $paged = $this->get_pages_tab_current_paged();
    $orderby = $this->get_pages_tab_current_orderby();
    $order = $this->get_pages_tab_current_order();
    $this->process_pages_tab_bulk_action($folio_id, $status, $search, $orderby, $order, $paged);

    $status_counts = $this->get_pages_tab_status_counts($folio_id);
    $bulk_actions = $this->get_pages_tab_available_bulk_actions($status);
    $results = $this->get_pages_tab_results($folio_id, $status, $search, $paged, $orderby, $order);
    $posts = (array) $results['posts'];
    $total_items = (int) $results['total_items'];
    $total_pages = (int) $results['total_pages'];
    $quick_edit_table = new Folio_Page_List_Table($this, static::POST_TYPE);
    ?>
    <?php $this->display_pages_tab_bulk_notice(); ?>
    <form id="pages-filter" method="get">
      <input type="hidden" name="page" value="<?php echo esc_attr(static::PAGE_ID); ?>" />
      <input type="hidden" name="tab_key" value="pages" />
      <input type="hidden" name="folio_id" value="<?php echo esc_attr((string) $folio_id); ?>" />
      <input type="hidden" name="post_status" class="post_status_page"
        value="<?php echo esc_attr($status); ?>" />
      <input type="hidden" name="orderby" value="<?php echo esc_attr($orderby); ?>" />
      <input type="hidden" name="order" value="<?php echo esc_attr($order); ?>" />
      <?php wp_nonce_field('groove_bulk_pages_action', '_groove_bulk_nonce'); ?>

      <ul class="subsubsub">
        <?php
        $total_statuses = count($status_counts);
        $status_index = 0;
        foreach ($status_counts as $status_key => $count) {
          $status_index++;
          $is_active = $status_key === $status;
          $status_url = $this->build_pages_tab_url(
            $folio_id,
            array_filter(array(
              'post_status' => $status_key,
              's' => $search !== '' ? $search : null,
              'orderby' => $orderby !== 'modified' ? $orderby : null,
              'order' => $order !== 'DESC' ? $order : null,
            ), function ($value) {
              return $value !== null;
            })
          );
          ?>
          <li class="<?php echo esc_attr($status_key); ?>">
            <a href="<?php echo esc_url($status_url); ?>" class="<?php echo esc_attr($is_active ? 'current' : ''); ?>">
              <?php echo esc_html($this->get_pages_tab_status_label($status_key)); ?>
              <span class="count">(<?php echo esc_html(number_format_i18n($count)); ?>)</span>
            </a>
            <?php if ($status_index < $total_statuses): ?>
              |
            <?php endif; ?>
          </li>
          <?php
        }
        ?>
      </ul>

      <p class="search-box">
        <label class="screen-reader-text"
          for="post-search-input"><?php esc_html_e('Search folio pages', 'groove'); ?>:</label>
        <input type="search" id="post-search-input" name="s" value="<?php echo esc_attr($search); ?>" />
        <input type="submit" id="search-submit" class="button"
          value="<?php esc_attr_e('Search Folio Pages', 'groove'); ?>" />
      </p>

      <div class="tablenav top">
        <div class="alignleft actions bulkactions">
          <label for="bulk-action-selector-top"
            class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove'); ?></label>
          <select name="action" id="bulk-action-selector-top">
            <option value="-1"><?php esc_html_e('Bulk actions', 'groove'); ?></option>
            <?php foreach ($bulk_actions as $action_key => $action_label): ?>
              <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
            <?php endforeach; ?>
          </select>
          <input type="submit" id="doaction" class="button action" value="<?php esc_attr_e('Apply', 'groove'); ?>" />
        </div>
        <div class="tablenav-pages">
          <?php if ($total_pages > 1): ?>
            <?php
            $base_url = $this->build_pages_tab_url(
              $folio_id,
              array_filter(array(
                'post_status' => $status !== 'all' ? $status : null,
                's' => $search !== '' ? $search : null,
                'orderby' => $orderby !== 'modified' ? $orderby : null,
                'order' => $order !== 'DESC' ? $order : null,
                'paged' => '%#%',
              ), function ($value) {
                return $value !== null;
              })
            );
            $pagination_links = paginate_links(array(
              'base' => $base_url,
              'format' => '',
              'current' => $paged,
              'total' => $total_pages,
              'type' => 'array',
              'prev_text' => '&lsaquo;',
              'next_text' => '&rsaquo;',
            ));
            ?>
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
            <span class="pagination-links">
              <?php echo wp_kses_post(implode(' ', (array) $pagination_links)); ?>
            </span>
          <?php else: ?>
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
          <?php endif; ?>
        </div>
        <br class="clear" />
      </div>

      <table class="wp-list-table widefat striped table-view-list pages">
        <thead>
          <tr>
            <th scope="col" id="cb" class="manage-column column-cb check-column">
              <label class="screen-reader-text"
                for="cb-select-all-1"><?php esc_html_e('Select all pages', 'groove'); ?></label>
              <input id="cb-select-all-1" type="checkbox" />
            </th>
            <th scope="col"
              class="manage-column column-primary <?php echo esc_attr($orderby === 'title' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'title', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Page Name', 'groove'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
            <th scope="col"
              class="manage-column <?php echo esc_attr($orderby === 'menu_order' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a
                href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'menu_order', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Menu Position', 'groove'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
            <th scope="col" class="manage-column"><?php esc_html_e('Publish Status', 'groove'); ?></th>
            <th scope="col"
              class="manage-column <?php echo esc_attr($orderby === 'modified' ? 'sorted ' . strtolower($order) : 'sortable desc'); ?>">
              <a
                href="<?php echo esc_url($this->get_pages_tab_sort_url($folio_id, 'modified', $orderby, $order, $status, $search)); ?>">
                <span><?php esc_html_e('Last Updated', 'groove'); ?></span>
                <span class="sorting-indicators"><span class="sorting-indicator asc" aria-hidden="true"></span><span
                    class="sorting-indicator desc" aria-hidden="true"></span></span>
              </a>
            </th>
          </tr>
        </thead>
        <tbody id="the-list">
          <?php if (!empty($posts)): ?>
            <?php foreach ($posts as $post): ?>
              <?php
              $post_id = (int) $post->ID;
              $post_status = (string) get_post_status($post_id);
              $title = get_the_title($post_id);
              $post_title = $title !== '' ? $title : esc_html__('(no title)', 'groove');
              $base_edit_url = get_edit_post_link($post_id, '');
              if ($base_edit_url) {
                $edit_url = add_query_arg(
                  array('folio_id' => (int) $folio_id),
                  $base_edit_url
                );
              } else {
                $edit_url = add_query_arg(
                  array(
                    'post' => $post_id,
                    'action' => 'edit',
                    'folio_id' => (int) $folio_id,
                  ),
                  admin_url('post.php')
                );
              }
              $view_url = get_permalink($post_id);
              $preview_url = get_preview_post_link(get_post($post_id), array('folio_id' => (int) $folio_id));
              $is_preview_status = in_array($post_status, array('draft', 'pending', 'future'), true);
              $row_view_url = ($is_preview_status && $preview_url) ? $preview_url : $view_url;
              $row_view_label = $is_preview_status ? esc_html__('Preview', 'groove') : esc_html__('View', 'groove');
              $modified_label = sprintf(
                /* translators: 1: date/time value, 2: user display name */
                esc_html__('%1$s by %2$s', 'groove'),
                get_the_modified_date(get_option('date_format') . ' ' . get_option('time_format'), $post_id),
                $this->get_pages_tab_last_modified_by($post_id)
              );
              $quick_edit_aria_label = sprintf(
                /* translators: %s: Page title. */
                esc_attr__('Quick edit "%s" inline', 'groove'),
                wp_strip_all_tags($post_title)
              );
              ?>
              <tr id="post-<?php echo esc_attr((string) $post_id); ?>">
                <th scope="row" class="check-column">
                  <label class="screen-reader-text"
                    for="cb-select-<?php echo esc_attr((string) $post_id); ?>"><?php esc_html_e('Select page', 'groove'); ?></label>
                  <input id="cb-select-<?php echo esc_attr((string) $post_id); ?>" type="checkbox" name="post[]"
                    value="<?php echo esc_attr((string) $post_id); ?>" />
                </th>
                <td class="title column-title has-row-actions column-primary page-title"
                  data-colname="<?php esc_attr_e('Page Name', 'groove'); ?>">
                  <strong class="g-folio__title-wrap">
                    <a class="row-title g-folio__truncate-text" href="<?php echo esc_url($edit_url); ?>"
                      title="<?php echo esc_attr($post_title); ?>">
                      <?php echo esc_html($post_title); ?>
                    </a>
                  </strong>
                  <div class="row-actions">
                    <span class="edit"><a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'groove'); ?></a> |</span>
                    <?php if ($post_status !== 'trash'): ?>
                      <span class="inline hide-if-no-js">
                        <button type="button" class="button-link editinline"
                          aria-label="<?php echo esc_attr($quick_edit_aria_label); ?>"
                          aria-expanded="false"><?php esc_html_e('Quick Edit', 'groove'); ?></button> |
                      </span>
                    <?php endif; ?>
                    <span class="view"><a href="<?php echo esc_url($row_view_url); ?>" target="_blank"
                        rel="noopener noreferrer"><?php echo esc_html($row_view_label); ?></a> |</span>
                    <?php if ($post_status === 'trash'): ?>
                      <span class="untrash"><a
                          href="<?php echo esc_url(wp_nonce_url(admin_url('post.php?action=untrash&post=' . $post_id), 'untrash-post_' . $post_id)); ?>"><?php esc_html_e('Restore', 'groove'); ?></a> |</span>
                      <span class="delete"><a class="submitdelete"
                          href="<?php echo esc_url(get_delete_post_link($post_id, '', true)); ?>"><?php esc_html_e('Delete Permanently', 'groove'); ?></a></span>
                    <?php else: ?>
                      <span class="trash"><a class="submitdelete"
                          href="<?php echo esc_url(get_delete_post_link($post_id)); ?>"><?php esc_html_e('Trash', 'groove'); ?></a></span>
                    <?php endif; ?>
                  </div>
                  <?php
                  if (function_exists('get_inline_data')) {
                    get_inline_data(get_post($post_id));
                  }
                  ?>
                  <button type="button" class="toggle-row"><span
                      class="screen-reader-text"><?php esc_html_e('Show more details', 'groove'); ?></span></button>
                </td>
                <td data-colname="<?php esc_attr_e('Menu Position', 'groove'); ?>">
                  <?php echo esc_html((string) (int) get_post_field('menu_order', $post_id)); ?>
                </td>
                <td data-colname="<?php esc_attr_e('Publish Status', 'groove'); ?>">
                  <?php
                  $status_object = get_post_status_object($post_status);
                  echo esc_html($status_object && !empty($status_object->label) ? (string) $status_object->label : ucfirst($post_status));
                  ?>
                </td>
                <td data-colname="<?php esc_attr_e('Last Updated', 'groove'); ?>">
                  <?php echo esc_html($modified_label); ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr class="no-items">
              <td class="colspanchange" colspan="5">
                <?php esc_html_e('No pages found for the current filters.', 'groove'); ?>
              </td>
            </tr>
          <?php endif; ?>
        </tbody>
        <tfoot>
          <tr>
            <td class="manage-column column-cb check-column">
              <label class="screen-reader-text"
                for="cb-select-all-2"><?php esc_html_e('Select all pages', 'groove'); ?></label>
              <input id="cb-select-all-2" type="checkbox" />
            </td>
            <th scope="col" class="manage-column column-primary"><?php esc_html_e('Page Name', 'groove'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Menu Position', 'groove'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Publish Status', 'groove'); ?></th>
            <th scope="col" class="manage-column"><?php esc_html_e('Last Updated', 'groove'); ?></th>
          </tr>
        </tfoot>
      </table>

      <?php if (!empty($posts)): ?>
        <?php
        $quick_edit_post = get_post((int) $posts[0]->ID);
        if ($quick_edit_post instanceof \WP_Post) {
          setup_postdata($quick_edit_post);
        }
        $quick_edit_table->inline_edit();
        if ($quick_edit_post instanceof \WP_Post) {
          wp_reset_postdata();
        }
        ?>
      <?php endif; ?>

      <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom">
          <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-bottom"
              class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove'); ?></label>
            <select name="action2" id="bulk-action-selector-bottom">
              <option value="-1"><?php esc_html_e('Bulk actions', 'groove'); ?></option>
              <?php foreach ($bulk_actions as $action_key => $action_label): ?>
                <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'groove'); ?>" />
          </div>
          <div class="tablenav-pages">
            <span class="displaying-num">
              <?php
              printf(
                /* translators: %s: number of items */
                esc_html(_n('%s item', '%s items', $total_items, 'groove')),
                esc_html(number_format_i18n($total_items))
              );
              ?>
            </span>
            <?php
            $bottom_base_url = $this->build_pages_tab_url(
              $folio_id,
              array_filter(array(
                'post_status' => $status !== 'all' ? $status : null,
                's' => $search !== '' ? $search : null,
                'orderby' => $orderby !== 'modified' ? $orderby : null,
                'order' => $order !== 'DESC' ? $order : null,
                'paged' => '%#%',
              ), function ($value) {
                return $value !== null;
              })
            );
            echo wp_kses_post(
              paginate_links(array(
                'base' => $bottom_base_url,
                'format' => '',
                'current' => $paged,
                'total' => $total_pages,
                'type' => 'plain',
                'prev_text' => '&lsaquo;',
                'next_text' => '&rsaquo;',
              ))
            );
            ?>
          </div>
          <br class="clear" />
        </div>
      <?php else: ?>
        <div class="tablenav bottom">
          <div class="alignleft actions bulkactions">
            <label for="bulk-action-selector-bottom"
              class="screen-reader-text"><?php esc_html_e('Select bulk action', 'groove'); ?></label>
            <select name="action2" id="bulk-action-selector-bottom">
              <option value="-1"><?php esc_html_e('Bulk actions', 'groove'); ?></option>
              <?php foreach ($bulk_actions as $action_key => $action_label): ?>
                <option value="<?php echo esc_attr($action_key); ?>"><?php echo esc_html($action_label); ?></option>
              <?php endforeach; ?>
            </select>
            <input type="submit" id="doaction2" class="button action" value="<?php esc_attr_e('Apply', 'groove'); ?>" />
          </div>
          <br class="clear" />
        </div>
      <?php endif; ?>
    </form>
    <?php
  }

  public function display_page()
  {
    $tab_key = isset($_GET['tab_key']) ? sanitize_key(wp_unslash($_GET['tab_key'])) : 'setup';

    if ($tab_key === 'pages') {
      $original_right_buttons = $this->right_button_items;
      $this->right_button_items = array_values(array_filter(
        (array) $this->right_button_items,
        function ($item) {
          return isset($item['link']);
        }
      ));

      parent::display_page();

      $this->right_button_items = $original_right_buttons;
      return;
    }

    $folio_id = isset($_GET['folio_id']) ? (int) wp_unslash($_GET['folio_id']) : 0;
    ?>
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('groove_save_folio', 'groove_nonce'); ?>
      <input type="hidden" name="folio_id" value="<?php echo esc_attr($folio_id); ?>" />
      <?php parent::display_page() ?>
    </form>
    <?php
  }
}
?>
