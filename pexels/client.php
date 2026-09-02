<?php
namespace Groove\Pexels;

if (!defined('ABSPATH')) {
  exit;
}

/**
 * Client
 *
 * A very small wrapper around the Pexels v1 REST API.
 *
 * This class is only ever exercised by the curation path (the CLI script or an
 * explicit admin "refresh imagery" action). Nothing here runs on a hook and
 * nothing here is called while rendering a folio or an ordinary admin screen.
 *
 * Successful GETs are cached in a transient for an hour so repeated dry runs
 * do not burn the 200-requests-per-hour quota. Pass `no_cache => true` in the
 * args (or call set_cache_enabled(false)) to force a fresh request.
 *
 * @since 0.2.0
 */
class Client
{

  /** API base, with trailing slash. */
  const API_BASE = 'https://api.pexels.com/v1/';

  /** Transient key prefix. */
  const CACHE_PREFIX = 'groove_pexels_';

  /** Seconds a successful response stays cached. */
  const CACHE_TTL = 3600;

  /** Request timeout in seconds. */
  const TIMEOUT = 20;

  /**
   * The resolved API key. Never logged, never rendered.
   *
   * @var string
   */
  private $api_key;

  /**
   * Whether responses may be served from / written to the transient cache.
   *
   * @var bool
   */
  private $cache_enabled = true;

  /**
   * Rate-limit headers from the most recent live request.
   *
   * @var array ['limit' => int, 'remaining' => int, 'reset' => int]
   */
  private $last_rate = ['limit' => 0, 'remaining' => 0, 'reset' => 0];

  /**
   * @param string|null $api_key Explicit key, or null to resolve via Key::resolve().
   */
  public function __construct(?string $api_key = null)
  {
    $this->api_key = null === $api_key ? Key::resolve() : trim($api_key);
  }

  // ── Public API ───────────────────────────────────────────────────────────

  /**
   * Whether a non-empty key was resolved.
   *
   * @return bool
   */
  public function has_key(): bool
  {
    return '' !== $this->api_key;
  }

  /**
   * Search photos.
   *
   * @param string $query Search terms. Required by the API.
   * @param array  $args  Any of: orientation, size, color, locale, page,
   *                      per_page (max 80). Plus the local flag `no_cache`.
   *
   * @return array|\WP_Error Decoded body with an added '_rate' key.
   */
  public function search(string $query, array $args = [])
  {
    $query = trim($query);
    if ('' === $query) {
      return new \WP_Error(
        'groove_pexels_bad_request',
        __('A Pexels search needs a non-empty query.', 'groove')
      );
    }

    $args = array_merge(['per_page' => 15], $args, ['query' => $query]);

    return $this->request('search', $args);
  }

  /**
   * The curated feed.
   *
   * @param array $args page, per_page, plus the local flag `no_cache`.
   *
   * @return array|\WP_Error Decoded body with an added '_rate' key.
   */
  public function curated(array $args = [])
  {
    return $this->request('curated', array_merge(['per_page' => 15], $args));
  }

  /**
   * A single photo by ID.
   *
   * @param int $id Pexels photo ID.
   *
   * @return array|\WP_Error Decoded body with an added '_rate' key.
   */
  public function photo(int $id)
  {
    if ($id <= 0) {
      return new \WP_Error(
        'groove_pexels_bad_request',
        __('A Pexels photo ID must be a positive integer.', 'groove')
      );
    }

    return $this->request('photos/' . $id);
  }

  /**
   * Cheap auth probe: one search result against a random query, cache bypassed.
   *
   * THE RANDOM QUERY IS LOAD-BEARING — DO NOT "SIMPLIFY" THIS BACK TO /curated.
   *
   * Pexels sits behind Cloudflare, and popular request URLs are served straight
   * from the edge cache without ever reaching origin auth. `/curated?per_page=1`
   * is about the most cache-warm URL on the whole API: it answers HTTP 200 with
   * a full, valid photo payload even when the request carries a garbage key or
   * no key at all (cf-cache-status: HIT), so probing it green-lights any key you
   * hand it. The X-Ratelimit-* headers on such a response are stale too — two
   * consecutive probes reported 24898 and 24999 remaining.
   *
   * A high-entropy query cannot exist in the shared cache, so the request is a
   * cf-cache-status: BYPASS, reaches origin, and an invalid key gets the 401 it
   * deserves ({"status":401,"code":"Unauthorized","message":"Invalid API key"}).
   *
   * The probe is never cached locally either: a one-shot random query would only
   * pin junk in the transient store, and a cached verify result would defeat the
   * point of verifying.
   *
   * @return array|\WP_Error ['remaining'=>int,'limit'=>int,'reset'=>int,'source'=>string,'masked'=>string]
   */
  public function verify()
  {
    $response = $this->search(
      $this->probe_query(),
      ['per_page' => 1, 'no_cache' => true]
    );

    if (is_wp_error($response)) {
      return $response;
    }

    $rate = isset($response['_rate']) && is_array($response['_rate'])
      ? $response['_rate']
      : $this->last_rate;

    return [
      'remaining' => isset($rate['remaining']) ? (int) $rate['remaining'] : 0,
      'limit'     => isset($rate['limit']) ? (int) $rate['limit'] : 0,
      'reset'     => isset($rate['reset']) ? (int) $rate['reset'] : 0,
      'source'    => Key::source(),
      'masked'    => Key::masked(),
    ];
  }

  // ── Helpers (additive — not part of the frozen contract) ─────────────────

  /**
   * Turn the transient cache on or off for this client instance.
   *
   * @param bool $enabled
   *
   * @return self
   */
  public function set_cache_enabled(bool $enabled): self
  {
    $this->cache_enabled = $enabled;

    return $this;
  }

  /**
   * Rate-limit headers from the most recent live request.
   *
   * @return array ['limit' => int, 'remaining' => int, 'reset' => int]
   */
  public function get_last_rate(): array
  {
    return $this->last_rate;
  }

  // ── Internals ────────────────────────────────────────────────────────────

  /**
   * Perform a GET against the API, with caching and error normalisation.
   *
   * @param string $endpoint Endpoint relative to API_BASE, e.g. 'search'.
   * @param array  $args     Query args. The local-only key `no_cache` is stripped.
   *
   * @return array|\WP_Error
   */
  private function request(string $endpoint, array $args = [])
  {
    if (!$this->has_key()) {
      return new \WP_Error(
        'groove_pexels_no_key',
        __('No Pexels API key is configured. Add one in Groove settings, or define GROOVE_PEXELS_API_KEY in wp-config.php.', 'groove')
      );
    }

    $bypass_cache = !empty($args['no_cache']) || !$this->cache_enabled;
    unset($args['no_cache']);

    // Drop empties so the cache key stays stable across equivalent calls.
    $args = array_filter(
      $args,
      static function ($value) {
        return null !== $value && '' !== $value && [] !== $value;
      }
    );
    ksort($args);

    $cache_key = $this->cache_key($endpoint, $args);

    if (!$bypass_cache) {
      $cached = get_transient($cache_key);
      if (is_array($cached)) {
        return $cached;
      }
    }

    $url = add_query_arg($args, self::API_BASE . ltrim($endpoint, '/'));

    $response = wp_remote_get(
      $url,
      [
        'timeout'    => self::TIMEOUT,
        'user-agent' => 'GrooveFolios/' . (defined('GROOVE_VERSION') ? GROOVE_VERSION : '0.0.0'),
        'headers'    => [
          // Pexels expects the raw key — no "Bearer " prefix.
          'Authorization' => $this->api_key,
          'Accept'        => 'application/json',
        ],
      ]
    );

    if (is_wp_error($response)) {
      return new \WP_Error(
        'groove_pexels_transport_error',
        sprintf(
          /* translators: %s: HTTP transport error message. */
          __('Could not reach the Pexels API: %s', 'groove'),
          $response->get_error_message()
        ),
        ['endpoint' => $endpoint]
      );
    }

    $status = (int) wp_remote_retrieve_response_code($response);
    $rate = $this->parse_rate($response);
    $this->last_rate = $rate;

    $error = $this->status_error($status, $rate, $endpoint, wp_remote_retrieve_response_message($response));
    if ($error) {
      return $error;
    }

    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);

    if (!is_array($data)) {
      return new \WP_Error(
        'groove_pexels_invalid_json',
        __('The Pexels API returned a response that could not be decoded as JSON.', 'groove'),
        ['endpoint' => $endpoint, 'status' => $status]
      );
    }

    // Cloudflare can hand back a cached *error document* under HTTP 200, so a
    // 200 is not on its own proof of success.
    $body_error = $this->body_error($data, $rate, $endpoint);
    if ($body_error) {
      return $body_error;
    }

    $data['_rate'] = $rate;

    // Only pin a response that actually carries a payload. A malformed or
    // edge-cached body has no business sitting in a transient for an hour.
    if (!$bypass_cache && $this->has_payload($data)) {
      set_transient($cache_key, $data, self::CACHE_TTL);
    }

    return $data;
  }

  /**
   * Whether a decoded body carries a real API payload.
   *
   * A search or curated response must have a `photos` array (an empty one is a
   * legitimate "no results"); a single-photo response must have an ID and src.
   *
   * @param array $data
   *
   * @return bool
   */
  private function has_payload(array $data): bool
  {
    if (isset($data['photos']) && is_array($data['photos'])) {
      return true;
    }

    return !empty($data['id']) && !empty($data['src']);
  }

  /**
   * Detect an error document returned with a 2xx status.
   *
   * Pexels errors look like {"status":401,"code":"Unauthorized","message":"…"}.
   * Cloudflare will happily serve one of those from cache with HTTP 200, and
   * without this check it would decode cleanly and sail through as success.
   *
   * @param array  $data
   * @param array  $rate
   * @param string $endpoint
   *
   * @return \WP_Error|null Null when the body is a normal payload.
   */
  private function body_error(array $data, array $rate, string $endpoint)
  {
    if ($this->has_payload($data)) {
      return null;
    }

    $status = isset($data['status']) ? (int) $data['status'] : 0;
    $code = isset($data['code']) ? strtolower(trim((string) $data['code'])) : '';

    $message = '';
    if (!empty($data['message']) && is_string($data['message'])) {
      $message = $data['message'];
    } elseif (!empty($data['error']) && is_string($data['error'])) {
      $message = $data['error'];
    }

    // Some error documents carry the code word but no numeric status.
    if ($status < 400) {
      $by_code = [
        'unauthorized'        => 401,
        'forbidden'           => 403,
        'not found'           => 404,
        'too many requests'   => 429,
        'rate limit exceeded' => 429,
      ];

      if (isset($by_code[$code])) {
        $status = $by_code[$code];
      }
    }

    if ($status < 400) {
      return null;
    }

    return $this->status_error($status, $rate, $endpoint, $message);
  }

  /**
   * A one-shot, high-entropy search term for verify().
   *
   * Random by design: see the note on verify().
   *
   * @return string
   */
  private function probe_query(): string
  {
    if (function_exists('wp_generate_password')) {
      return 'groove-verify-' . strtolower(wp_generate_password(12, false, false));
    }

    return 'groove-verify-' . bin2hex(random_bytes(6));
  }

  /**
   * Map a non-200 status onto a distinct WP_Error code.
   *
   * @param int    $status
   * @param array  $rate
   * @param string $endpoint
   * @param string $status_message HTTP status message, or the API's own message.
   *
   * @return \WP_Error|null Null when the status is fine.
   */
  private function status_error(int $status, array $rate, string $endpoint, string $status_message = '')
  {
    if (200 === $status) {
      return null;
    }

    $data = ['status' => $status, 'endpoint' => $endpoint, 'rate' => $rate, 'api_message' => $status_message];

    if (401 === $status || 403 === $status) {
      return new \WP_Error(
        'groove_pexels_unauthorized',
        sprintf(
          /* translators: %s: masked API key, e.g. ••••••••abcd. */
          __('Pexels rejected the API key (%s). Check the key and try again.', 'groove'),
          Key::masked()
        ),
        $data
      );
    }

    if (429 === $status) {
      return new \WP_Error(
        'groove_pexels_rate_limited',
        __('The Pexels rate limit has been reached. Wait for the window to reset and re-run.', 'groove'),
        $data
      );
    }

    if (404 === $status) {
      return new \WP_Error(
        'groove_pexels_not_found',
        __('The requested Pexels resource does not exist.', 'groove'),
        $data
      );
    }

    return new \WP_Error(
      'groove_pexels_http_error',
      sprintf(
        /* translators: 1: HTTP status code, 2: HTTP status message. */
        __('The Pexels API responded with HTTP %1$d %2$s.', 'groove'),
        $status,
        $status_message
      ),
      $data
    );
  }

  /**
   * Read the X-Ratelimit-* headers.
   *
   * @param array $response Raw wp_remote_get response.
   *
   * @return array ['limit' => int, 'remaining' => int, 'reset' => int]
   */
  private function parse_rate($response): array
  {
    return [
      'limit'     => (int) wp_remote_retrieve_header($response, 'x-ratelimit-limit'),
      'remaining' => (int) wp_remote_retrieve_header($response, 'x-ratelimit-remaining'),
      'reset'     => (int) wp_remote_retrieve_header($response, 'x-ratelimit-reset'),
    ];
  }

  /**
   * Transient name for an endpoint + args pair.
   *
   * The key is salted with a hash of the API key so switching keys cannot
   * serve another key's cached responses. The salt is a one-way hash — the
   * key itself never lands in the options table this way.
   *
   * @param string $endpoint
   * @param array  $args
   *
   * @return string
   */
  private function cache_key(string $endpoint, array $args): string
  {
    $salt = substr(hash('sha256', $this->api_key), 0, 8);
    $seed = $endpoint . '|' . wp_json_encode($args) . '|' . $salt;

    return self::CACHE_PREFIX . md5($seed);
  }
}
