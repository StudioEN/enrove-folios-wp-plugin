<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Themes_Menu_Item;
use Groove\Themes\Themes_Manager;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Themes admin page.
 *
 * Displays all registered themes (built-in + installed packages) and
 * provides a ZIP upload form so admins can install new managed theme packages
 * without touching any plugin files.
 *
 * @since 1.0.0
 */
class Themes extends Page
{
	const PAGE_ID = 'groove-themes';

	public function get_title()
	{
		return esc_html__('Themes', 'groove');
	}

	public function create_tabs()
	{
		return [];
	}

	public function __construct()
	{
		// Register POST action handlers (both priv — themes require manage_options).
		$this->add_post_action('groove_install_theme', 'handle_install');
		$this->add_post_action('groove_uninstall_theme', 'handle_uninstall');

		add_action('groove/menu/register', function (Menu_Manager $menu) {
			$menu->register(static::PAGE_ID, new Themes_Menu_Item($this));
		}, Overview::MENU_PRIORITY + 20);
	}

	// -----------------------------------------------------------------------
	// Install handler
	// -----------------------------------------------------------------------

	/**
	 * Handle a theme ZIP upload and install it.
	 * Hooked to admin_post_groove_install_theme.
	 */
	public function handle_install()
	{
		check_admin_referer('groove_install_theme');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to install themes.', 'groove'));
		}

		if (empty($_FILES['theme_zip']) || $_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK) {
			$this->redirect_with_notice('error', 'upload_failed');
			return;
		}

		$result = Themes_Manager::install_theme_from_zip($_FILES['theme_zip']['tmp_name']);

		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', urlencode($result->get_error_message()));
			return;
		}

		$this->redirect_with_notice('success', urlencode($result));
	}

	// -----------------------------------------------------------------------
	// Uninstall handler
	// -----------------------------------------------------------------------

	/**
	 * Handle a theme uninstall request.
	 * Hooked to admin_post_groove_uninstall_theme.
	 */
	public function handle_uninstall()
	{
		check_admin_referer('groove_uninstall_theme');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to remove themes.', 'groove'));
		}

		$theme_id = isset($_POST['theme_id']) ? sanitize_key($_POST['theme_id']) : '';

		if (empty($theme_id)) {
			$this->redirect_with_notice('error', 'missing_theme_id');
			return;
		}

		$result = Themes_Manager::uninstall_theme($theme_id);

		if (is_wp_error($result)) {
			$this->redirect_with_notice('error', urlencode($result->get_error_message()));
			return;
		}

		$this->redirect_with_notice('uninstalled', $theme_id);
	}

	// -----------------------------------------------------------------------
	// Display
	// -----------------------------------------------------------------------

	public function display_content()
	{
		$all_themes = Themes_Manager::get_all_themes();
		$installed_meta = Themes_Manager::get_installed_themes_meta();
		$folio_counts = $this->get_folio_counts_by_theme();
		$total_themes = count($all_themes);
		$installed_count = count($installed_meta);
		$builtin_count = max(0, $total_themes - $installed_count);
		$default_theme_id = (string) get_option('groove_default_theme_id', '');
		$notice_type = isset($_GET['groove_notice']) ? sanitize_key($_GET['groove_notice']) : '';
		$notice_value = isset($_GET['groove_value']) ? sanitize_text_field(urldecode($_GET['groove_value'])) : '';
		?>
		<div class="g-themes-page">

			<?php $this->queue_notice_toast($notice_type, $notice_value); ?>

			<div class="g-themes-section">
				<div class="g-themes-section-header">
					<h2 class="g-themes-section-title"><?php esc_html_e('Your Themes', 'groove'); ?></h2>
					<p class="g-themes-section-desc">
						<?php
						/* The three summary tiles that used to sit above the grid counted
						   what the grid already shows, so the counts moved into this line
						   and the tiles came out. */
						if ($total_themes === 0) {
							esc_html_e('No themes are available yet.', 'groove');
						} else {
							printf(
								/* translators: 1: total theme count, 2: built-in count, 3: installed package count */
								esc_html(
									_n(
										'%1$s theme available — %2$s built-in, %3$s installed. Select one for details.',
										'%1$s themes available — %2$s built-in, %3$s installed. Select one for details.',
										$total_themes,
										'groove'
									)
								),
								esc_html(number_format_i18n($total_themes)),
								esc_html(number_format_i18n($builtin_count)),
								esc_html(number_format_i18n($installed_count))
							);
						}
						?>
					</p>
				</div>

				<?php if (empty($all_themes)): ?>
					<div class="g-themes-empty">
						<p><?php esc_html_e('No themes installed yet. Upload a theme package below.', 'groove'); ?></p>
					</div>
				<?php else: ?>
					<div class="g-themes-grid">
						<?php foreach ($all_themes as $id => $theme):
							$folio_count = isset($folio_counts[$id]) ? (int) $folio_counts[$id] : 0;
							?>
							<button type="button" class="g-themes-card" data-groove-theme-open="<?php echo esc_attr($id); ?>"
								data-theme-name="<?php echo esc_attr($theme['name']); ?>" aria-haspopup="dialog">
								<span class="g-themes-card-thumb">
									<?php /* The name is right beside it — an alt would only repeat it. */ ?>
									<img src="<?php echo esc_url($theme['thumbnail_url']); ?>" alt="" loading="lazy" />
								</span>
								<span class="g-themes-card-body">
									<span class="g-themes-card-name"><?php echo esc_html($theme['name']); ?></span>
									<span class="g-themes-card-count">
										<?php
										echo esc_html(
											$folio_count > 0
												? sprintf(
													/* translators: %s: number of folios using this theme */
													_n('%s folio', '%s folios', $folio_count, 'groove'),
													number_format_i18n($folio_count)
												)
												: __('Unused', 'groove')
										);
										?>
									</span>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="g-themes-section g-themes-upload-section">
				<div class="g-themes-section-header">
					<h2 class="g-themes-section-title"><?php esc_html_e('Install a Theme', 'groove'); ?></h2>
					<p class="g-themes-section-desc">
						<?php esc_html_e('Upload a Groove theme package (.zip) provided by the Groove team or a trusted theme author.', 'groove'); ?>
					</p>
				</div>

				<div class="g-themes-upload-grid">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
						enctype="multipart/form-data" class="g-themes-upload-form" id="groove-theme-upload-form">
						<?php wp_nonce_field('groove_install_theme'); ?>
						<input type="hidden" name="action" value="groove_install_theme" />

						<div class="g-themes-dropzone" id="groove-theme-dropzone">
							<div class="g-themes-dropzone-icon">📦</div>
							<p class="g-themes-dropzone-label">
								<?php esc_html_e('Drag and drop your theme .zip here', 'groove'); ?>
							</p>
							<p class="g-themes-dropzone-sub">
								<?php esc_html_e('or choose a file to upload', 'groove'); ?>
							</p>
							<label for="theme_zip" class="button button-secondary g-themes-file-label">
								<?php esc_html_e('Choose File', 'groove'); ?>
							</label>
							<input type="file" name="theme_zip" id="theme_zip" accept=".zip" class="g-themes-file-input" />
							<p class="g-themes-file-name" id="groove-theme-filename">
								<?php esc_html_e('No file chosen', 'groove'); ?>
							</p>
						</div>

						<div class="g-themes-upload-actions">
							<button type="submit" class="button button-primary" id="groove-theme-submit" disabled>
								<?php esc_html_e('Install Theme', 'groove'); ?>
							</button>
						</div>
					</form>

					<div class="g-themes-package-info">
						<h3><?php esc_html_e('Package format', 'groove'); ?></h3>
						<p><?php esc_html_e('A valid Groove theme package is a .zip file with this structure:', 'groove'); ?>
						</p>
						<pre class="g-themes-code">my-theme.zip
├── setup.php
├── cover.php
├── page.php
└── assets/
    ├── css/
    │   └── theme.css
    └── images/
        ├── theme-thumb.png
        ├── theme-cover.jpg
        └── theme-g-logo.png</pre>
						<p>
							<?php esc_html_e('setup.php, cover.php and page.php are required; everything under assets/ is optional. setup.php must declare a name, a cover_class and a page_class.', 'groove'); ?>
						</p>
						<p>
							<?php esc_html_e('assets/css/theme.css is enqueued automatically. Image filenames are whatever setup.php declares — they are only ever looked for in assets/images/.', 'groove'); ?>
						</p>
						<p>
							<?php esc_html_e('The theme name in setup.php becomes its ID automatically, so uninstall a theme before re-uploading a package with the same name.', 'groove'); ?>
						</p>
					</div>
				</div>
			</div>
		</div>

		<?php $this->display_details_modal($all_themes, $installed_meta, $folio_counts, $default_theme_id); ?>

		<script>
			(function () {
				var dropzone = document.getElementById('groove-theme-dropzone');
				var input = document.getElementById('theme_zip');
				var filename = document.getElementById('groove-theme-filename');
				var submit = document.getElementById('groove-theme-submit');

				function setFile(file) {
					if (!file) return;
					filename.textContent = file.name;
					submit.disabled = false;
					dropzone.classList.add('g-themes-dropzone--has-file');
				}

				input.addEventListener('change', function () {
					setFile(this.files[0]);
				});

				dropzone.addEventListener('dragover', function (e) {
					e.preventDefault();
					dropzone.classList.add('g-themes-dropzone--over');
				});
				dropzone.addEventListener('dragleave', function () {
					dropzone.classList.remove('g-themes-dropzone--over');
				});
				dropzone.addEventListener('drop', function (e) {
					e.preventDefault();
					dropzone.classList.remove('g-themes-dropzone--over');
					var file = e.dataTransfer.files[0];
					if (file && file.name.toLowerCase().endsWith('.zip')) {
						// Attach to the real file input via DataTransfer.
						var dt = new DataTransfer();
						dt.items.add(file);
						input.files = dt.files;
						setFile(file);
					} else if (window.grooveShowToast) {
						window.grooveShowToast('Themes are installed from a .zip file.', 'error');
					}
				});
			})();
		</script>

		<script>
			// Theme details dialog. Every panel is rendered server-side and hidden;
			// opening a card reveals its panel rather than rebuilding one in JS, so
			// nonces, copy and per-theme forms all stay in PHP.
			(function () {
				var modal = document.getElementById('g-theme-details-modal');
				if (!modal) return;

				var titleEl = document.getElementById('g-theme-details-title');
				var panels = modal.querySelectorAll('[data-groove-theme-panel]');
				var lastFocused = null;
				var hideTimer = null;

				function resetDanger(panel) {
					var confirmBox = panel.querySelector('[data-groove-theme-danger-confirm]');
					var trigger = panel.querySelector('[data-groove-theme-danger-start]');
					if (confirmBox) confirmBox.hidden = true;
					if (trigger) trigger.hidden = false;
				}

				function open(themeId, name) {
					var matched = null;
					Array.prototype.forEach.call(panels, function (panel) {
						var mine = panel.getAttribute('data-groove-theme-panel') === themeId;
						panel.hidden = !mine;
						if (mine) {
							matched = panel;
							resetDanger(panel);
						}
					});
					if (!matched) return;

					window.clearTimeout(hideTimer);
					lastFocused = document.activeElement;
					titleEl.textContent = name;
					modal.hidden = false;
					// The lock is a class rather than an inline style: the theme
					// preview overlay stacks above this dialog and clears its own
					// inline lock on close, which would otherwise unlock the page
					// while this dialog is still open.
					document.body.classList.add('g-modal-open');
					window.requestAnimationFrame(function () {
						modal.classList.add('is-open');
					});
					modal.querySelector('.g-theme-details__close').focus();
					modal.querySelector('.g-theme-details__body').scrollTop = 0;
				}

				function close() {
					if (modal.hidden) return;

					modal.classList.remove('is-open');
					document.body.classList.remove('g-modal-open');
					// Held in the DOM until the fade finishes; the timer also covers
					// reduced motion, where no transition fires at all.
					hideTimer = window.setTimeout(function () {
						modal.hidden = true;
					}, 260);

					if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
				}

				// Everything visible inside the dialog, in document order.
				function focusables() {
					var found = modal.querySelectorAll('button, a[href], input:not([type="hidden"])');
					return Array.prototype.filter.call(found, function (el) {
						return el.offsetParent !== null;
					});
				}

				document.addEventListener('click', function (e) {
					var target = e.target instanceof Element ? e.target : null;
					if (!target) return;

					var card = target.closest('[data-groove-theme-open]');
					if (card) {
						open(card.getAttribute('data-groove-theme-open'), card.getAttribute('data-theme-name') || '');
						return;
					}
					if (target.closest('[data-groove-theme-close]')) {
						close();
						return;
					}

					var start = target.closest('[data-groove-theme-danger-start]');
					if (start) {
						var panel = start.closest('[data-groove-theme-panel]');
						start.hidden = true;
						var box = panel.querySelector('[data-groove-theme-danger-confirm]');
						box.hidden = false;
						box.querySelector('[data-groove-theme-danger-cancel]').focus();
						return;
					}

					var cancel = target.closest('[data-groove-theme-danger-cancel]');
					if (cancel) {
						var owner = cancel.closest('[data-groove-theme-panel]');
						resetDanger(owner);
						owner.querySelector('[data-groove-theme-danger-start]').focus();
					}
				});

				document.addEventListener('keydown', function (e) {
					if (e.key !== 'Escape' || modal.hidden) return;
					// The live preview overlay stacks above this dialog and owns
					// Escape while it is open.
					var preview = document.getElementById('g-tpp-overlay');
					if (preview && !preview.hidden) return;
					e.preventDefault();
					close();
				});

				modal.addEventListener('keydown', function (e) {
					if (e.key !== 'Tab') return;
					var items = focusables();
					if (!items.length) return;

					var first = items[0];
					var last = items[items.length - 1];
					if (e.shiftKey && document.activeElement === first) {
						e.preventDefault();
						last.focus();
					} else if (!e.shiftKey && document.activeElement === last) {
						e.preventDefault();
						first.focus();
					}
				});
			})();
		</script>
		<?php
	}

	/**
	 * The theme details dialog: one shell, one hidden panel per theme.
	 *
	 * The grid card carries only a thumbnail and a name; everything else about a
	 * theme — its stats, and the actions that operate on it — lives here so the
	 * grid stays scannable and the actions stay in one place.
	 *
	 * @param array  $all_themes       Descriptors keyed by theme ID.
	 * @param array  $installed_meta   Installed-package meta keyed by theme ID.
	 * @param array  $folio_counts     Folio count keyed by theme ID.
	 * @param string $default_theme_id The theme new folios start from.
	 */
	private function display_details_modal($all_themes, $installed_meta, $folio_counts, $default_theme_id)
	{
		if (empty($all_themes)) {
			return;
		}

		$can_create = current_user_can('edit_posts');
		$can_manage = current_user_can('manage_options');
		?>
		<div id="g-theme-details-modal" class="g-theme-details" role="dialog" aria-modal="true"
			aria-labelledby="g-theme-details-title" hidden>
			<div class="g-theme-details__backdrop" data-groove-theme-close></div>
			<div class="g-theme-details__dialog">
				<div class="g-theme-details__header">
					<h2 id="g-theme-details-title" class="g-theme-details__title"></h2>
					<button type="button" class="g-theme-details__close" data-groove-theme-close
						aria-label="<?php esc_attr_e('Close theme details', 'groove'); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
				<div class="g-theme-details__body">
					<?php foreach ($all_themes as $id => $theme):
						$is_installed = isset($installed_meta[$id]);
						$folio_count = isset($folio_counts[$id]) ? (int) $folio_counts[$id] : 0;
						$version = $is_installed && !empty($installed_meta[$id]['version'])
							? (string) $installed_meta[$id]['version']
							: '';
						$sample = Themes_Manager::get_sample_content((string) $id);
						$folios_url = admin_url('admin.php?page=' . \Groove\Pages\All_Folios::PAGE_ID . '&theme_id=' . $id);
						?>
						<div class="g-theme-details__panel" data-groove-theme-panel="<?php echo esc_attr($id); ?>" hidden>
							<div class="g-theme-details__media">
								<img src="<?php echo esc_url(!empty($theme['cover_url']) ? $theme['cover_url'] : $theme['thumbnail_url']); ?>"
									alt="" loading="lazy" />
							</div>

							<div class="g-theme-details__tags">
								<span class="g-themes-tag <?php echo $is_installed ? 'g-themes-tag--installed' : 'g-themes-tag--builtin'; ?>">
									<?php echo $is_installed ? esc_html__('Installed', 'groove') : esc_html__('Built-in', 'groove'); ?>
								</span>
								<?php if ((string) $id === $default_theme_id): ?>
									<span class="g-themes-tag g-themes-tag--default"><?php esc_html_e('Default', 'groove'); ?></span>
								<?php endif; ?>
							</div>

							<?php if (!empty($theme['description'])): ?>
								<p class="g-theme-details__desc"><?php echo esc_html($theme['description']); ?></p>
							<?php endif; ?>

							<dl class="g-theme-details__stats">
								<div class="g-theme-details__stat">
									<dt><?php esc_html_e('Folios', 'groove'); ?></dt>
									<dd>
										<?php if ($folio_count > 0): ?>
											<a href="<?php echo esc_url($folios_url); ?>">
												<?php echo esc_html(number_format_i18n($folio_count)); ?>
											</a>
										<?php else: ?>
											<span class="g-theme-details__muted"><?php esc_html_e('None yet', 'groove'); ?></span>
										<?php endif; ?>
									</dd>
								</div>
								<?php if (!empty($theme['author'])): ?>
									<div class="g-theme-details__stat">
										<dt><?php esc_html_e('Author', 'groove'); ?></dt>
										<dd><?php echo esc_html($theme['author']); ?></dd>
									</div>
								<?php endif; ?>
								<?php if (!empty($theme['last_updated'])): ?>
									<div class="g-theme-details__stat">
										<dt><?php esc_html_e('Updated', 'groove'); ?></dt>
										<dd><?php echo esc_html($theme['last_updated']); ?></dd>
									</div>
								<?php endif; ?>
								<?php if ($version !== ''): ?>
									<div class="g-theme-details__stat">
										<dt><?php esc_html_e('Version', 'groove'); ?></dt>
										<dd><?php echo esc_html($version); ?></dd>
									</div>
								<?php endif; ?>
								<div class="g-theme-details__stat">
									<dt><?php esc_html_e('Theme ID', 'groove'); ?></dt>
									<dd><code><?php echo esc_html($id); ?></code></dd>
								</div>
							</dl>

							<?php
							/*
							 * The action row is the Add New picker's footer: the sample-content
							 * toggle rides the leading edge, the primary action keeps the
							 * trailing corner, and the sentence explaining what gets seeded
							 * hangs off the same info button rather than widening the row.
							 * Same classes, not a lookalike, so the two cannot drift.
							 *
							 * The <form> is the row itself — the toggle has to post with it,
							 * and wrapping it in a flex child of its own would take the
							 * toggle off the row's leading edge.
							 */
							$seed_field_id = 'g-theme-seed-' . sanitize_key($id);
							$row_classes = 'g-folio__theme-button g-theme-details__actions';
							?>
							<?php if ($can_create): ?>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
									class="<?php echo esc_attr($row_classes); ?>">
									<?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
									<input type="hidden" name="action" value="groove_create_folio" />
									<input type="hidden" name="themeId" value="<?php echo esc_attr($id); ?>" />

									<?php if ($sample !== null): ?>
										<div class="g-folio__sample-toggle">
											<label for="<?php echo esc_attr($seed_field_id); ?>"
												class="g-folio__sample-label">
												<input type="hidden" name="seed_sample_content" value="0" />
												<input type="checkbox" name="seed_sample_content" value="1"
													id="<?php echo esc_attr($seed_field_id); ?>" />
												<?php echo esc_html($sample['label']); ?>
											</label>
											<?php if ($sample['description'] !== ''): ?>
												<button type="button"
													class="g-folio__sample-info g-tooltip-button g-tooltip-button--wrap"
													aria-label="<?php echo esc_attr($sample['description']); ?>"
													data-tooltip-text="<?php echo esc_attr($sample['description']); ?>">
													<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>
												</button>
											<?php endif; ?>
										</div>
									<?php endif; ?>

									<?php /* Same control, same look as the Add New picker's Preview. */ ?>
									<button type="button" class="g-theme-preview-btn g-theme-details__preview"
										data-theme-id="<?php echo esc_attr($id); ?>">
										<?php esc_html_e('Preview', 'groove'); ?>
									</button>
									<button type="submit" class="button button-primary">
										<?php esc_html_e('Create a folio', 'groove'); ?>
									</button>
								</form>
							<?php else: ?>
								<div class="<?php echo esc_attr($row_classes); ?>">
									<button type="button" class="g-theme-preview-btn g-theme-details__preview"
										data-theme-id="<?php echo esc_attr($id); ?>">
										<?php esc_html_e('Preview', 'groove'); ?>
									</button>
								</div>
							<?php endif; ?>

							<?php if ($is_installed && $can_manage): ?>
								<div class="g-theme-details__danger">
									<button type="button" class="g-theme-details__danger-start"
										data-groove-theme-danger-start>
										<?php esc_html_e('Remove theme', 'groove'); ?>
									</button>
									<?php /* Deleting the package files cannot be undone, so this asks in
									         place rather than firing a browser confirm over the dialog. */ ?>
									<div class="g-theme-details__danger-confirm" data-groove-theme-danger-confirm hidden>
										<p>
											<?php
											if ($folio_count > 0) {
												printf(
													esc_html(
														_n(
															'Remove this theme? Its files are deleted permanently, and %s folio still uses it.',
															'Remove this theme? Its files are deleted permanently, and %s folios still use it.',
															$folio_count,
															'groove'
														)
													),
													esc_html(number_format_i18n($folio_count))
												);
											} else {
												esc_html_e('Remove this theme? Its files are deleted permanently.', 'groove');
											}
											?>
										</p>
										<div class="g-theme-details__danger-buttons">
											<button type="button" class="button button-secondary"
												data-groove-theme-danger-cancel>
												<?php esc_html_e('Keep it', 'groove'); ?>
											</button>
											<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
												<?php wp_nonce_field('groove_uninstall_theme'); ?>
												<input type="hidden" name="action" value="groove_uninstall_theme" />
												<input type="hidden" name="theme_id" value="<?php echo esc_attr($id); ?>" />
												<button type="submit" class="button button-secondary g-themes-delete-btn">
													<?php esc_html_e('Remove permanently', 'groove'); ?>
												</button>
											</form>
										</div>
									</div>
								</div>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Folio count per theme, in one query.
	 *
	 * The grid and the details dialog both need this for every theme; asking
	 * WP_Query once per theme meant a query per card.
	 *
	 * @return array Theme ID => folio count.
	 */
	private function get_folio_counts_by_theme()
	{
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS theme_id, COUNT(*) AS total
			   FROM {$wpdb->postmeta} pm
			   INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			  WHERE pm.meta_key = 'theme_id'
			    AND p.post_type = 'groove_folio'
			    AND p.post_status NOT IN ('auto-draft', 'trash', 'inherit')
			  GROUP BY pm.meta_value",
			ARRAY_A
		);

		$counts = array();
		foreach ((array) $rows as $row) {
			$counts[(string) $row['theme_id']] = (int) $row['total'];
		}

		return $counts;
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	/**
	 * Report an install or removal as a toast.
	 *
	 * The theme grid is the point of this screen, so the outcome of the last
	 * upload is reported over it rather than pushing every card down the page.
	 *
	 * @param string $type  Notice key from the redirect.
	 * @param string $value Theme name, or an error string.
	 */
	private function queue_notice_toast($type, $value)
	{
		if (empty($type)) {
			return;
		}

		$consumed = array('groove_notice', 'groove_value');

		switch ($type) {
			case 'success':
				\Groove\Toast::success(
					sprintf(
						/* translators: %s: theme name */
						__('"%s" installed successfully.', 'groove'),
						$value
					),
					$consumed
				);
				break;
			case 'uninstalled':
				\Groove\Toast::info(__('Theme removed successfully.', 'groove'), $consumed);
				break;
			case 'error':
			default:
				\Groove\Toast::error(
					!empty($value) ? $value : __('An unknown error occurred.', 'groove'),
					$consumed
				);
				break;
		}
	}

	private function redirect_with_notice($type, $value)
	{
		$url = add_query_arg([
			'page' => static::PAGE_ID,
			'groove_notice' => $type,
			'groove_value' => $value,
		], admin_url('admin.php'));

		wp_safe_redirect($url);
		exit;
	}
}
