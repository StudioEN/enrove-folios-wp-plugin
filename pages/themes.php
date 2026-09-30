<?php
namespace Groove\Pages;

use Groove\Pages\Page;
use Groove\Pages\Overview;
use Groove\Menu\Menu_Manager;
use Groove\Menu\Themes_Menu_Item;
use Groove\Themes\Themes_Manager;
use Groove\Utils\Markdown;
use Groove\Utils\Request;
use Groove\Utils\Theme_Docs;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Themes admin page.
 *
 * Displays the built-in themes, and the Spec and Playbook documents that
 * describe how a theme is built. Installing a theme from a .zip was removed
 * in 0.5.1 (see Themes_Manager); third-party themes are coming back later.
 *
 * @since 1.0.0
 */
class Themes extends Page
{
	const PAGE_ID = 'groove-themes';

	/**
	 * The tab holding the theme grid. Every other tab key names a document in
	 * `Theme_Docs::DOCS`, which is what keeps a request parameter from ever
	 * reaching the filesystem as a path.
	 */
	const TAB_THEMES = 'themes';

	public function get_title()
	{
		return esc_html__('Themes', 'groove-folios');
	}

	/**
	 * The theme grid, then the two documents that describe how to build one.
	 *
	 * The docs are tabs here rather than a screen of their own because this is
	 * where someone already is when they need them: the list of folders that
	 * did not load describes a contract that, until now, only existed as a
	 * file path in a sentence.
	 */
	public function create_tabs()
	{
		return [
			self::TAB_THEMES => ['label' => esc_html__('Themes', 'groove-folios')],
			'spec' => ['label' => esc_html__('Spec', 'groove-folios')],
			'playbook' => ['label' => esc_html__('Playbook', 'groove-folios')],
		];
	}

	/**
	 * Build a URL onto one of this screen's tabs.
	 *
	 * @param string $tab_key Tab to land on.
	 * @param string $anchor  Optional heading anchor within a document.
	 * @return string
	 */
	public function tab_url($tab_key, $anchor = '')
	{
		$url = Request::admin_url(static::PAGE_ID, ['tab_key' => $tab_key]);

		return $anchor === '' ? $url : $url . '#' . $anchor;
	}

	public function __construct()
	{
		add_action('groove/menu/register', function (Menu_Manager $menu) {
			$menu->register(static::PAGE_ID, new Themes_Menu_Item($this));
		}, Overview::MENU_PRIORITY + 20);
	}

	// -----------------------------------------------------------------------
	// Display
	// -----------------------------------------------------------------------

	/**
	 * Which tab the request is asking for, falling back to the theme grid.
	 *
	 * Resolved once and read by both `display_tabs()` and `display_content()`,
	 * so the strip cannot highlight one tab while the page renders another. An
	 * unrecognised or empty `tab_key` lands on the grid *and* lights the Themes
	 * tab, rather than rendering the grid under a strip with nothing marked.
	 *
	 * @return string
	 */
	private function current_tab()
	{
		$tab_key = Request::key('tab_key');

		return Theme_Docs::exists($tab_key) ? $tab_key : self::TAB_THEMES;
	}

	public function display_content()
	{
		$tab_key = $this->current_tab();

		if ($tab_key !== self::TAB_THEMES) {
			$this->display_tab_doc($tab_key);

			return;
		}

		$this->display_tab_themes();
	}

	public function display_tabs()
	{
		$tabs = $this->get_tabs();
		$tab_key = $this->current_tab();
		?>
		<nav class="nav-tab-wrapper wp-clearfix" aria-label="<?php esc_attr_e('Themes tabs', 'groove-folios'); ?>">
			<?php
			foreach ($tabs as $tab_id => $tab) {
				$active_class = $tab_key === $tab_id ? ' nav-tab-active' : '';
				$tab_url = $this->tab_url($tab_id);
				echo '<a href="' . esc_url($tab_url) . '" class="nav-tab' . esc_attr($active_class) . '">'
					. esc_html($tab['label']) . '</a>';
			}
			?>
		</nav>
		<?php
	}

	/**
	 * Render one of the documents that ship with the plugin.
	 *
	 * The file is the only copy — there is no second version of this text in
	 * `pages/` for an edit to miss, which is the same reason a theme's
	 * description is read from its own `setup.php`. What the screen adds is a
	 * path to the file, because the next thing someone who disagrees with it
	 * wants to know is where to change it.
	 *
	 * @param string $doc_key A key in Theme_Docs::DOCS, already validated.
	 */
	private function display_tab_doc($doc_key)
	{
		$markdown = Theme_Docs::read($doc_key);
		$relative = Theme_Docs::DOCS[$doc_key];
		?>
			<section class="g-docs">
				<?php if ($markdown === ''): ?>
					<p class="g-docs__missing">
						<?php
						printf(
							/* translators: %s: path to the documentation file, relative to the plugin folder */
							esc_html__('This document is not on disk. It ships with the plugin at %s, and a deployment that copies only PHP files leaves this tab with nothing to render.', 'groove-folios'),
							'<code>' . esc_html($relative) . '</code>'
						);
						?>
					</p>
				<?php else: ?>
					<?php
					/* The download sits on the line that names the file, so what it
					   downloads is never in doubt. A plain same-origin link: the file
					   is already public in the plugin folder, and `download` keeps its
					   own name, which is the name the two documents use for each other. */
					$file_name = basename($relative);
					/* translators: %s: file name of the documentation, e.g. README.md */
					$download_label = sprintf(__('Download %s', 'groove-folios'), $file_name);
					?>
					<div class="g-docs__source">
						<p class="g-docs__source-text">
							<?php
							printf(
								/* translators: %s: path to the documentation file, relative to the plugin folder */
								esc_html__('Rendered from %s, which ships with the plugin. Edit that file to change this page.', 'groove-folios'),
								'<code>' . esc_html($relative) . '</code>'
							);
							?>
						</p>
						<a href="<?php echo esc_url(GROOVE_URL . $relative); ?>"
							download="<?php echo esc_attr($file_name); ?>"
							class="button button-secondary g-page-header__icon-button g-tooltip-button g-docs__download"
							aria-label="<?php echo esc_attr($download_label); ?>"
							data-tooltip-text="<?php esc_attr_e('Download', 'groove-folios'); ?>"
							data-tooltip-keeps-label>
							<span class="dashicons dashicons-download" aria-hidden="true"></span>
						</a>
					</div>
					<?php
					/* One $args for both passes. `outline()` re-reads the source rather
					   than watching `render()` work, so they agree only while they are
					   given the same options — bin/check-docs.php asserts they do. */
					$args = ['doc_links' => $this->doc_link_map()];
					?>
					<div class="g-docs__layout">
						<?php $this->display_doc_contents(Markdown::outline($markdown, $args)); ?>
						<div class="g-docs__body">
							<?php
							/* Escaped again at the echo, against the fixed tag set render()
							   emits (Markdown::allowed_html()); wp_kses_post() would drop the
							   task-list checkboxes. */
							echo wp_kses(Markdown::render($markdown, $args), Markdown::allowed_html());
							?>
						</div>
					</div>
				<?php endif; ?>
			</section>
		<?php
		// The contents rail's current-section marker is in assets/js/groove-themes.js.
	}

	/**
	 * The contents rail: the document's top-level sections, and nothing else.
	 *
	 * Only the `##` headings. Adding the `###` subsections under them took the
	 * playbook's rail from eleven entries to thirty-five, which is a second copy
	 * of the document rather than a way to find your place in it — and a list you
	 * have to scan is not doing the job a contents list exists to do. The
	 * subsections are still headings on the page, still anchored, still linkable.
	 *
	 * The level is the rendered one, already demoted by `heading_offset`, which
	 * is why a `##` section is h3 here rather than h2.
	 *
	 * @param array $outline From Markdown::outline().
	 */
	private function display_doc_contents(array $outline)
	{
		$entries = array_values(array_filter($outline, function ($heading) {
			return $heading['level'] === 3;
		}));

		if (count($entries) < 2) {
			// One section is not a contents list, and none at all is not a document.
			return;
		}
		?>
		<?php /* Named by the heading it already shows rather than by an aria-label
		         repeating the same word, so a screen reader announces the rail once. */ ?>
		<nav class="g-docs__toc" aria-labelledby="g-docs-toc-title">
			<p class="g-docs__toc-title" id="g-docs-toc-title"><?php esc_html_e('Contents', 'groove-folios'); ?></p>
			<ul class="g-docs__toc-list">
				<?php foreach ($entries as $entry): ?>
					<li class="g-docs__toc-item">
						<a class="g-docs__toc-link" href="#<?php echo esc_attr($entry['anchor']); ?>">
							<?php echo esc_html($entry['text']); ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
		<?php
	}

	/**
	 * Filename => URL, so the cross-references the two documents already make to
	 * each other become links between the two tabs instead of dead file paths.
	 */
	private function doc_link_map()
	{
		$map = [];

		foreach (Theme_Docs::DOCS as $key => $relative) {
			$map[basename($relative)] = $this->tab_url($key);
		}

		return $map;
	}

	private function display_tab_themes()
	{
		$all_themes = Themes_Manager::get_all_themes();
		$folio_counts = $this->get_folio_counts_by_theme();
		$total_themes = count($all_themes);
		$default_theme_id = (string) get_option('groove_default_theme_id', '');
		?>
		<div class="space-y-4">

			<?php $this->display_theme_problems($folio_counts); ?>

			<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
				<div>
					<h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Your Themes', 'groove-folios'); ?></h2>
					<p class="mt-1 mb-0 text-sm text-gray-600">
						<?php
						/* The three summary tiles that used to sit above the grid counted
						   what the grid already shows, so the counts moved into this line
						   and the tiles came out. */
						if ($total_themes === 0) {
							esc_html_e('No themes are available yet.', 'groove-folios');
						} else {
							printf(
								esc_html(
									/* translators: %s: number of themes */
									_n(
										'%s theme available. Select it for details.',
										'%s themes available. Select one for details.',
										$total_themes,
										'groove-folios'
									)
								),
								esc_html(number_format_i18n($total_themes))
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
									? __('No themes loaded. Every theme folder on this site failed to register — see above.', 'groove-folios')
									: __('No themes are available.', 'groove-folios')
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
								data-theme-badge="<?php echo esc_attr__('Built-in', 'groove-folios'); ?>"
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
													_n('%s folio', '%s folios', $folio_count, 'groove-folios'),
													number_format_i18n($folio_count)
												)
												: __('Unused', 'groove-folios')
										);
										?>
									</span>
								</span>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</section>

			<?php /* An "Install a Theme" panel took a .zip here until 0.5.1. It copied
			         the package's PHP into wp-content/groove-themes/, which WordPress.org
			         does not allow a plugin to do, so it came out with the installer. */ ?>
			<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
				<h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Third-Party Themes', 'groove-folios'); ?></h2>
				<p class="mt-1 mb-0 text-sm text-gray-600">
					<?php esc_html_e('Support for themes from other authors is coming soon. Until then, Groove Folios comes with the themes above.', 'groove-folios'); ?>
				</p>
			</section>
		</div>

		<?php $this->display_details_modal($all_themes, $folio_counts); ?>
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
	 * @param array  $folio_counts     Folio count keyed by theme ID.
	 */
	private function display_details_modal($all_themes, $folio_counts)
	{
		if (empty($all_themes)) {
			return;
		}

		$can_create = current_user_can('edit_posts');
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
							<?php esc_html_e('Default', 'groove-folios'); ?>
						</span>
					</div>
					<button type="button" class="g-theme-details__close" data-groove-theme-close
						aria-label="<?php esc_attr_e('Close theme details', 'groove-folios'); ?>">
						<span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
					</button>
				</div>
				<div class="g-theme-details__body">
					<?php foreach ($all_themes as $id => $theme):
						$folio_count = isset($folio_counts[$id]) ? (int) $folio_counts[$id] : 0;
						$sample = Themes_Manager::get_sample_content((string) $id);
						// setup.php declares last_updated as a bare ISO string. Anything that
						// parses is shown in the site's date format; anything else is printed
						// as the theme wrote it.
						$updated = !empty($theme['last_updated']) ? (string) $theme['last_updated'] : '';
						$updated_ts = $updated !== '' ? strtotime($updated) : false;
						$updated_label = $updated_ts ? date_i18n(get_option('date_format'), $updated_ts) : $updated;
						$folios_url = Request::admin_url(All_Folios::PAGE_ID, ['theme_id' => (string) $id]);
						?>
						<div class="g-theme-details__panel" data-groove-theme-panel="<?php echo esc_attr($id); ?>" hidden>
							<div class="g-dialog-screen" data-groove-theme-screen>
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
									<?php /* Thumbnail and its Preview link, in the Add New picker's own wrap:
									         it is the same control on the same picture, so it reads where the
									         eye already learned to find it and the action row is left to the
									         two things that change something. */ ?>
									<div class="g-folio__theme-card-wrap">
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
										<button type="button" class="g-theme-preview-btn" data-theme-id="<?php echo esc_attr($id); ?>"
											aria-label="<?php echo esc_attr(sprintf(
												/* translators: %s: theme name */
												__('Preview %s theme', 'groove-folios'),
												$theme['name']
											)); ?>">
											<?php esc_html_e('Preview', 'groove-folios'); ?>
										</button>
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
												<dt><?php esc_html_e('Folios', 'groove-folios'); ?></dt>
												<dd>
													<?php if ($folio_count > 0): ?>
														<a href="<?php echo esc_url($folios_url); ?>">
															<?php echo esc_html(number_format_i18n($folio_count)); ?>
														</a>
													<?php else: ?>
														<span class="g-theme-details__muted"><?php esc_html_e('None yet', 'groove-folios'); ?></span>
													<?php endif; ?>
												</dd>
											</div>
											<?php if (!empty($theme['author'])): ?>
												<div class="g-theme-details__stat">
													<dt><?php esc_html_e('Author', 'groove-folios'); ?></dt>
													<dd><?php echo esc_html($theme['author']); ?></dd>
												</div>
											<?php endif; ?>
											<?php if ($updated_label !== ''): ?>
												<div class="g-theme-details__stat">
													<dt><?php esc_html_e('Updated', 'groove-folios'); ?></dt>
													<dd><?php echo esc_html($updated_label); ?></dd>
												</div>
											<?php endif; ?>
											<div class="g-theme-details__stat">
												<dt><?php esc_html_e('Theme ID', 'groove-folios'); ?></dt>
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
								 * The <form> is the row itself, because the toggle has to post with it.
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

										<button type="submit" class="button button-primary">
											<?php esc_html_e('Create a folio', 'groove-folios'); ?>
										</button>
									</form>
								<?php endif; ?>
							</div>

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

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- A GROUP BY count no WP API provides; fixed SQL with no input, run once per Themes screen view, and it must reflect installs and removals immediately.
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
	 * Everything wrong with the themes on this site, in one place.
	 *
	 * Folders that did not register, recomputed by the loaders on every request.
	 *
	 * Standing, not a click outcome, which is what CLAUDE.md reserves an inline
	 * notice for. Nothing here is dismissible: every row is derived from state
	 * the loaders already hold, so a row disappears the moment
	 * the thing it describes is fixed, and a dismissal could only hide a true
	 * statement. It is empty on a healthy site.
	 *
	 * @param array $folio_counts Folios per theme ID, already computed for the grid.
	 */
	private function display_theme_problems(array $folio_counts)
	{
		$skipped = Themes_Manager::get_skipped_themes();

		if (empty($skipped)) {
			return;
		}
		?>
		<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">

				<div>
					<h2 class="m-0 text-sm font-semibold text-gray-800">
						<?php
						printf(
							/* translators: %s: number of theme folders that did not load */
							esc_html(_n(
								'%s theme folder did not load.',
								'%s theme folders did not load.',
								count($skipped),
								'groove-folios'
							)),
							esc_html(number_format_i18n(count($skipped)))
						);
						?>
					</h2>
					<p class="mt-1 mb-0 text-sm text-gray-600">
						<?php esc_html_e('They are not in the theme picker. Until now they failed in silence — a theme simply was not there, with nothing to say why.', 'groove-folios'); ?>
						<?php /* Each row below names a fault and a fix in a sentence. The
						         playbook's section 9 is a table of every fault a loader can
						         record, which is the thing to read when the sentence is not
						         enough — so it is linked once here rather than repeated on
						         nine rows that would all point at the same table. */ ?>
						<a class="g-docs__link" href="<?php echo esc_url(
							$this->tab_url(Theme_Docs::SKIPPED_THEME_DOC, Theme_Docs::SKIPPED_THEME_ANCHOR)
						); ?>"><?php esc_html_e('What each fault means', 'groove-folios'); ?></a>
					</p>
				</div>

				<ul class="m-0 p-0 list-none space-y-3">
					<?php foreach ($skipped as $record):
						$described = Themes_Manager::describe_skipped_theme($record);
						$folder = (string) ($record['folder'] ?? '');
						$folio_count = isset($folio_counts[$folder]) ? (int) $folio_counts[$folder] : 0;
						?>
						<li class="rounded-md border border-gray-200 bg-gray-50/50 p-3 text-sm space-y-1">
							<div class="flex items-center gap-2">
								<code class="text-gray-800"><?php echo esc_html($folder); ?></code>
								<span class="g-themes-tag g-themes-tag--builtin"><?php esc_html_e('Built-in', 'groove-folios'); ?></span>
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
											'groove-folios'
										)),
										esc_html(number_format_i18n($folio_count))
									);
									?>
									<a href="<?php echo esc_url(Request::admin_url(All_Folios::PAGE_ID, ['theme_id' => (string) $folder])); ?>"><?php esc_html_e('View them', 'groove-folios'); ?></a>
								</div>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
		</section>
		<?php
	}
}
