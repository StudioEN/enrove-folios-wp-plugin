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
        'type' => 'blank',
        'link' => Utils::get_folio_permalink_by_id(Utils::get_groove_post_id())
      ),
      array(
        'text' => 'Save Draft',
        'type' => 'secondary',
        'action' => 'save_groove_folio_draft'
      ),
      array(
        'text' => 'Publish',
        'type' => 'secondary',
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
              class="inline-flex items-center rounded-l-md border border-r-0 border-gray-300 bg-gray-50 px-3 text-gray-500 sm:text-sm">/folio/</span>
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
              <button type="button" id="feature-image"
                class="inline-flex items-center rounded border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Replace
                image</button>
              <button type="button" data-default-url="<?= esc_url($theme_url) ?>" id="use-default-image"
                class="inline-flex items-center border border-transparent px-2.5 py-1.5 text-xs font-medium text-indigo-600 hover:text-indigo-500 focus:outline-none">Use
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
    <div class="border-b border-gray-200 bg-white shadow-sm mb-6">
      <nav class="-mb-px flex space-x-8 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto" aria-label="Tabs">
        <?php
        foreach ($tabs as $tab_id => $tab) {
          $active_class = 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
          if ($tab_key === $tab_id) {
            $active_class = 'border-indigo-500 text-indigo-600';
          }
          $q['tab_key'] = $tab_id;
          $sanitized_tab_label = esc_html($tab['label']);
          $tab_url = add_query_arg($q, admin_url('admin.php'));
          echo '<a href="' . esc_url($tab_url) . '" data-tab-id="' . esc_attr($tab_id) . '" class="' . $active_class . ' whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">' . $sanitized_tab_label . '</a>';
        }
        ?>
      </nav>
    </div>
    <?php
  }

  public function display_tab_pages()
  {
    $post_type = static::POST_TYPE;
    $post_type_object = get_post_type_object($post_type);

    $table = new Folio_Page_List_Table($this, $post_type);

    $table->prepare_items();
    $table->views();

    if ($table->has_items()) {
      $table->inline_edit();
    }
    ?>
    <form id="pages-filter" method="get">
      <?php $table->search_box($post_type_object->labels->search_items, 'post'); ?>

      <input type="hidden" name="post_status" class="post_status_page"
        value="<?php echo !empty($_GET['post_status']) ? esc_attr(sanitize_key(wp_unslash($_GET['post_status']))) : 'all'; ?>" />
      <input type="hidden" name="post_type" class="post_type_page" value="<?php echo $post_type; ?>" />

      <?php $table->display(); ?>
    </form>
    <?php
  }

  public function display_page()
  {
    $folio = $this->get_folio_fields();
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
