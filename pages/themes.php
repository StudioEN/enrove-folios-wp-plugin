<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Themes_Menu_Item;
use Groove\Themes\Themes_Manager;

if ( ! defined( 'ABSPATH' ) ) {
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
class Themes extends Page {
	const PAGE_ID = 'groove-themes';

	public function get_title() {
		return esc_html__( 'Themes', 'groove' );
	}

	public function create_tabs() {
		return [];
	}

	public function __construct() {
		// Register POST action handlers (both priv — themes require manage_options).
		$this->add_post_action( 'groove_install_theme', 'handle_install' );
		$this->add_post_action( 'groove_uninstall_theme', 'handle_uninstall' );

		add_action( 'groove/menu/register', function ( Menu_Manager $menu ) {
			$menu->register( static::PAGE_ID, new Themes_Menu_Item( $this ) );
		}, Overview::MENU_PRIORITY + 20 );
	}

	// -----------------------------------------------------------------------
	// Install handler
	// -----------------------------------------------------------------------

	/**
	 * Handle a theme ZIP upload and install it.
	 * Hooked to admin_post_groove_install_theme.
	 */
	public function handle_install() {
		check_admin_referer( 'groove_install_theme' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to install themes.', 'groove' ) );
		}

		if ( empty( $_FILES['theme_zip'] ) || $_FILES['theme_zip']['error'] !== UPLOAD_ERR_OK ) {
			$this->redirect_with_notice( 'error', 'upload_failed' );
			return;
		}

		$result = Themes_Manager::install_theme_from_zip( $_FILES['theme_zip']['tmp_name'] );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', urlencode( $result->get_error_message() ) );
			return;
		}

		$this->redirect_with_notice( 'success', urlencode( $result ) );
	}

	// -----------------------------------------------------------------------
	// Uninstall handler
	// -----------------------------------------------------------------------

	/**
	 * Handle a theme uninstall request.
	 * Hooked to admin_post_groove_uninstall_theme.
	 */
	public function handle_uninstall() {
		check_admin_referer( 'groove_uninstall_theme' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to remove themes.', 'groove' ) );
		}

		$theme_id = isset( $_POST['theme_id'] ) ? sanitize_key( $_POST['theme_id'] ) : '';

		if ( empty( $theme_id ) ) {
			$this->redirect_with_notice( 'error', 'missing_theme_id' );
			return;
		}

		$result = Themes_Manager::uninstall_theme( $theme_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', urlencode( $result->get_error_message() ) );
			return;
		}

		$this->redirect_with_notice( 'uninstalled', $theme_id );
	}

	// -----------------------------------------------------------------------
	// Display
	// -----------------------------------------------------------------------

	public function display_content() {
		$all_themes       = Themes_Manager::get_all_themes();
		$installed_ids    = array_keys( Themes_Manager::get_installed_themes_meta() );
		$notice_type      = isset( $_GET['groove_notice'] ) ? sanitize_key( $_GET['groove_notice'] ) : '';
		$notice_value     = isset( $_GET['groove_value'] ) ? sanitize_text_field( urldecode( $_GET['groove_value'] ) ) : '';
		?>
		<div class="g-themes-page">

			<?php $this->display_notice( $notice_type, $notice_value ); ?>

			<!-- ── Installed Themes ───────────────────────────────── -->
			<div class="g-themes-section">
				<div class="g-themes-section-header">
					<h2 class="g-themes-section-title">
						<?php esc_html_e( 'Available Themes', 'groove' ); ?>
						<span class="g-themes-count"><?php echo count( $all_themes ); ?></span>
					</h2>
				</div>

				<?php if ( empty( $all_themes ) ) : ?>
					<div class="g-themes-empty">
						<p><?php esc_html_e( 'No themes installed yet. Upload a theme package below.', 'groove' ); ?></p>
					</div>
				<?php else : ?>
					<div class="g-themes-grid">
						<?php foreach ( $all_themes as $id => $theme ) :
							$is_installed = in_array( $id, $installed_ids, true );
							$tag = $is_installed ? 'Installed' : 'Built-in';
							$tag_class = $is_installed ? 'g-themes-tag--installed' : 'g-themes-tag--builtin';
						?>
							<div class="g-themes-card">
								<div class="g-themes-card-thumb" style="background-image: url('<?php echo esc_url( $theme['thumbnail_url'] ); ?>')"></div>
								<div class="g-themes-card-body">
									<div class="g-themes-card-meta">
										<span class="g-themes-card-name"><?php echo esc_html( $theme['name'] ); ?></span>
										<span class="g-themes-tag <?php echo esc_attr( $tag_class ); ?>"><?php echo esc_html( $tag ); ?></span>
									</div>
									<p class="g-themes-card-id">ID: <code><?php echo esc_html( $id ); ?></code></p>
									<?php if ( $is_installed ) : ?>
										<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="g-themes-delete-form">
											<?php wp_nonce_field( 'groove_uninstall_theme' ); ?>
											<input type="hidden" name="action" value="groove_uninstall_theme" />
											<input type="hidden" name="theme_id" value="<?php echo esc_attr( $id ); ?>" />
											<button type="submit" class="g-themes-delete-btn"
												onclick="return confirm('<?php esc_attr_e( 'Delete this theme? This cannot be undone.', 'groove' ); ?>')">
												<?php esc_html_e( 'Remove', 'groove' ); ?>
											</button>
										</form>
									<?php endif; ?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<!-- ── Upload a Theme Package ─────────────────────────── -->
			<div class="g-themes-section g-themes-upload-section">
				<div class="g-themes-section-header">
					<h2 class="g-themes-section-title"><?php esc_html_e( 'Install a Theme', 'groove' ); ?></h2>
					<p class="g-themes-section-desc">
						<?php esc_html_e( 'Upload a Groove theme package (.zip) provided by the Groove team or a trusted theme author.', 'groove' ); ?>
					</p>
				</div>

				<form
					method="post"
					action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
					enctype="multipart/form-data"
					class="g-themes-upload-form"
					id="groove-theme-upload-form"
				>
					<?php wp_nonce_field( 'groove_install_theme' ); ?>
					<input type="hidden" name="action" value="groove_install_theme" />

					<div class="g-themes-dropzone" id="groove-theme-dropzone">
						<div class="g-themes-dropzone-icon">📦</div>
						<p class="g-themes-dropzone-label">
							<?php esc_html_e( 'Drag & drop your theme .zip here', 'groove' ); ?>
						</p>
						<p class="g-themes-dropzone-sub">
							<?php esc_html_e( 'or', 'groove' ); ?>
						</p>
						<label for="theme_zip" class="g-folio__button g-folio__button-primary g-themes-file-label">
							<?php esc_html_e( 'Choose File', 'groove' ); ?>
						</label>
						<input
							type="file"
							name="theme_zip"
							id="theme_zip"
							accept=".zip"
							class="g-themes-file-input"
						/>
						<p class="g-themes-file-name" id="groove-theme-filename">
							<?php esc_html_e( 'No file chosen', 'groove' ); ?>
						</p>
					</div>

					<div class="g-themes-upload-actions">
						<button
							type="submit"
							class="g-folio__button g-folio__button-primary"
							id="groove-theme-submit"
							disabled
						>
							<?php esc_html_e( 'Install Theme', 'groove' ); ?>
						</button>
					</div>
				</form>

				<div class="g-themes-package-info">
					<h3><?php esc_html_e( 'Package format', 'groove' ); ?></h3>
					<p><?php esc_html_e( 'A valid Groove theme package is a .zip file with this structure:', 'groove' ); ?></p>
					<pre class="g-themes-code">my-theme.zip
├── theme-info.json     ← required
├── cover.php           ← folio cover template
├── page.php            ← folio inner-page template
└── assets/
    ├── thumbnail.png
    ├── cover.png
    └── logo.png</pre>
					<p><?php esc_html_e( 'The theme name in theme-info.json becomes its ID automatically.', 'groove' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		(function() {
			var dropzone  = document.getElementById('groove-theme-dropzone');
			var input     = document.getElementById('theme_zip');
			var filename  = document.getElementById('groove-theme-filename');
			var submit    = document.getElementById('groove-theme-submit');

			function setFile(file) {
				if (!file) return;
				filename.textContent = file.name;
				submit.disabled = false;
				dropzone.classList.add('g-themes-dropzone--has-file');
			}

			input.addEventListener('change', function() {
				setFile(this.files[0]);
			});

			dropzone.addEventListener('dragover', function(e) {
				e.preventDefault();
				dropzone.classList.add('g-themes-dropzone--over');
			});
			dropzone.addEventListener('dragleave', function() {
				dropzone.classList.remove('g-themes-dropzone--over');
			});
			dropzone.addEventListener('drop', function(e) {
				e.preventDefault();
				dropzone.classList.remove('g-themes-dropzone--over');
				var file = e.dataTransfer.files[0];
				if (file && file.name.endsWith('.zip')) {
					// Attach to the real file input via DataTransfer.
					var dt = new DataTransfer();
					dt.items.add(file);
					input.files = dt.files;
					setFile(file);
				} else {
					alert('Please drop a .zip file.');
				}
			});
		})();
		</script>
		<?php
	}

	// -----------------------------------------------------------------------
	// Helpers
	// -----------------------------------------------------------------------

	private function display_notice( $type, $value ) {
		if ( empty( $type ) ) {
			return;
		}

		switch ( $type ) {
			case 'success':
				$message = sprintf(
					/* translators: %s: theme name */
					esc_html__( '"%s" installed successfully.', 'groove' ),
					esc_html( $value )
				);
				$class = 'g-themes-notice--success';
				break;
			case 'uninstalled':
				$message = esc_html__( 'Theme removed successfully.', 'groove' );
				$class   = 'g-themes-notice--success';
				break;
			case 'error':
			default:
				$message = ! empty( $value )
					? esc_html( $value )
					: esc_html__( 'An unknown error occurred.', 'groove' );
				$class = 'g-themes-notice--error';
				break;
		}
		?>
		<div class="g-themes-notice <?php echo esc_attr( $class ); ?>">
			<?php echo $message; // Already escaped above. ?>
		</div>
		<?php
	}

	private function redirect_with_notice( $type, $value ) {
		$url = add_query_arg( [
			'page'           => static::PAGE_ID,
			'groove_notice'  => $type,
			'groove_value'   => $value,
		], admin_url( 'admin.php' ) );

		wp_safe_redirect( $url );
		exit;
	}
}
