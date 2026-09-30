<?php
namespace Groove;

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Toast notifications.
 *
 * Groove admin screens report the outcome of an action as a transient pill at
 * the bottom-right corner rather than a block of copy pushed into the page.
 * An inline notice reflows the layout it lands in and stays there long after
 * it has been read; a toast reports and gets out of the way, so the content
 * below it never moves.
 *
 * Queue a message wherever the inline notice used to be rendered:
 *
 *     \Groove\Toast::success(__('Settings saved.', 'groove-folios'), ['message']);
 *
 * The queue is flushed into the admin footer, so anything queued during page
 * render arrives in the same request. The second argument lists query args to
 * strip from the address bar once the toast is shown, so a reload does not
 * replay it.
 *
 * A failure the operator has to fix is reported through failure() instead. It
 * queues the same toast and, in addition, pins the reason and the next step to
 * the control that was pressed:
 *
 *     \Groove\Toast::failure(
 *         __('That base slug cannot be used in a URL.', 'groove-folios'),
 *         __('Use letters, numbers and hyphens — for example folio.', 'groove-folios'),
 *         '#groove-save-routing',
 *         ['message']
 *     );
 *
 * Reserve an inline notice for a standing condition the operator has to act on
 * (a missing dependency, an unreachable service) — those describe the state of
 * the screen, not the outcome of a click, and should not disappear on a timer.
 *
 * @since 0.2.0
 */
class Toast
{
	const SUCCESS = 'success';
	const ERROR = 'error';
	const WARNING = 'warning';
	const INFO = 'info';

	/**
	 * Toasts queued for the current request.
	 *
	 * @var array<int, array{message: string, type: string, duration: int, anchor: string, hint: string}>
	 */
	private static $queue = [];

	/**
	 * Query args to strip from the URL once the toasts have been shown.
	 *
	 * @var array<int, string>
	 */
	private static $consumed_args = [];

	/**
	 * Whether the footer printer has been hooked.
	 *
	 * @var bool
	 */
	private static $hooked = false;

	/**
	 * Queue a toast.
	 *
	 * @param string   $message       Plain text. Rendered as text, never as HTML.
	 * @param string   $type          One of success|error|warning|info.
	 * @param string[] $consumed_args Query args to drop from the URL afterwards.
	 * @param int      $duration      Milliseconds before auto-dismiss. 0 keeps it up.
	 */
	public static function add($message, $type = self::SUCCESS, array $consumed_args = [], $duration = 4000)
	{
		self::push($message, $type, $consumed_args, $duration, '', '');
	}

	/**
	 * Queue a toast, optionally pinned to the control that produced it.
	 *
	 * @param string   $message       Plain text. Rendered as text, never as HTML.
	 * @param string   $type          One of success|error|warning|info.
	 * @param string[] $consumed_args Query args to drop from the URL afterwards.
	 * @param int      $duration      Milliseconds before auto-dismiss. 0 keeps it up.
	 * @param string   $anchor        CSS selector for the control to point at.
	 * @param string   $hint          Plain text. What the operator should do next.
	 */
	private static function push($message, $type, array $consumed_args, $duration, $anchor, $hint)
	{
		$message = trim(wp_strip_all_tags((string) $message));

		if ($message === '') {
			return;
		}

		if (!in_array($type, [self::SUCCESS, self::ERROR, self::WARNING, self::INFO], true)) {
			$type = self::SUCCESS;
		}

		self::$queue[] = [
			'message' => $message,
			'type' => $type,
			'duration' => max(0, (int) $duration),
			'anchor' => (string) $anchor,
			'hint' => trim(wp_strip_all_tags((string) $hint)),
		];

		foreach ($consumed_args as $arg) {
			$arg = sanitize_key($arg);
			if ($arg !== '' && !in_array($arg, self::$consumed_args, true)) {
				self::$consumed_args[] = $arg;
			}
		}

		self::hook();
	}

	public static function success($message, array $consumed_args = [], $duration = 4000)
	{
		self::add($message, self::SUCCESS, $consumed_args, $duration);
	}

	public static function error($message, array $consumed_args = [], $duration = 6000)
	{
		self::add($message, self::ERROR, $consumed_args, $duration);
	}

	public static function warning($message, array $consumed_args = [], $duration = 6000)
	{
		self::add($message, self::WARNING, $consumed_args, $duration);
	}

	public static function info($message, array $consumed_args = [], $duration = 4000)
	{
		self::add($message, self::INFO, $consumed_args, $duration);
	}

	/**
	 * Report a failed action: a toast in passing, and a toggletip that stays on
	 * the control until it is dismissed.
	 *
	 * The toast alone would fade before a long message has been read, and would
	 * leave the operator looking at a screen that gives no sign of what to fix.
	 * The hint is what makes this worth pinning — say what to do, not only what
	 * went wrong.
	 *
	 * @param string   $message       What went wrong.
	 * @param string   $hint          What to do about it.
	 * @param string   $anchor        CSS selector for the control to point at.
	 * @param string[] $consumed_args Query args to drop from the URL afterwards.
	 * @param string   $type          error|warning. Anything else is treated as an error.
	 */
	public static function failure($message, $hint = '', $anchor = '', array $consumed_args = [], $type = self::ERROR)
	{
		if (!in_array($type, [self::ERROR, self::WARNING], true)) {
			$type = self::ERROR;
		}

		self::push($message, $type, $consumed_args, 6000, $anchor, $hint);
	}

	/**
	 * Hook the footer printer the first time something is queued.
	 *
	 * Admin page callbacks run well before the footer, so a toast queued while
	 * the screen renders still makes this pass. admin_footer, not
	 * admin_print_footer_scripts: the queue rides on the groove-toast handle
	 * as an inline script, and core prints footer scripts on the latter.
	 */
	private static function hook()
	{
		if (self::$hooked) {
			return;
		}

		self::$hooked = true;
		add_action('admin_footer', [__CLASS__, 'print_queue'], 99);
	}

	/**
	 * Hand the queue to the front end.
	 */
	public static function print_queue()
	{
		if (empty(self::$queue)) {
			return;
		}

		$payload = [
			'toasts' => self::$queue,
			'consumedArgs' => self::$consumed_args,
		];

		self::$queue = [];
		self::$consumed_args = [];

		wp_add_inline_script(
			'groove-toast',
			'(window.GROOVE_TOASTS = window.GROOVE_TOASTS || []).push(' . wp_json_encode($payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . ');'
			. 'if (window.grooveDrainToasts) { window.grooveDrainToasts(); }'
		);
	}
}
