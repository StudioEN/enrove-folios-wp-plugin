<?php
namespace Groove\Pages;

use Groove\Insights\Manager as Insights_Manager;
use Groove\Menu\Insights_Menu_Item;
use Groove\Menu\Menu_Manager;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
	exit;
}

class Insights extends Page
{
	const PAGE_ID = 'groove-insights';

	private $manager;

	public function __construct()
	{
		$this->manager = Insights_Manager::instance();

		$this->add_post_action('groove_insights_run_now', 'handle_run_now');
		$this->add_post_action('groove_insights_create_master_folio', 'handle_create_master_folio');

		add_action('groove/menu/register', function (Menu_Manager $menu) {
			$menu->register(static::PAGE_ID, new Insights_Menu_Item($this));
		}, Overview::MENU_PRIORITY + 18);
	}

	public function get_title()
	{
		return esc_html__('Insights', 'groove');
	}

	public function create_tabs()
	{
		return [];
	}

	public function handle_run_now()
	{
		check_admin_referer('groove_insights_run_now', 'groove_nonce');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to run Insights.', 'groove'));
		}

		$run_id = Insights_Manager::create_run([
			'trigger_source' => 'manual',
		]);
		if (is_wp_error($run_id)) {
			$this->redirect_with_notice('error', 'run_create_failed');
		}

		$dispatched = $this->manager->dispatch_run($run_id);
		if (is_wp_error($dispatched)) {
			$this->redirect_with_notice('error', 'run_dispatch_failed', (int) $run_id);
		}

		$this->redirect_with_notice('success', 'run_dispatched', (int) $run_id);
	}

	public function handle_create_master_folio()
	{
		check_admin_referer('groove_insights_create_master_folio', 'groove_nonce');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to create the Insights folio.', 'groove'));
		}

		$folio_id = $this->manager->ensure_master_folio();
		if (is_wp_error($folio_id)) {
			$this->redirect_with_notice('error', 'master_folio_failed');
		}

		$this->redirect_with_notice('success', 'master_folio_ready', (int) $folio_id);
	}

	private function get_notice_message($type, $value, $object_id)
	{
		if ($type === 'success' && $value === 'run_dispatched') {
			return sprintf(
				/* translators: %d: run id */
				esc_html__('Run #%d was queued and sent to the worker.', 'groove'),
				$object_id
			);
		}

		if ($type === 'success' && $value === 'master_folio_ready') {
			return sprintf(
				/* translators: %d: folio id */
				esc_html__('Master folio is ready. Folio ID: %d.', 'groove'),
				$object_id
			);
		}

		if ($value === 'run_dispatch_failed') {
			return esc_html__('Run was created, but the worker dispatch failed. Check Insights settings and the latest run record.', 'groove');
		}

		if ($value === 'master_folio_failed') {
			return esc_html__('Unable to create the master folio.', 'groove');
		}

		return esc_html__('Unable to complete the requested action.', 'groove');
	}

	private function redirect_with_notice($type, $value, $object_id = 0)
	{
		$url = add_query_arg([
			'page' => static::PAGE_ID,
			'groove_notice' => sanitize_key($type),
			'groove_value' => sanitize_key($value),
			'groove_object_id' => (int) $object_id,
		], admin_url('admin.php'));

		wp_safe_redirect($url);
		exit;
	}

	private function render_notice()
	{
		$type = isset($_GET['groove_notice']) ? sanitize_key(wp_unslash($_GET['groove_notice'])) : '';
		$value = isset($_GET['groove_value']) ? sanitize_key(wp_unslash($_GET['groove_value'])) : '';
		$object_id = isset($_GET['groove_object_id']) ? (int) wp_unslash($_GET['groove_object_id']) : 0;

		if ($type === '') {
			return;
		}

		$class = $type === 'success' ? 'notice notice-success inline' : 'notice notice-error inline';
		echo '<div class="' . esc_attr($class) . '"><p>' . esc_html($this->get_notice_message($type, $value, $object_id)) . '</p></div>';
	}

	private function get_status_badge($status)
	{
		$status = sanitize_key((string) $status);
		$map = [
			'queued' => 'bg-gray-100 text-gray-800',
			'dispatching' => 'bg-amber-100 text-amber-800',
			'dispatched' => 'bg-blue-100 text-blue-800',
			'completed' => 'bg-green-100 text-green-800',
			'failed' => 'bg-red-100 text-red-800',
		];
		$classes = isset($map[$status]) ? $map[$status] : 'bg-gray-100 text-gray-800';

		return '<span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold ' . esc_attr($classes) . '">' . esc_html(ucfirst($status ?: 'unknown')) . '</span>';
	}

	private function render_configuration_card()
	{
		$endpoint = Insights_Manager::get_worker_endpoint();
		$secret = Insights_Manager::get_shared_secret();
		$email = Insights_Manager::get_notification_email();
		$master_folio_id = Insights_Manager::get_master_folio_id();
		$master_folio_url = $master_folio_id > 0 ? admin_url('admin.php?page=groove-folio&folio_id=' . $master_folio_id) : '';
		?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
  <div>
    <h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Configuration', 'groove'); ?></h2>
    <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Groove handles orchestration and publishing. A separate worker should perform the research and media generation.', 'groove'); ?></p>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 text-sm text-gray-700">
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3">
      <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold"><?php esc_html_e('Worker Endpoint', 'groove'); ?></div>
      <div class="mt-1"><?php echo $endpoint !== '' ? esc_html($endpoint) : esc_html__('Not configured', 'groove'); ?></div>
    </div>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3">
      <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold"><?php esc_html_e('Shared Secret', 'groove'); ?></div>
      <div class="mt-1"><?php echo $secret !== '' ? esc_html__('Configured', 'groove') : esc_html__('Missing', 'groove'); ?></div>
    </div>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3">
      <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold"><?php esc_html_e('Notification Email', 'groove'); ?></div>
      <div class="mt-1"><?php echo esc_html($email !== '' ? $email : __('Not configured', 'groove')); ?></div>
    </div>
    <div class="rounded-md border border-gray-200 bg-gray-50/50 p-3">
      <div class="text-xs uppercase tracking-wide text-gray-500 font-semibold"><?php esc_html_e('Master Folio', 'groove'); ?></div>
      <div class="mt-1">
        <?php if ($master_folio_url !== ''): ?>
          <a href="<?php echo esc_url($master_folio_url); ?>" class="text-indigo-600 hover:text-indigo-500"><?php echo esc_html__('Open master folio', 'groove'); ?></a>
        <?php else: ?>
          <?php esc_html_e('Not created yet', 'groove'); ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <p class="m-0 text-xs text-gray-500">
    <?php esc_html_e('Use a real system cron for Friday 7:00 AM ET scheduling. WordPress should not own the schedule for this workflow.', 'groove'); ?>
  </p>
</section>
<?php
	}

	private function render_actions_card()
	{
		$settings_url = admin_url('admin.php?page=groove-settings&tab_key=insights');
		?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4 space-y-4">
  <div>
    <h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Actions', 'groove'); ?></h2>
    <p class="mt-1 mb-0 text-sm text-gray-600"><?php esc_html_e('Create the publication shell, trigger a run manually, or adjust worker settings.', 'groove'); ?></p>
  </div>

  <div class="flex flex-wrap gap-3">
    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('groove_insights_create_master_folio', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="groove_insights_create_master_folio" />
      <button type="submit" class="button button-secondary"><?php esc_html_e('Create Master Folio', 'groove'); ?></button>
    </form>

    <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="post">
      <?php wp_nonce_field('groove_insights_run_now', 'groove_nonce'); ?>
      <input type="hidden" name="action" value="groove_insights_run_now" />
      <button type="submit" class="button button-primary"><?php esc_html_e('Run Insights Now', 'groove'); ?></button>
    </form>

    <a href="<?php echo esc_url($settings_url); ?>" class="button button-secondary"><?php esc_html_e('Open Insights Settings', 'groove'); ?></a>
  </div>
</section>
<?php
	}

	private function render_runs_card()
	{
		$runs = $this->manager->get_recent_runs();
		?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
  <div class="flex items-center justify-between mb-4">
    <h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Recent Runs', 'groove'); ?></h2>
    <span class="text-xs text-gray-500"><?php esc_html_e('Latest worker callbacks and publish attempts', 'groove'); ?></span>
  </div>

  <?php if (!empty($runs)): ?>
  <div class="overflow-x-auto">
    <table class="min-w-full border-collapse">
      <thead class="bg-gray-50 border-b border-gray-200">
        <tr>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Run', 'groove'); ?></th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Status', 'groove'); ?></th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Requested', 'groove'); ?></th>
          <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500"><?php esc_html_e('Issue', 'groove'); ?></th>
        </tr>
      </thead>
      <tbody class="bg-white divide-y divide-gray-100">
        <?php foreach ($runs as $run): ?>
          <?php
          $status = get_post_meta($run->ID, 'run_status', true);
          $requested_at = get_post_meta($run->ID, 'requested_at', true);
          $issue_page_id = (int) get_post_meta($run->ID, 'issue_page_id', true);
          $error_message = (string) get_post_meta($run->ID, 'error_message', true);
          $issue_link = $issue_page_id > 0 ? admin_url('post.php?post=' . $issue_page_id . '&action=edit') : '';
          ?>
        <tr class="hover:bg-gray-50/50 align-top">
          <td class="px-4 py-3 text-sm text-gray-700">#<?php echo esc_html((string) $run->ID); ?></td>
          <td class="px-4 py-3 text-sm text-gray-700">
            <?php echo wp_kses_post($this->get_status_badge($status)); ?>
            <?php if ($error_message !== ''): ?>
            <div class="mt-2 text-xs text-red-700"><?php echo esc_html($error_message); ?></div>
            <?php endif; ?>
          </td>
          <td class="px-4 py-3 text-sm text-gray-700 whitespace-nowrap"><?php echo esc_html($requested_at !== '' ? $requested_at : $run->post_date); ?></td>
          <td class="px-4 py-3 text-sm text-gray-700">
            <?php if ($issue_link !== ''): ?>
              <a href="<?php echo esc_url($issue_link); ?>" class="text-indigo-600 hover:text-indigo-500"><?php esc_html_e('Open issue page', 'groove'); ?></a>
            <?php else: ?>
              <?php esc_html_e('Not published yet', 'groove'); ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
  <p class="text-sm text-gray-600"><?php esc_html_e('No runs have been recorded yet.', 'groove'); ?></p>
  <?php endif; ?>
</section>
<?php
	}

	private function render_issues_card()
	{
		$issues = $this->manager->get_recent_issues();
		?>
<section class="bg-white border border-gray-200 rounded-lg shadow-sm p-4">
  <div class="flex items-center justify-between mb-4">
    <h2 class="m-0 text-sm font-semibold text-gray-800"><?php esc_html_e('Recent Issues', 'groove'); ?></h2>
    <span class="text-xs text-gray-500"><?php esc_html_e('Published under the master Insights folio', 'groove'); ?></span>
  </div>

  <?php if (!empty($issues)): ?>
  <ul class="space-y-3 m-0">
    <?php foreach ($issues as $issue): ?>
      <?php $view_url = Utils::get_folio_permalink_by_id($issue->ID); ?>
      <li class="rounded-md border border-gray-200 bg-gray-50/50 px-4 py-3">
        <div class="flex items-center justify-between gap-4">
          <div>
            <div class="text-sm font-medium text-gray-900"><?php echo esc_html(get_the_title($issue)); ?></div>
            <div class="text-xs text-gray-500 mt-1"><?php echo esc_html(get_the_date(get_option('date_format'), $issue)); ?></div>
          </div>
          <div class="flex gap-3 text-sm">
            <a href="<?php echo esc_url(admin_url('post.php?post=' . $issue->ID . '&action=edit')); ?>" class="text-indigo-600 hover:text-indigo-500"><?php esc_html_e('Edit', 'groove'); ?></a>
            <a href="<?php echo esc_url($view_url); ?>" class="text-indigo-600 hover:text-indigo-500"><?php esc_html_e('View', 'groove'); ?></a>
          </div>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>
  <?php else: ?>
  <p class="text-sm text-gray-600"><?php esc_html_e('No issue pages have been published yet.', 'groove'); ?></p>
  <?php endif; ?>
</section>
<?php
	}

	public function display_content()
	{
		?>
<div class="space-y-6">
  <?php $this->render_notice(); ?>
  <?php $this->render_configuration_card(); ?>
  <?php $this->render_actions_card(); ?>

  <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
    <div><?php $this->render_runs_card(); ?></div>
    <div><?php $this->render_issues_card(); ?></div>
  </div>
</div>
<?php
	}
}
