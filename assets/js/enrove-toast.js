/**
 * Enrove toast notifications.
 *
 * A single pill in the bottom-right corner reports the outcome of an action
 * and then leaves. Nothing in the page reflows around it, so the content the
 * operator was reading stays exactly where they left it. Every Enrove
 * notification that is not pinned to a control lands in this one stack.
 *
 * window.enroveShowToast(message, type, duration)
 *   message  Plain text. Inserted as text, never parsed as HTML.
 *   type     'success' | 'error' | 'warning' | 'info'   (default 'success')
 *   duration Milliseconds before auto-dismiss. 0 keeps it up until dismissed.
 *
 * window.enroveStatusToast(key)
 *   One toast for an operation that reports as it runs ("Saving…", then
 *   "Saved"): each set(message, type, duration) rewrites the same pill in
 *   place rather than stacking a new one. Returns { set, hide }.
 *
 * PHP queues toasts through \Enrove\Toast, which pushes payloads onto
 * window.ENROVE_TOASTS and calls enroveDrainToasts(). The queue is an array so
 * the footer script can run before or after this file without ordering rules.
 *
 * A queued item may also name an `anchor` selector and a `hint`. The toast
 * still reports the outcome in passing; the anchor gets a toggletip that stays
 * at the button the operator pressed, saying what to do next. Use it for a
 * failure the operator has to act on, never for a success.
 */
(function () {
	'use strict'

	var CONTAINER_CLASS = 'g-toast-container'
	var EXIT_MS = 200

	function getContainer() {
		var container = document.querySelector('.' + CONTAINER_CLASS)

		if (!container) {
			container = document.createElement('div')
			container.className = CONTAINER_CLASS
			// Announced politely so a screen reader hears the outcome without
			// losing the operator's place on the page.
			container.setAttribute('role', 'status')
			container.setAttribute('aria-live', 'polite')
			document.body.appendChild(container)
		}

		return container
	}

	/**
	 * Build one toast in the container and return its controller.
	 */
	function createToast(onGone) {
		var container = getContainer()

		var toast = document.createElement('div')
		var content = document.createElement('div')
		content.className = 'g-toast__content'

		var close = document.createElement('button')
		close.type = 'button'
		close.className = 'g-toast__close'
		close.setAttribute('aria-label', 'Dismiss')
		close.innerHTML = '&times;'

		toast.appendChild(content)
		toast.appendChild(close)
		container.appendChild(toast)

		var shown = false

		// Paint the toast in its hidden state first so the entrance transition
		// has something to move from.
		requestAnimationFrame(function () {
			shown = true
			toast.classList.add('is-visible')
		})

		var timer = null
		var duration = 0
		var dismissed = false

		function dismiss() {
			if (dismissed) {
				return
			}
			dismissed = true

			if (timer) {
				clearTimeout(timer)
				timer = null
			}

			toast.classList.remove('is-visible')
			toast.classList.add('is-leaving')

			if (onGone) {
				onGone()
			}

			setTimeout(function () {
				toast.remove()
				if (!container.children.length) {
					container.remove()
				}
			}, EXIT_MS)
		}

		function schedule(ms) {
			if (timer) {
				clearTimeout(timer)
				timer = null
			}
			duration = ms
			if (duration > 0) {
				timer = setTimeout(dismiss, duration)
			}
		}

		close.addEventListener('click', dismiss)

		// Hold the toast while it is being read or reached for.
		toast.addEventListener('mouseenter', function () {
			if (timer) {
				clearTimeout(timer)
				timer = null
			}
		})

		toast.addEventListener('mouseleave', function () {
			if (!dismissed && !timer && duration > 0) {
				timer = setTimeout(dismiss, duration)
			}
		})

		return {
			update: function (message, type, ms) {
				toast.className = 'g-toast g-toast--' + (type || 'success') + (shown ? ' is-visible' : '')
				content.textContent = message
				schedule(ms)
			},
			dismiss: dismiss
		}
	}

	window.enroveShowToast = function (message, type, duration) {
		if (!message) {
			return null
		}

		if (!document.body) {
			document.addEventListener('DOMContentLoaded', function () {
				window.enroveShowToast(message, type, duration)
			}, { once: true })
			return null
		}

		var handle = createToast(null)
		handle.update(message, type || 'success', typeof duration === 'number' ? duration : 4000)

		return handle.dismiss
	}

	window.enroveStatusToast = function () {
		var handle = null

		return {
			set: function (message, type, duration) {
				if (!message || !document.body) {
					return
				}
				if (!handle) {
					handle = createToast(function () {
						handle = null
					})
				}
				handle.update(message, type || 'info', typeof duration === 'number' ? duration : 0)
			},
			hide: function () {
				if (handle) {
					handle.dismiss()
				}
			}
		}
	}

	/**
	 * Show anything PHP has queued, then drop the query args that produced it
	 * so a reload does not replay a stale outcome.
	 */
	window.enroveDrainToasts = function () {
		var queue = window.ENROVE_TOASTS

		if (!queue || !queue.length) {
			return
		}

		if (!document.body) {
			document.addEventListener('DOMContentLoaded', window.enroveDrainToasts, { once: true })
			return
		}

		var consumed = []

		while (queue.length) {
			var payload = queue.shift()
			if (!payload) {
				continue
			}

			(payload.toasts || []).forEach(function (item) {
				window.enroveShowToast(item.message, item.type, item.duration)

				if (item.anchor && window.enroveShowToggletip) {
					window.enroveShowToggletip(item.anchor, {
						message: item.message,
						hint: item.hint,
						type: item.type
					})
				}
			})

			consumed = consumed.concat(payload.consumedArgs || [])
		}

		if (!consumed.length || !window.history || !window.history.replaceState) {
			return
		}

		var url = new URL(window.location.href)
		var changed = false

		consumed.forEach(function (arg) {
			if (url.searchParams.has(arg)) {
				url.searchParams.delete(arg)
				changed = true
			}
		})

		if (changed) {
			window.history.replaceState({}, '', url)
		}
	}

	// The footer payload usually lands after this file, but a toast queued from
	// an earlier hook can beat it here.
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', window.enroveDrainToasts)
	} else {
		window.enroveDrainToasts()
	}
})()
