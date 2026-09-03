<?php
namespace Groove;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Groove Analytics.
 *
 * Sends events to PostHog. Two deliberately separate paths:
 *
 * - track()          Passive, anonymous telemetry. Opt-in via Settings → Privacy,
 *                    fire-and-forget, identified only by a hash of the site URL,
 *                    and flagged so PostHog creates no person profile.
 * - send_feedback()  User-initiated messages from the Feedback page. Sent
 *                    regardless of the analytics opt-in (pressing Send is the
 *                    consent), blocking so the caller can report real success or
 *                    failure, and carrying the sender's email address.
 *
 * To activate: fill in POSTHOG_API_KEY and POSTHOG_HOST below with your
 * PostHog project credentials, then save.
 */
class Analytics
{
	/**
	 * Your PostHog project API key (starts with phc_...).
	 * Found in PostHog → Settings → Project → Project API key.
	 *
	 * This is a write-only public key and is safe to ship in plugin source.
	 * Never put a personal API key (phx_...) here — those can read and delete
	 * project data.
	 */
	const POSTHOG_API_KEY = 'phc_mPqk8qCDZ2N2Uf61qAE2bcBck4sPkW3tkat4u1dTq1M';

	/**
	 * Your PostHog ingestion host.
	 * US cloud: https://us.i.posthog.com
	 * EU cloud: https://eu.i.posthog.com
	 */
	const POSTHOG_HOST = 'https://us.i.posthog.com';

	/**
	 * PostHog's current single-event capture endpoint.
	 */
	const CAPTURE_PATH = 'i/v0/e/';

	/**
	 * Track an anonymous usage event if the user has opted in to analytics.
	 *
	 * @param string $event      PostHog event name (e.g. 'folio_published').
	 * @param array  $properties Additional properties to attach to the event.
	 *
	 * @return bool False when tracking is off or misconfigured. Delivery is
	 *              fire-and-forget, so a true return means "dispatched", not
	 *              "received".
	 */
	public static function track($event, array $properties = [])
	{
		if (!get_option('groove_usage_analytics', 0)) {
			return false;
		}

		// Anonymous: no person profile, so no personal data is ever associated.
		$properties['$process_person_profile'] = false;

		return self::capture($event, md5(get_site_url()), $properties, false);
	}

	/**
	 * Send a user-initiated message to PostHog.
	 *
	 * Unlike track(), this ignores the usage-analytics opt-in — the user
	 * explicitly pressed Send — and blocks so the caller can tell the user
	 * truthfully whether the message got through.
	 *
	 * @param string $event       PostHog event name (e.g. 'feedback_submitted').
	 * @param string $distinct_id Person identifier, usually the sender's email.
	 * @param array  $properties  Event properties.
	 * @param array  $person      Person properties to set (sent as $set).
	 *
	 * @return bool True only when PostHog accepted the event.
	 */
	public static function send_feedback($event, $distinct_id, array $properties = [], array $person = [])
	{
		if (!empty($person)) {
			$properties['$set'] = $person;
		}

		return self::capture($event, $distinct_id, $properties, true);
	}

	/**
	 * POST a single event to PostHog.
	 *
	 * @param string $event
	 * @param string $distinct_id
	 * @param array  $properties
	 * @param bool   $blocking    Wait for the response and report the result.
	 *
	 * @return bool
	 */
	private static function capture($event, $distinct_id, array $properties, $blocking)
	{
		$api_key = self::POSTHOG_API_KEY;
		if (empty($api_key) || strpos($api_key, 'YOUR_') === 0) {
			return false;
		}

		$body = wp_json_encode([
			'api_key' => $api_key,
			'event' => $event,
			'distinct_id' => $distinct_id,
			'timestamp' => gmdate('c'),
			'properties' => array_merge(self::base_properties(), $properties),
		]);

		if (!$body) {
			return false;
		}

		$response = wp_remote_post(
			trailingslashit(self::POSTHOG_HOST) . self::CAPTURE_PATH,
			[
				'body' => $body,
				'headers' => ['Content-Type' => 'application/json'],
				'timeout' => $blocking ? 8 : 3,
				'blocking' => $blocking,
				'data_format' => 'body',
			]
		);

		if (!$blocking) {
			return true;
		}

		if (is_wp_error($response)) {
			self::log_failure($event, $response->get_error_message());

			return false;
		}

		$code = wp_remote_retrieve_response_code($response);

		if ($code < 200 || $code >= 300) {
			self::log_failure($event, $code . ' ' . wp_remote_retrieve_body($response));

			return false;
		}

		return true;
	}

	/**
	 * Record why a blocking send failed. Only when WP_DEBUG is on — the message
	 * body is never logged, only PostHog's response.
	 */
	private static function log_failure($event, $reason)
	{
		if (defined('WP_DEBUG') && WP_DEBUG) {
			error_log(sprintf('[Groove] PostHog capture failed for "%s": %s', $event, $reason)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Environment properties attached to every event.
	 */
	private static function base_properties()
	{
		return [
			'$lib' => 'groove-folios',
			'plugin_version' => defined('GROOVE_VERSION') ? GROOVE_VERSION : '',
			'wp_version' => get_bloginfo('version'),
			'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
		];
	}
}
