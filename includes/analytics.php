<?php
namespace Groove;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Groove Analytics.
 *
 * Sends anonymous usage events to PostHog when the user has opted in via
 * Settings → Privacy. All tracking is fire-and-forget (non-blocking HTTP).
 *
 * To activate: fill in POSTHOG_API_KEY and POSTHOG_HOST below with your
 * PostHog project credentials, then save.
 */
class Analytics
{
	/**
	 * Your PostHog project API key (starts with phc_...).
	 * Found in PostHog → Settings → Project → Project API key.
	 */
	const POSTHOG_API_KEY = 'phc_mPqk8qCDZ2N2Uf61qAE2bcBck4sPkW3tkat4u1dTq1M';

	/**
	 * Your PostHog ingestion host.
	 * US cloud: https://us.i.posthog.com
	 * EU cloud: https://eu.i.posthog.com
	 */
	const POSTHOG_HOST = 'https://us.i.posthog.com';

	/**
	 * Track an event if the user has opted in to analytics.
	 *
	 * @param string $event      PostHog event name (e.g. 'folio_published').
	 * @param array  $properties Additional properties to attach to the event.
	 */
	public static function track($event, array $properties = [])
	{
		if (!get_option('groove_usage_analytics', 0)) {
			return;
		}

		$api_key = self::POSTHOG_API_KEY;
		if (empty($api_key) || strpos($api_key, 'YOUR_') === 0) {
			return;
		}

		$body = wp_json_encode([
			'api_key' => $api_key,
			'distinct_id' => md5(get_site_url()),
			'event' => $event,
			'properties' => array_merge(
				[
					'$lib' => 'groove-folios',
					'plugin_version' => defined('GROOVE_VERSION') ? GROOVE_VERSION : '',
					'wp_version' => get_bloginfo('version'),
					'php_version' => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
				],
				$properties
			),
		]);

		if (!$body) {
			return;
		}

		wp_remote_post(
			trailingslashit(self::POSTHOG_HOST) . 'capture/',
			[
				'body' => $body,
				'headers' => ['Content-Type' => 'application/json'],
				'timeout' => 3,
				'blocking' => false,
				'data_format' => 'body',
			]
		);
	}
}
