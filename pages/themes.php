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
		$this->add_post_action('groove_replace_theme', 'handle_replace');
		$this->add_post_action('groove_cancel_replace', 'handle_cancel_replace');

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
			// This used to redirect with the literal string 'upload_failed',
			// which is what the operator then read in the toast.
			$this->redirect_with_failure(
				__('The file did not finish uploading.', 'groove'),
				__('Check the package is a .zip and is smaller than this server\'s upload limit, then try again.', 'groove'),
				'#groove-theme-submit'
			);
			return;
		}

		$result = Themes_Manager::install_theme_from_zip($_FILES['theme_zip']['tmp_name']);

		if (is_wp_error($result)) {
			if ($result->get_error_code() === 'replace_confirm_required') {
				$this->park_upload_for_confirmation($_FILES['theme_zip']['tmp_name'], (array) $result->get_error_data());
				return;
			}

			$this->redirect_with_failure(
				$result->get_error_message(),
				(string) $result->get_error_data(),
				'#groove-theme-submit'
			);
			return;
		}

		$this->redirect_with_notice('success', urlencode($result));
	}

	/**
	 * Hold an upload that would replace an installed theme, and ask first.
	 *
	 * The zip has to outlive the request to survive the question, and PHP
	 * deletes the upload's temp file at the end of one — so it is moved
	 * somewhere of our own and the path kept in a per-user transient. The path
	 * is generated here and never comes from the request, so nothing the
	 * browser sends decides what gets unzipped on the way back.
	 */
	private function park_upload_for_confirmation($tmp_name, array $context)
	{
		// Discard anything the last question left behind. A parked upload is
		// cleared by either button, but an operator who simply walks away
		// leaves the zip on disk until the OS gets to it, and uploading again
		// is the moment we know the old one is not wanted.
		$key = 'groove_theme_pending_' . get_current_user_id();
		$stale = get_transient($key);
		if (!empty($stale['zip'])) {
			@unlink($stale['zip']);
		}

		$parked = trailingslashit(get_temp_dir()) . 'groove-pending-' . wp_generate_password(20, false) . '.zip';

		if (!@move_uploaded_file($tmp_name, $parked)) {
			$this->redirect_with_failure(
				__('The upload could not be held while you confirmed.', 'groove'),
				__('Check that PHP can write to the server\'s temporary directory, then try again.', 'groove'),
				'#groove-theme-submit'
			);
			return;
		}

		set_transient($key, array_merge($context, array('zip' => $parked)), 15 * MINUTE_IN_SECONDS);

		$this->redirect_with_notice('confirm_replace', '');
	}

	/**
	 * Complete an upload the operator confirmed should replace what is there.
	 */
	public function handle_replace()
	{
		check_admin_referer('groove_replace_theme');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to install themes.', 'groove'));
		}

		$key = 'groove_theme_pending_' . get_current_user_id();
		$pending = get_transient($key);
		delete_transient($key);

		if (empty($pending['zip']) || !is_readable($pending['zip'])) {
			$this->redirect_with_failure(
				__('That upload is no longer waiting to be confirmed.', 'groove'),
				__('It is held for fifteen minutes. Upload the package again.', 'groove'),
				'#groove-theme-submit'
			);
			return;
		}

		$result = Themes_Manager::install_theme_from_zip($pending['zip'], true);
		@unlink($pending['zip']);

		if (is_wp_error($result)) {
			$this->redirect_with_failure(
				$result->get_error_message(),
				(string) $result->get_error_data(),
				'#groove-theme-submit'
			);
			return;
		}

		$this->redirect_with_notice('replaced', urlencode($result));
	}

	/**
	 * Discard an upload the operator decided not to go through with.
	 */
	public function handle_cancel_replace()
	{
		check_admin_referer('groove_cancel_replace');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to install themes.', 'groove'));
		}

		$key = 'groove_theme_pending_' . get_current_user_id();
		$pending = get_transient($key);
		delete_transient($key);

		if (!empty($pending['zip'])) {
			@unlink($pending['zip']);
		}

		$this->redirect_with_notice('replace_cancelled', '');
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
			$this->redirect_with_failure(
				__('No theme was named in that request.', 'groove'),
				__('Open the theme from the grid and use Remove in its details dialog.', 'groove')
			);
			return;
		}

		$result = Themes_Manager::uninstall_theme($theme_id);

		if (is_wp_error($result)) {
			$this->redirect_with_failure($result->get_error_message(), (string) $result->get_error_data());
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
		<div class="space-y-4">

			<?php $this->queue_notice_toast($notice_type, $notice_value); ?>
			<?php $this->display_theme_problems($folio_counts); ?>
			<?php $this->display_replace_confirmation($folio_counts); ?>

			<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
				<div>
					<h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Your Themes', 'groove'); ?></h2>
					<p class="mt-1 mb-0 text-sm text-gray-600">
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
						<?php /* "None installed" is the wrong thing to say when there are
						         theme folders present that all failed to load — the panel
						         above is already naming them. */ ?>
						<p><?php
							echo esc_html(
								Themes_Manager::get_skipped_themes()
									? __('No themes loaded. Every theme folder on this site failed to register — see above.', 'groove')
									: __('No themes installed yet. Upload a theme package below.', 'groove')
							);
						?></p>
					</div>
				<?php else: ?>
					<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
						<?php foreach ($all_themes as $id => $theme):
							$folio_count = isset($folio_counts[$id]) ? (int) $folio_counts[$id] : 0;
							?>
							<button type="button" class="g-themes-card" data-groove-theme-open="<?php echo esc_attr($id); ?>"
								data-theme-name="<?php echo esc_attr($theme['name']); ?>"
								<?php /* The dialog's header wears the same badges this card stands for,
								         so it takes them from the card rather than re-deriving them. */ ?>
								data-theme-installed="<?php echo isset($installed_meta[$id]) ? '1' : '0'; ?>"
								data-theme-badge="<?php echo esc_attr(isset($installed_meta[$id]) ? __('Installed', 'groove') : __('Built-in', 'groove')); ?>"
								data-theme-default="<?php echo ((string) $id === $default_theme_id) ? '1' : '0'; ?>"
								aria-haspopup="dialog">
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
			</section>

			<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4 g-themes-upload-section">
				<div>
					<h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Install a Theme', 'groove'); ?></h2>
					<p class="mt-1 mb-0 text-sm text-gray-600">
						<?php esc_html_e('Upload a Groove theme package (.zip) provided by the Groove team or a trusted theme author.', 'groove'); ?>
					</p>
				</div>

				<?php /* Settings' thirds grid, with the spans the other way round: there
				         the form is the narrow column, here it holds the dropzone and the
				         package notes are the reference beside it. */ ?>
				<div class="grid grid-cols-1 xl:grid-cols-3 gap-4">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
						enctype="multipart/form-data" class="g-themes-upload-form xl:col-span-2" id="groove-theme-upload-form">
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

					<div class="g-themes-package-info xl:col-span-1">
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
						<?php /* Three paragraphs of contract used to sit here, paraphrasing
						         themes/README.md. That is how this panel came to describe a
						         package that would not work: it asked for assets/thumbnail.png
						         when images resolve from assets/images/, and an author who
						         followed it exactly got broken pictures and nothing to say why.
						         What is left is the two facts the tree cannot show and a
						         packager cannot infer — both enforced in code a few lines
						         apart, so neither can quietly stop being true — and a pointer
						         to the spec rather than a retelling of it. */ ?>
						<p>
							<?php esc_html_e('setup.php, cover.php and page.php are required; everything under assets/ is optional. Image filenames are whatever setup.php declares, and are only ever looked for in assets/images/.', 'groove'); ?>
						</p>
						<p>
							<?php esc_html_e('The theme name in setup.php becomes its ID, so a package cannot be re-uploaded as an update — remove the installed theme first.', 'groove'); ?>
						</p>
						<p>
							<?php
							printf(
								/* translators: %s: path to the theme spec, rendered as a code element */
								esc_html__('The full theme spec ships with this plugin at %s.', 'groove'),
								'<code>themes/README.md</code>'
							);
							?>
						</p>
					</div>
				</div>
			</section>
		</div>

		<?php $this->display_details_modal($all_themes, $installed_meta, $folio_counts); ?>

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
				var dialogEl = modal.querySelector('.g-theme-details__dialog');
				var badgeEl = modal.querySelector('[data-groove-theme-badge]');
				var defaultEl = modal.querySelector('[data-groove-theme-default]');
				var panels = modal.querySelectorAll('[data-groove-theme-panel]');
				var lastFocused = null;
				var hideTimer = null;

				function resetDanger(panel) {
					var confirmBox = panel.querySelector('[data-groove-theme-danger-confirm]');
					var trigger = panel.querySelector('[data-groove-theme-danger-start]');
					if (confirmBox) confirmBox.hidden = true;
					if (trigger) trigger.hidden = false;
				}

				// The card carries the theme's name and badges, so the header can be
				// filled from the thing that was clicked rather than from a second copy
				// of the same facts held in JS.
				function open(card) {
					// The replace confirmation is a question the upload is parked on, and
					// it renders before this modal in the DOM — a card reached by keyboard
					// behind it would open above the question, then clear the scroll lock
					// on close and leave the page scrolling under a dialog still open.
					if (document.body.classList.contains('g-theme-replace-open')) return;

					var themeId = card.getAttribute('data-groove-theme-open');
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
					titleEl.textContent = card.getAttribute('data-theme-name') || '';

					var badge = card.getAttribute('data-theme-badge') || '';
					var installed = card.getAttribute('data-theme-installed') === '1';
					badgeEl.textContent = badge;
					badgeEl.className = 'g-themes-tag ' + (installed ? 'g-themes-tag--installed' : 'g-themes-tag--builtin');
					badgeEl.hidden = badge === '';
					defaultEl.hidden = card.getAttribute('data-theme-default') !== '1';

					modal.hidden = false;
					// The lock is a class rather than an inline style: the theme
					// preview overlay stacks above this dialog and clears its own
					// inline lock on close, which would otherwise unlock the page
					// while this dialog is still open.
					document.body.classList.add('g-modal-open');
					window.requestAnimationFrame(function () {
						modal.classList.add('is-open');
					});
					// Focus the dialog itself: focusing the close button first paints a
					// ring on the one control you are least likely to want.
					modal.querySelector('.g-theme-details__body').scrollTop = 0;
					dialogEl.focus();
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
						open(card);
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
	 */
	private function display_details_modal($all_themes, $installed_meta, $folio_counts)
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
			<div class="g-theme-details__dialog" tabindex="-1">
				<div class="g-theme-details__header">
					<?php /* Name and badges sit together: what the theme is called and what
					         kind of theme it is are one fact, and keeping them on the header
					         line saves the body a row of chrome. Filled in on open. */ ?>
					<div class="g-theme-details__ident">
						<h2 id="g-theme-details-title" class="g-theme-details__title"></h2>
						<span class="g-themes-tag" data-groove-theme-badge hidden></span>
						<span class="g-themes-tag g-themes-tag--default" data-groove-theme-default hidden>
							<?php esc_html_e('Default', 'groove'); ?>
						</span>
					</div>
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
						// setup.php declares last_updated as a bare ISO string. Anything that
						// parses is shown in the site's date format; anything else is printed
						// as the theme wrote it.
						$updated = !empty($theme['last_updated']) ? (string) $theme['last_updated'] : '';
						$updated_ts = $updated !== '' ? strtotime($updated) : false;
						$updated_label = $updated_ts ? date_i18n(get_option('date_format'), $updated_ts) : $updated;
						$folios_url = admin_url('admin.php?page=' . \Groove\Pages\All_Folios::PAGE_ID . '&theme_id=' . $id);
						?>
						<div class="g-theme-details__panel" data-groove-theme-panel="<?php echo esc_attr($id); ?>" hidden>
							<?php
							/*
							 * The picture and the facts share a row rather than stacking. At
							 * 16/10 a full-width frame stood 400px tall and pushed the actions
							 * off a laptop screen; beside the facts it stays about the size it
							 * had on the card, which is the point of showing it at all.
							 */
							?>
							<div class="g-theme-details__top">
								<?php
								/*
								 * The theme's cover photograph fills the frame and the thumbnail
								 * sits on it: one picture answering both questions — what a new
								 * folio starts with, and the layout underneath it. The thumbnail
								 * is the image the card was showing, which is what makes the
								 * dialog read as that card opening rather than a new screen.
								 *
								 * A theme that declares no cover in setup.php gets a URL ending
								 * at the images directory, so the filename is what is tested; that
								 * theme shows its thumbnail full-frame and no inset.
								 */
								$cover_url = (string) ($theme['cover_url'] ?? '');
								$has_cover = $cover_url !== '' && substr($cover_url, -1) !== '/';
								?>
								<div class="g-theme-details__media<?php echo $has_cover ? ' g-theme-details__media--cover' : ''; ?>">
									<?php if ($has_cover): ?>
										<img src="<?php echo esc_url($cover_url); ?>" alt="" loading="lazy" />
										<span class="g-theme-details__inset">
											<img src="<?php echo esc_url($theme['thumbnail_url']); ?>" alt="" loading="lazy" />
										</span>
									<?php else: ?>
										<img src="<?php echo esc_url($theme['thumbnail_url']); ?>" alt="" loading="lazy" />
									<?php endif; ?>
								</div>

								<div class="g-theme-details__info">
									<?php if (!empty($theme['description'])): ?>
										<p class="g-theme-details__desc"><?php echo esc_html($theme['description']); ?></p>
									<?php endif; ?>

									<?php
									/*
									 * What the theme says it can do, in the theme's own setup.php —
									 * the descriptor is the only source, so an installed package
									 * carries its list with it and nothing here has to be kept in
									 * step by hand. A theme that declares nothing shows nothing.
									 */
									$features = isset($theme['features']) && is_array($theme['features'])
										? $theme['features']
										: array();
									if (!empty($features)):
										?>
										<ul class="g-theme-details__features">
											<?php foreach ($features as $feature):
												$feature_label = Themes_Manager::feature_label((string) $feature);
												if ($feature_label === '') {
													continue;
												}
												?>
												<li><?php echo esc_html($feature_label); ?></li>
											<?php endforeach; ?>
										</ul>
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
										<?php if ($updated_label !== ''): ?>
											<div class="g-theme-details__stat">
												<dt><?php esc_html_e('Updated', 'groove'); ?></dt>
												<dd><?php echo esc_html($updated_label); ?></dd>
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
								</div>
							</div>

							<?php
							/*
							 * The action row is the Add New picker's footer: the primary action
							 * keeps the trailing corner, the sample-content toggle sits with it
							 * because all it does is qualify it, and the sentence explaining what
							 * gets seeded hangs off the same info button rather than widening the
							 * row. Same classes, not a lookalike, so the two cannot drift.
							 *
							 * Removing the theme takes the leading edge, opposite the action that
							 * builds something — the same row rather than a divided strip under
							 * it, which is where wp-admin's own theme dialog keeps Delete, and
							 * which leaves the dialog ending on the thing you came here to do.
							 *
							 * The <form> is the row itself, because the toggle has to post with
							 * it. The remove trigger is a plain button inside that form; the box
							 * it opens is a sibling after the row, since it carries a form of its
							 * own and forms cannot nest.
							 */
							$seed_field_id = 'g-theme-seed-' . sanitize_key($id);
							$row_classes = 'g-folio__theme-button g-theme-details__actions';
							$can_remove = ($is_installed && $can_manage);
							?>
							<?php if ($can_create): ?>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"
									class="<?php echo esc_attr($row_classes); ?>">
									<?php wp_nonce_field('groove_create_folio_action', 'groove_nonce'); ?>
									<input type="hidden" name="action" value="groove_create_folio" />
									<input type="hidden" name="themeId" value="<?php echo esc_attr($id); ?>" />

									<?php $this->display_remove_trigger($can_remove); ?>

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
									<?php $this->display_remove_trigger($can_remove); ?>

									<button type="button" class="g-theme-preview-btn g-theme-details__preview"
										data-theme-id="<?php echo esc_attr($id); ?>">
										<?php esc_html_e('Preview', 'groove'); ?>
									</button>
								</div>
							<?php endif; ?>

							<?php if ($can_remove): ?>
								<?php /* Deleting the package files cannot be undone, so this asks in
								         place rather than firing a browser confirm over the dialog. It
								         opens where the trigger was, under the row that asked. */ ?>
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
	 * The action row's leading edge: removing the installed package.
	 *
	 * Both branches of that row need it — the one that can create a folio and
	 * the one that can only preview — and it is the same control in each, so it
	 * lives here rather than in two places that could drift apart. The caller
	 * passes its own verdict on whether there is anything to remove.
	 *
	 * @param bool $can_remove Whether this theme is an installed package the
	 *                         current user may delete.
	 */
	private function display_remove_trigger($can_remove)
	{
		if (!$can_remove) {
			return;
		}
		?>
		<button type="button" class="g-theme-details__danger-start" data-groove-theme-danger-start>
			<?php esc_html_e('Remove theme', 'groove'); ?>
		</button>
		<?php
	}

	/**
	 * Ask before an upload replaces a theme the site already has.
	 *
	 * Rendered server-side and already open, rather than shown by script: the
	 * upload is parked on the server waiting for an answer, so the question has
	 * to be answerable whether or not JavaScript ran. Both buttons are real
	 * form submissions for the same reason, and either one clears the parked
	 * file — there is no path that leaves it sitting in the temp directory
	 * because someone walked away, beyond the fifteen minutes the transient
	 * lives anyway.
	 *
	 * @param array $folio_counts Folios per theme ID, already computed for the grid.
	 */
	private function display_replace_confirmation(array $folio_counts)
	{
		$pending = get_transient('groove_theme_pending_' . get_current_user_id());

		if (empty($pending['theme_id']) || empty($pending['zip'])) {
			return;
		}

		$theme_id = (string) $pending['theme_id'];
		$existing_name = (string) ($pending['existing_name'] ?? $theme_id);
		$existing_version = (string) ($pending['existing_version'] ?? '');
		$incoming_name = (string) ($pending['incoming_name'] ?? $existing_name);
		$incoming_version = (string) ($pending['incoming_version'] ?? '');
		$folio_count = isset($folio_counts[$theme_id]) ? (int) $folio_counts[$theme_id] : 0;
		?>
		<div class="g-theme-details is-open" role="dialog" aria-modal="true"
			aria-labelledby="g-theme-replace-title">
			<div class="g-theme-details__backdrop"></div>
			<div class="g-theme-details__dialog" tabindex="-1">
				<div class="g-theme-details__header">
					<div class="g-theme-details__ident">
						<h2 id="g-theme-replace-title" class="g-theme-details__title">
							<?php esc_html_e('Replace this theme?', 'groove'); ?>
						</h2>
					</div>
					<?php /* Closing this dialog is cancelling, so the corner button submits the
					         Cancel form rather than being a second, vaguer way to say it. It is
					         the details dialog's button, which is also what stops this header
					         standing shorter than that one. */ ?>
					<button type="submit" form="g-theme-replace-cancel" class="g-theme-details__close"
						aria-label="<?php esc_attr_e('Cancel replacing this theme', 'groove'); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>

				<div class="g-theme-details__body g-theme-confirm">
					<p>
						<?php
						printf(
							/* translators: %s: name of the theme already installed, in bold */
							esc_html__('%s is already installed, and the package you uploaded derives to the same theme ID. Installing it replaces the version that is there.', 'groove'),
							'<strong>' . esc_html($existing_name) . '</strong>'
						);
						?>
					</p>

					<?php
					/*
					 * Versions are the comparison, so they are the values. The names
					 * are only worth the room when the two packages disagree about
					 * them — they usually cannot, since matching IDs means the names
					 * sanitise alike, and the sentence above has already said it.
					 */
					$names_differ = ($existing_name !== $incoming_name);
					$no_version = __('No version declared', 'groove');
					?>
					<dl class="g-theme-details__stats g-theme-confirm__facts">
						<div class="g-theme-details__stat">
							<dt><?php esc_html_e('Installed', 'groove'); ?></dt>
							<dd>
								<?php echo esc_html($existing_version !== '' ? $existing_version : $no_version); ?>
								<?php if ($names_differ): ?>
									<span class="g-theme-confirm__fact-name"><?php echo esc_html($existing_name); ?></span>
								<?php endif; ?>
							</dd>
						</div>
						<div class="g-theme-details__stat">
							<dt><?php esc_html_e('Uploaded', 'groove'); ?></dt>
							<dd>
								<?php echo esc_html($incoming_version !== '' ? $incoming_version : $no_version); ?>
								<?php if ($names_differ): ?>
									<span class="g-theme-confirm__fact-name"><?php echo esc_html($incoming_name); ?></span>
								<?php endif; ?>
							</dd>
						</div>
						<div class="g-theme-details__stat g-theme-confirm__fact--id">
							<dt><?php esc_html_e('Theme ID', 'groove'); ?></dt>
							<dd><code><?php echo esc_html($theme_id); ?></code></dd>
						</div>
					</dl>

					<?php if ($folio_count > 0): ?>
						<p class="g-theme-confirm__warning">
							<?php
							printf(
								/* translators: %s: number of folios using this theme */
								esc_html(_n(
									'%s folio uses this theme and will change appearance.',
									'%s folios use this theme and will change appearance.',
									$folio_count,
									'groove'
								)),
								esc_html(number_format_i18n($folio_count))
							);
							?>
							<a href="<?php echo esc_url(admin_url(
								'admin.php?page=' . \Groove\Pages\All_Folios::PAGE_ID . '&theme_id=' . $theme_id
							)); ?>"><?php esc_html_e('View them', 'groove'); ?></a>
						</p>
					<?php else: ?>
						<p class="g-theme-confirm__note">
							<?php esc_html_e('No folios use this theme yet, so nothing published changes.', 'groove'); ?>
						</p>
					<?php endif; ?>

					<p class="g-theme-confirm__note">
						<?php esc_html_e('The theme keeps its ID, so folios stay pointed at it. The replacement is live from the next page load.', 'groove'); ?>
					</p>

					<?php
					/*
					 * The answers ride the rule the details dialog draws above its own action
					 * row, in the body and inset by its padding — the same class, so the two
					 * cannot drift. A footer outside the body would rule the full width of
					 * the dialog, and the header's is the only edge-to-edge line in here.
					 *
					 * The Cancel form carries an ID because the header's close button submits
					 * it from outside: both exits stay plain form posts, so the question is
					 * still answerable with no JavaScript at all.
					 */
					?>
					<div class="g-folio__theme-button g-theme-details__actions g-theme-confirm__answers">
						<form id="g-theme-replace-cancel" method="post"
							action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<?php wp_nonce_field('groove_cancel_replace'); ?>
							<input type="hidden" name="action" value="groove_cancel_replace" />
							<button type="submit" class="button button-secondary">
								<?php esc_html_e('Cancel', 'groove'); ?>
							</button>
						</form>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
							<?php wp_nonce_field('groove_replace_theme'); ?>
							<input type="hidden" name="action" value="groove_replace_theme" />
							<button type="submit" class="button button-primary">
								<?php esc_html_e('Replace theme', 'groove'); ?>
							</button>
						</form>
					</div>
				</div>
			</div>
		</div>

		<script>
			// The dialog is already open in the HTML — this only adds what markup
			// cannot: the page behind it stops scrolling, the dialog takes focus so
			// the question is what a keyboard lands on, and Escape answers it the way
			// Escape answers the other dialogs on this screen. Every exit is still a
			// form post, so none of this is load-bearing.
			//
			// A click on the backdrop is deliberately not an exit: Cancel here throws
			// away an upload that has to be made again, which is more than a stray
			// click outside a dialog should be able to decide.
			(function () {
				var dialog = document.querySelector('.g-theme-details.is-open .g-theme-details__dialog');
				if (!dialog) return;

				document.body.classList.add('g-modal-open', 'g-theme-replace-open');
				dialog.focus();

				document.addEventListener('keydown', function (e) {
					if (e.key !== 'Escape' && e.key !== 'Esc') return;

					var cancel = document.getElementById('g-theme-replace-cancel');
					if (cancel) cancel.submit();
				});

				// Tab stays in here, like the details dialog: the grid behind this one
				// is full of cards that open a dialog, and none of them is an answer.
				dialog.addEventListener('keydown', function (e) {
					if (e.key !== 'Tab') return;

					var items = dialog.querySelectorAll('button, a[href]');
					if (!items.length) return;

					var first = items[0];
					var last = items[items.length - 1];

					if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
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
	 * Everything wrong with the themes on this site, in one place.
	 *
	 * Two halves that answer the same question — why is a theme not behaving?
	 * Folders that did not register at all, recomputed by the loaders on every
	 * request; and contract findings on packages that did register but will
	 * misbehave, stored on the theme's own row when it was installed.
	 *
	 * Standing, not a click outcome, which is what CLAUDE.md reserves an inline
	 * notice for. Nothing here is dismissible: every row is derived from state
	 * the loaders or the option already hold, so a row disappears the moment
	 * the thing it describes is fixed, and a dismissal could only hide a true
	 * statement. It is empty on a healthy site.
	 *
	 * @param array $folio_counts Folios per theme ID, already computed for the grid.
	 */
	private function display_theme_problems(array $folio_counts)
	{
		$skipped = Themes_Manager::get_skipped_themes();
		$installed_meta = Themes_Manager::get_installed_themes_meta();

		$flawed = array();
		foreach ($installed_meta as $theme_id => $meta) {
			if (!empty($meta['contract_warnings']) && is_array($meta['contract_warnings'])) {
				$flawed[(string) $theme_id] = $meta;
			}
		}

		if (empty($skipped) && empty($flawed)) {
			return;
		}
		?>
		<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">

			<?php if (!empty($skipped)): ?>
				<div>
					<h2 class="m-0 text-sm font-semibold text-gray-800">
						<?php
						printf(
							/* translators: %s: number of theme folders that did not load */
							esc_html(_n(
								'%s theme folder did not load.',
								'%s theme folders did not load.',
								count($skipped),
								'groove'
							)),
							esc_html(number_format_i18n(count($skipped)))
						);
						?>
					</h2>
					<p class="mt-1 mb-0 text-sm text-gray-600">
						<?php esc_html_e('They are not in the theme picker. Until now they failed in silence — a theme simply was not there, with nothing to say why.', 'groove'); ?>
					</p>
				</div>

				<ul class="m-0 p-0 list-none space-y-3">
					<?php foreach ($skipped as $record):
						$described = Themes_Manager::describe_skipped_theme($record);
						$folder = (string) ($record['folder'] ?? '');
						$is_installed = ($record['kind'] ?? '') === 'installed';
						$folio_count = isset($folio_counts[$folder]) ? (int) $folio_counts[$folder] : 0;
						?>
						<li class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm space-y-1">
							<div class="flex items-center gap-2">
								<code class="text-gray-800"><?php echo esc_html($folder); ?></code>
								<span class="g-themes-tag <?php echo $is_installed ? 'g-themes-tag--installed' : 'g-themes-tag--builtin'; ?>">
									<?php echo esc_html($is_installed ? __('Installed', 'groove') : __('Built-in', 'groove')); ?>
								</span>
							</div>
							<div class="text-gray-800"><?php echo esc_html($described->get_error_message()); ?></div>
							<?php $fix = (string) $described->get_error_data(); ?>
							<?php if ($fix !== ''): ?>
								<div class="text-gray-600"><?php echo esc_html($fix); ?></div>
							<?php endif; ?>
							<?php if ($folio_count > 0): ?>
								<div class="text-amber-900">
									<?php
									printf(
										/* translators: %s: number of folios pointing at this theme */
										esc_html(_n(
											'%s folio points at this theme and will not open.',
											'%s folios point at this theme and will not open.',
											$folio_count,
											'groove'
										)),
										esc_html(number_format_i18n($folio_count))
									);
									?>
									<a href="<?php echo esc_url(admin_url(
										'admin.php?page=' . \Groove\Pages\All_Folios::PAGE_ID . '&theme_id=' . $folder
									)); ?>"><?php esc_html_e('View them', 'groove'); ?></a>
								</div>
							<?php endif; ?>
							<?php if ($is_installed): ?>
								<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="pt-1">
									<?php wp_nonce_field('groove_uninstall_theme'); ?>
									<input type="hidden" name="action" value="groove_uninstall_theme" />
									<input type="hidden" name="theme_id" value="<?php echo esc_attr($folder); ?>" />
									<?php /* A package whose files are gone has no card, so this row is
									         the only place its stale entry can be cleared from. */ ?>
									<button type="submit" class="button button-secondary g-themes-delete-btn">
										<?php esc_html_e('Remove entry', 'groove'); ?>
									</button>
								</form>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
			</ul>
			<?php endif; ?>

			<?php foreach ($flawed as $theme_id => $meta): ?>
				<div class="space-y-1">
					<p class="m-0 text-sm font-semibold text-gray-800">
						<?php
						printf(
							/* translators: %s: theme name */
							esc_html__('"%s" is installed and renders, but the contract check found things worth fixing.', 'groove'),
							esc_html((string) ($meta['name'] ?? $theme_id))
						);
						?>
					</p>
					<ul class="m-0 pl-5 list-disc space-y-1 text-sm text-gray-700">
						<?php foreach ($meta['contract_warnings'] as $warning): ?>
							<li><?php echo esc_html((string) $warning); ?></li>
						<?php endforeach; ?>
					</ul>
					<p class="m-0 text-sm text-gray-600">
						<?php esc_html_e('None of these stops the theme rendering — they are the mistakes that produce no error when it does. The full spec ships with the plugin at themes/README.md.', 'groove'); ?>
					</p>
				</div>
			<?php endforeach; ?>
		</section>
		<?php
	}

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
			case 'replaced':
				\Groove\Toast::success(
					sprintf(
						/* translators: %s: theme name */
						__('"%s" replaced. The new version is live from the next page load.', 'groove'),
						$value
					),
					$consumed
				);
				break;
			case 'replace_cancelled':
				\Groove\Toast::info(__('Upload discarded. Nothing was changed.', 'groove'), $consumed);
				break;
			case 'confirm_replace':
				// The dialog says it all; a toast behind it would only repeat it.
				break;
			case 'uninstalled':
				\Groove\Toast::info(__('Theme removed successfully.', 'groove'), $consumed);
				break;
			case 'error':
			default:
				// Toast::failure(), not Toast::error(): an install that failed
				// is something to go and fix, and CLAUDE.md reserves the pinned
				// toggletip for exactly that. The old plain toast took the
				// explanation away with it after six seconds, on a screen whose
				// whole purpose is the action that just failed.
				$failure_key = 'groove_theme_failure_' . get_current_user_id();
				$failure = get_transient($failure_key);
				if (is_array($failure) && !empty($failure['message'])) {
					delete_transient($failure_key);
					\Groove\Toast::failure(
						(string) $failure['message'],
						isset($failure['hint']) ? (string) $failure['hint'] : '',
						isset($failure['anchor']) ? (string) $failure['anchor'] : '',
						$consumed
					);
					break;
				}

				\Groove\Toast::error(
					!empty($value) ? $value : __('An unknown error occurred.', 'groove'),
					$consumed
				);
				break;
		}
	}

	/**
	 * Redirect back to this screen reporting a failure the operator must act on.
	 *
	 * The message and its hint travel in a transient rather than the query
	 * string. Both are sentences now, not slugs, and a URL is no place for
	 * them — the old path put the whole error message in ?groove_value= and
	 * then stripped it on arrival, so it survived exactly one page load and
	 * could not be re-read by reloading. Keyed per user so two admins working
	 * at once do not read each other's.
	 */
	private function redirect_with_failure($message, $hint = '', $anchor = '')
	{
		set_transient(
			'groove_theme_failure_' . get_current_user_id(),
			array(
				'message' => (string) $message,
				'hint' => (string) $hint,
				// Empty for an uninstall: the Remove button lives inside the
				// details dialog, which the redirect has closed, so there is no
				// control left on screen to point at. The toggletip is skipped
				// and the toast reports on its own.
				'anchor' => (string) $anchor,
			),
			5 * MINUTE_IN_SECONDS
		);

		$this->redirect_with_notice('error', '');
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
