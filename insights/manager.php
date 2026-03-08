<?php
namespace Groove\Insights;

use Groove\Themes\Themes_Manager;
use Groove\Utils\Utils;

if (!defined('ABSPATH')) {
	exit;
}

class Manager
{
	const RUN_POST_TYPE = 'groove_insight_run';
	const REST_NAMESPACE = 'groove/v1';

	private static $instance = null;

	public static function instance()
	{
		if (null === self::$instance) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct()
	{
		add_action('init', [$this, 'register_run_post_type']);
		add_action('rest_api_init', [$this, 'register_rest_routes']);
	}

	public function register_run_post_type()
	{
		register_post_type(self::RUN_POST_TYPE, [
			'labels' => [
				'name' => __('Insight Runs', 'groove'),
				'singular_name' => __('Insight Run', 'groove'),
			],
			'public' => false,
			'show_ui' => false,
			'show_in_menu' => false,
			'show_in_rest' => false,
			'supports' => ['title', 'custom-fields'],
			'capability_type' => 'post',
			'map_meta_cap' => true,
		]);
	}

	public function register_rest_routes()
	{
		register_rest_route(
			self::REST_NAMESPACE,
			'/insights/runs/(?P<run_id>\d+)/complete',
			[
				'methods' => \WP_REST_Server::CREATABLE,
				'callback' => [$this, 'handle_run_completion'],
				'permission_callback' => '__return_true',
			]
		);
	}

	public static function get_worker_endpoint()
	{
		return esc_url_raw((string) get_option('groove_insights_worker_endpoint', ''));
	}

	public static function get_shared_secret()
	{
		return trim((string) get_option('groove_insights_shared_secret', ''));
	}

	public static function get_notification_email()
	{
		$email = sanitize_email((string) get_option('groove_insights_notification_email', ''));
		if (!is_email($email)) {
			$email = (string) get_option('admin_email', '');
		}

		return $email;
	}

	public static function get_master_folio_id()
	{
		$folio_id = (int) get_option('groove_insights_master_folio_id', 0);
		if ($folio_id > 0 && get_post_type($folio_id) === 'groove_folio') {
			return $folio_id;
		}

		return 0;
	}

	public static function get_callback_url($run_id)
	{
		return rest_url(self::REST_NAMESPACE . '/insights/runs/' . (int) $run_id . '/complete');
	}

	public static function create_run($args = [])
	{
		$trigger_source = isset($args['trigger_source']) ? sanitize_key($args['trigger_source']) : 'manual';
		$title_suffix = wp_date(get_option('date_format') . ' ' . get_option('time_format'));
		$run_id = wp_insert_post([
			'post_type' => self::RUN_POST_TYPE,
			'post_status' => 'publish',
			'post_title' => sprintf(__('Insights Run %s', 'groove'), $title_suffix),
			'meta_input' => [
				'run_status' => 'queued',
				'requested_at' => current_time('mysql'),
				'trigger_source' => $trigger_source,
				'requested_by' => get_current_user_id(),
			],
		], true);

		if (is_wp_error($run_id)) {
			return $run_id;
		}

		self::update_run($run_id, [
			'master_folio_id' => self::get_master_folio_id(),
		]);

		return (int) $run_id;
	}

	public static function update_run($run_id, $meta)
	{
		foreach ((array) $meta as $key => $value) {
			update_post_meta((int) $run_id, (string) $key, $value);
		}
	}

	public static function mark_run_status($run_id, $status, $meta = [])
	{
		$payload = array_merge([
			'run_status' => sanitize_key($status),
			'last_updated_at' => current_time('mysql'),
		], $meta);

		self::update_run($run_id, $payload);
	}

	public function dispatch_run($run_id)
	{
		$run_id = (int) $run_id;
		$endpoint = self::get_worker_endpoint();
		if ($endpoint === '') {
			return new \WP_Error('groove_insights_missing_endpoint', __('The Insights worker endpoint is not configured.', 'groove'));
		}

		$secret = self::get_shared_secret();
		if ($secret === '') {
			return new \WP_Error('groove_insights_missing_secret', __('The Insights shared secret is not configured.', 'groove'));
		}

		$payload = [
			'run_id' => $run_id,
			'site_name' => get_bloginfo('name'),
			'site_url' => home_url('/'),
			'callback_url' => self::get_callback_url($run_id),
			'callback_secret' => $secret,
			'master_folio_id' => self::get_master_folio_id(),
			'notification_email' => self::get_notification_email(),
			'triggered_at' => current_time('c'),
		];

		self::mark_run_status($run_id, 'dispatching');

		$response = wp_remote_post($endpoint, [
			'headers' => [
				'Content-Type' => 'application/json',
				'X-Groove-Insights-Secret' => $secret,
			],
			'body' => wp_json_encode($payload),
			'timeout' => 20,
		]);

		if (is_wp_error($response)) {
			self::mark_run_status($run_id, 'failed', [
				'error_message' => $response->get_error_message(),
			]);

			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code($response);
		$body = (string) wp_remote_retrieve_body($response);
		if ($code < 200 || $code >= 300) {
			self::mark_run_status($run_id, 'failed', [
				'error_message' => sprintf(__('Worker dispatch failed with HTTP %d.', 'groove'), $code),
				'dispatch_response' => wp_strip_all_tags($body),
			]);

			return new \WP_Error('groove_insights_dispatch_failed', __('The worker rejected the request.', 'groove'));
		}

		self::mark_run_status($run_id, 'dispatched', [
			'dispatch_response' => wp_strip_all_tags($body),
			'dispatched_at' => current_time('mysql'),
		]);

		return true;
	}

	public function handle_run_completion(\WP_REST_Request $request)
	{
		$secret = (string) $request->get_header('x-groove-insights-secret');
		if (!$this->is_valid_secret($secret)) {
			return new \WP_REST_Response([
				'success' => false,
				'message' => __('Invalid callback secret.', 'groove'),
			], 401);
		}

		$run_id = (int) $request['run_id'];
		$run = get_post($run_id);
		if (!$run || $run->post_type !== self::RUN_POST_TYPE) {
			return new \WP_REST_Response([
				'success' => false,
				'message' => __('Unknown run ID.', 'groove'),
			], 404);
		}

		$payload = $request->get_json_params();
		if (!is_array($payload)) {
			return new \WP_REST_Response([
				'success' => false,
				'message' => __('Invalid JSON payload.', 'groove'),
			], 400);
		}

		self::update_run($run_id, [
			'worker_payload_json' => wp_json_encode($payload),
		]);

		$status = sanitize_key((string) ($payload['status'] ?? 'completed'));
		if (in_array($status, ['failed', 'error'], true)) {
			self::mark_run_status($run_id, 'failed', [
				'completed_at' => current_time('mysql'),
				'error_message' => sanitize_textarea_field((string) ($payload['error_message'] ?? __('The worker reported a failure.', 'groove'))),
			]);

			return rest_ensure_response([
				'success' => true,
				'run_id' => $run_id,
				'status' => 'failed',
			]);
		}

		$result = $this->publish_issue_from_payload($run_id, $payload);
		if (is_wp_error($result)) {
			self::mark_run_status($run_id, 'failed', [
				'completed_at' => current_time('mysql'),
				'error_message' => $result->get_error_message(),
			]);

			return new \WP_REST_Response([
				'success' => false,
				'message' => $result->get_error_message(),
			], 500);
		}

		return rest_ensure_response([
			'success' => true,
			'run_id' => $run_id,
			'status' => 'completed',
			'issue_page_id' => $result['issue_page_id'],
			'issue_edit_url' => $result['issue_edit_url'],
		]);
	}

	public function ensure_master_folio()
	{
		$existing_id = self::get_master_folio_id();
		if ($existing_id > 0) {
			return $existing_id;
		}

		$existing_posts = get_posts([
			'post_type' => 'groove_folio',
			'post_status' => ['publish', 'draft', 'private'],
			'posts_per_page' => 1,
			'name' => 'weekly-ai-and-saas-insights',
		]);
		if (!empty($existing_posts[0]->ID)) {
			update_option('groove_insights_master_folio_id', (int) $existing_posts[0]->ID);
			return (int) $existing_posts[0]->ID;
		}

		$themes = Themes_Manager::get_all_themes();
		$default_theme_id = (string) get_option('groove_default_theme_id', '');
		if ($default_theme_id === '' || !isset($themes[$default_theme_id])) {
			$default_theme_id = !empty($themes) ? (string) array_key_first($themes) : '';
		}

		$folio_id = wp_insert_post([
			'post_type' => 'groove_folio',
			'post_status' => 'publish',
			'post_title' => 'Weekly AI and SaaS Insights',
			'post_name' => 'weekly-ai-and-saas-insights',
			'post_content' => __('A running archive of weekly executive briefings on AI product releases, infrastructure shifts, and the implications for SaaS teams.', 'groove'),
			'meta_input' => [
				'subtitle' => __('Agent-curated executive briefings', 'groove'),
				'theme_id' => $default_theme_id,
				'permission' => '2',
				'use_folio' => '1',
			],
		], true);

		if (is_wp_error($folio_id)) {
			return $folio_id;
		}

		update_option('groove_insights_master_folio_id', (int) $folio_id);

		return (int) $folio_id;
	}

	public function get_recent_runs($limit = 8)
	{
		return get_posts([
			'post_type' => self::RUN_POST_TYPE,
			'post_status' => 'publish',
			'posts_per_page' => max(1, (int) $limit),
			'orderby' => 'date',
			'order' => 'DESC',
		]);
	}

	public function get_recent_issues($limit = 8)
	{
		$master_folio_id = self::get_master_folio_id();
		if ($master_folio_id <= 0) {
			return [];
		}

		return get_posts([
			'post_type' => 'groove_folio_page',
			'post_status' => ['publish', 'draft', 'private'],
			'posts_per_page' => max(1, (int) $limit),
			'orderby' => 'date',
			'order' => 'DESC',
			'meta_query' => [
				[
					'key' => 'folio_id',
					'value' => $master_folio_id,
					'compare' => '=',
					'type' => 'NUMERIC',
				],
			],
		]);
	}

	private function is_valid_secret($provided_secret)
	{
		$saved_secret = self::get_shared_secret();
		if ($saved_secret === '' || !is_string($provided_secret) || $provided_secret === '') {
			return false;
		}

		return hash_equals($saved_secret, $provided_secret);
	}

	private function publish_issue_from_payload($run_id, $payload)
	{
		$master_folio_id = self::get_master_folio_id();
		if ($master_folio_id <= 0) {
			$master_folio_id = $this->ensure_master_folio();
		}
		if (is_wp_error($master_folio_id)) {
			return $master_folio_id;
		}

		$issue = isset($payload['issue']) && is_array($payload['issue']) ? $payload['issue'] : [];
		$title = sanitize_text_field((string) ($issue['title'] ?? 'Weekly AI and SaaS Insights'));
		if ($title === '') {
			$title = 'Weekly AI and SaaS Insights';
		}

		$slug = sanitize_title((string) ($issue['slug'] ?? $title));
		$content = $this->build_issue_content($payload);
		$excerpt = sanitize_textarea_field((string) ($issue['summary'] ?? ''));

		$issue_page_id = wp_insert_post([
			'post_type' => 'groove_folio_page',
			'post_status' => 'publish',
			'post_title' => $title,
			'post_name' => $slug,
			'post_excerpt' => $excerpt,
			'post_content' => $content,
			'meta_input' => [
				'folio_id' => $master_folio_id,
				'groove_insights_run_id' => $run_id,
			],
		], true);

		if (is_wp_error($issue_page_id)) {
			return $issue_page_id;
		}

		$story_count = isset($payload['top_stories']) && is_array($payload['top_stories']) ? count($payload['top_stories']) : 0;
		$audio_url = esc_url_raw((string) ($issue['audio_url'] ?? ''));
		$markdown_url = esc_url_raw((string) ($issue['markdown_url'] ?? ''));
		$issue_edit_url = admin_url('post.php?post=' . (int) $issue_page_id . '&action=edit');
		$issue_view_url = Utils::get_folio_permalink_by_id($issue_page_id);

		self::mark_run_status($run_id, 'completed', [
			'completed_at' => current_time('mysql'),
			'issue_page_id' => (int) $issue_page_id,
			'issue_edit_url' => $issue_edit_url,
			'issue_view_url' => $issue_view_url,
			'issue_title' => $title,
			'top_story_count' => $story_count,
			'audio_url' => $audio_url,
			'markdown_url' => $markdown_url,
		]);

		$this->send_completion_email($title, $issue_view_url, $markdown_url, $audio_url, $payload);

		return [
			'issue_page_id' => (int) $issue_page_id,
			'issue_edit_url' => $issue_edit_url,
		];
	}

	private function build_issue_content($payload)
	{
		$issue = isset($payload['issue']) && is_array($payload['issue']) ? $payload['issue'] : [];
		$overall_summary = wp_kses_post((string) ($issue['summary_html'] ?? ''));
		if ($overall_summary === '') {
			$overall_summary = wpautop(esc_html((string) ($issue['summary'] ?? '')));
		}

		$audio_url = esc_url((string) ($issue['audio_url'] ?? ''));
		$markdown_url = esc_url((string) ($issue['markdown_url'] ?? ''));
		$briefing_html = wp_kses_post((string) ($issue['briefing_html'] ?? ''));

		$html = '<section class="groove-insights-issue">';
		if ($overall_summary !== '') {
			$html .= '<div class="groove-insights-summary">' . $overall_summary . '</div>';
		}

		if ($audio_url !== '' || $markdown_url !== '') {
			$html .= '<div class="groove-insights-assets"><h2>' . esc_html__('Assets', 'groove') . '</h2><ul>';
			if ($audio_url !== '') {
				$html .= '<li><a href="' . esc_url($audio_url) . '">' . esc_html__('Audio briefing', 'groove') . '</a></li>';
			}
			if ($markdown_url !== '') {
				$html .= '<li><a href="' . esc_url($markdown_url) . '">' . esc_html__('Markdown briefing', 'groove') . '</a></li>';
			}
			$html .= '</ul></div>';
		}

		if ($briefing_html !== '') {
			$html .= '<div class="groove-insights-briefing">' . $briefing_html . '</div>';
		}

		$stories = isset($payload['top_stories']) && is_array($payload['top_stories']) ? $payload['top_stories'] : [];
		if (!empty($stories)) {
			$html .= '<div class="groove-insights-stories"><h2>' . esc_html__('Top Stories', 'groove') . '</h2>';
			foreach ($stories as $index => $story) {
				$html .= $this->build_story_section($story, $index + 1);
			}
			$html .= '</div>';
		}

		$sources = isset($payload['original_sources']) && is_array($payload['original_sources']) ? $payload['original_sources'] : [];
		if (!empty($sources)) {
			$html .= '<div class="groove-insights-sources"><h2>' . esc_html__('Original Sources', 'groove') . '</h2><ul>';
			foreach ($sources as $source) {
				$title = esc_html((string) ($source['title'] ?? __('Source', 'groove')));
				$publication = esc_html((string) ($source['publication'] ?? ''));
				$date = esc_html((string) ($source['date'] ?? ''));
				$url = esc_url((string) ($source['url'] ?? ''));
				$label = trim($publication . ($date !== '' ? ' • ' . $date : ''));

				$html .= '<li>';
				if ($url !== '') {
					$html .= '<a href="' . $url . '">' . $title . '</a>';
				} else {
					$html .= $title;
				}
				if ($label !== '') {
					$html .= ' <span>(' . esc_html($label) . ')</span>';
				}
				$html .= '</li>';
			}
			$html .= '</ul></div>';
		}

		$html .= '</section>';

		return $html;
	}

	private function build_story_section($story, $index)
	{
		$title = esc_html((string) ($story['title'] ?? sprintf(__('Story %d', 'groove'), $index)));
		$takeaway = esc_html((string) ($story['takeaway'] ?? ''));
		$impact = esc_html((string) ($story['impact'] ?? ''));
		$summary_html = wp_kses_post((string) ($story['summary_html'] ?? ''));
		if ($summary_html === '') {
			$summary_html = wpautop(esc_html((string) ($story['summary'] ?? '')));
		}
		$url = esc_url((string) ($story['source_url'] ?? ''));
		$source = esc_html((string) ($story['source_name'] ?? ''));
		$date = esc_html((string) ($story['date'] ?? ''));

		$html = '<article class="groove-insights-story">';
		$html .= '<h3>' . $title . '</h3>';
		if ($takeaway !== '') {
			$html .= '<p><strong>' . esc_html__('Takeaway:', 'groove') . '</strong> ' . $takeaway . '</p>';
		}
		if ($impact !== '') {
			$html .= '<p><strong>' . esc_html__('Why it matters:', 'groove') . '</strong> ' . $impact . '</p>';
		}
		if ($summary_html !== '') {
			$html .= $summary_html;
		}
		if ($source !== '' || $url !== '') {
			$html .= '<p>';
			if ($source !== '') {
				$html .= '<strong>' . esc_html__('Source:', 'groove') . '</strong> ';
			}
			if ($url !== '') {
				$html .= '<a href="' . $url . '">' . ($source !== '' ? $source : esc_html__('Read source', 'groove')) . '</a>';
			} else {
				$html .= $source;
			}
			if ($date !== '') {
				$html .= ' <span>(' . $date . ')</span>';
			}
			$html .= '</p>';
		}
		$html .= '</article>';

		return $html;
	}

	private function send_completion_email($title, $issue_url, $markdown_url, $audio_url, $payload)
	{
		$to = self::get_notification_email();
		if (!is_email($to)) {
			return;
		}

		$subject = sprintf(__('Groove Insights complete: %s', 'groove'), $title);
		$body = sprintf("%s\n\n", $title);

		$stories = isset($payload['top_stories']) && is_array($payload['top_stories']) ? $payload['top_stories'] : [];
		if (!empty($stories)) {
			$body .= __("Top takeaways:\n", 'groove');
			foreach ($stories as $story) {
				$line = trim((string) ($story['takeaway'] ?? ''));
				if ($line === '') {
					$line = trim((string) ($story['title'] ?? ''));
				}
				if ($line !== '') {
					$body .= '- ' . $line . "\n";
				}
			}
			$body .= "\n";
		}

		if ($issue_url !== '') {
			$body .= __('Published issue:', 'groove') . ' ' . $issue_url . "\n";
		}
		if ($markdown_url !== '') {
			$body .= __('Markdown briefing:', 'groove') . ' ' . $markdown_url . "\n";
		}
		if ($audio_url !== '') {
			$body .= __('Audio briefing:', 'groove') . ' ' . $audio_url . "\n";
		}

		wp_mail($to, $subject, $body);
	}
}
