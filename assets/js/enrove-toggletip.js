/**
 * Enrove toggletips.
 *
 * A toast says an action failed; a toggletip says why, at the control that
 * failed. It is anchored to the button the operator just pressed, stays up
 * until it is dismissed, and carries a next step rather than only a diagnosis.
 *
 * window.enroveShowToggletip(anchor, options)
 *   anchor   Element or selector. Nothing is shown if it cannot be found.
 *   options  message  Plain text. What went wrong. Required.
 *            hint     Plain text. What to do about it. Optional.
 *            type     'error' | 'warning' | 'info'   (default 'error')
 *            duration Milliseconds before auto-dismiss. 0 (default) holds it
 *                     until it is dismissed.
 *
 * Returns a hide() for the caller, or null if nothing was shown.
 *
 * Unlike .g-tooltip-button, the bubble is not nested inside its anchor: the
 * anchors here are real <button>s, which cannot legally contain the dismiss
 * button, and a bubble clipped by a card's overflow would be unreadable. It is
 * appended to <body>, positioned against the anchor's rect, and follows it on
 * scroll and resize.
 *
 * Only one toggletip is open at a time. A second call replaces the first, so a
 * repeated failure never stacks explanations on top of each other.
 */
(function () {
	'use strict'

	var GAP = 8
	var ARROW = 6
	var EDGE = 8

	var open = null
	var uid = 0

	function hideOpen() {
		if (open) {
			open.hide()
		}
	}

	function resolveAnchor(anchor) {
		if (!anchor) {
			return null
		}

		if (typeof anchor === 'string') {
			return document.querySelector(anchor)
		}

		return anchor.nodeType === 1 ? anchor : null
	}

	function place(node, anchorEl) {
		var a = anchorEl.getBoundingClientRect()
		var b = node.getBoundingClientRect()
		var viewportWidth = window.innerWidth || document.documentElement.clientWidth
		var viewportHeight = window.innerHeight || document.documentElement.clientHeight

		var adminBar = document.getElementById('wpadminbar')
		var topInset = 0
		if (adminBar) {
			var barRect = adminBar.getBoundingClientRect()
			if (barRect.bottom > 0) {
				topInset = barRect.bottom
			}
		}

		var safeTop = Math.max(EDGE, topInset + EDGE)
		var needed = b.height + GAP + ARROW

		// Below the anchor by preference. A save button sits at the foot of its
		// card, so there is usually room underneath — and the fields the
		// operator has to correct are above it, where an explanation covering
		// them would help nobody.
		var placement = 'bottom'
		if (viewportHeight - a.bottom - EDGE < needed && a.top - safeTop >= needed) {
			placement = 'top'
		}

		var top = placement === 'bottom'
			? a.bottom + GAP + ARROW
			: a.top - GAP - ARROW - b.height

		// Aligned to the anchor's leading edge, then pulled back inside the
		// viewport if the bubble is wider than the room to its right.
		var maxLeft = Math.max(EDGE, viewportWidth - EDGE - b.width)
		var left = Math.min(Math.max(EDGE, a.left), maxLeft)

		node.style.top = Math.round(top) + 'px'
		node.style.left = Math.round(left) + 'px'
		node.setAttribute('data-placement', placement)

		// The arrow keeps pointing at the anchor even when the bubble has been
		// shifted along, so it is always clear which control this is about.
		var arrowX = a.left + (a.width / 2) - left
		arrowX = Math.max(14, Math.min(arrowX, Math.max(14, b.width - 14)))
		node.style.setProperty('--g-toggletip-arrow-x', Math.round(arrowX) + 'px')
	}

	window.enroveShowToggletip = function (anchor, options) {
		options = options || {}

		var anchorEl = resolveAnchor(anchor)
		var message = String(options.message || '')

		if (!anchorEl || !document.body || message === '') {
			return null
		}

		hideOpen()

		uid += 1

		var node = document.createElement('div')
		node.className = 'g-toggletip g-toggletip--' + (options.type || 'error')
		node.id = 'g-toggletip-' + uid
		// Announced rather than focused: the operator keeps their place, and a
		// screen reader still hears why the press did not take.
		node.setAttribute('role', 'status')
		node.setAttribute('aria-live', 'polite')

		var body = document.createElement('div')
		body.className = 'g-toggletip__body'

		var messageNode = document.createElement('p')
		messageNode.className = 'g-toggletip__message'
		messageNode.textContent = message
		body.appendChild(messageNode)

		var hint = String(options.hint || '')
		if (hint !== '') {
			var hintNode = document.createElement('p')
			hintNode.className = 'g-toggletip__hint'
			hintNode.textContent = hint
			body.appendChild(hintNode)
		}

		var close = document.createElement('button')
		close.type = 'button'
		close.className = 'g-toggletip__close'
		close.setAttribute('aria-label', options.dismissLabel || 'Dismiss')
		close.innerHTML = '&times;'

		var arrow = document.createElement('span')
		arrow.className = 'g-toggletip__arrow'
		arrow.setAttribute('aria-hidden', 'true')

		node.appendChild(body)
		node.appendChild(close)
		node.appendChild(arrow)
		document.body.appendChild(node)

		// Reading the anchor's own description aloud saves a screen-reader user
		// from hunting for the explanation after tabbing back to the button.
		var previousDescribedBy = anchorEl.getAttribute('aria-describedby')
		anchorEl.setAttribute('aria-describedby', node.id)

		place(node, anchorEl)
		requestAnimationFrame(function () {
			node.classList.add('is-visible')
		})

		var timer = null
		var hidden = false

		function reposition() {
			if (!hidden) {
				place(node, anchorEl)
			}
		}

		function onKeydown(event) {
			if (event.key === 'Escape' || event.key === 'Esc') {
				hide()
			}
		}

		function onPointerDown(event) {
			if (!node.contains(event.target)) {
				hide()
			}
		}

		function hide() {
			if (hidden) {
				return
			}
			hidden = true

			if (timer) {
				clearTimeout(timer)
				timer = null
			}

			if (open && open.node === node) {
				open = null
			}

			if (previousDescribedBy === null) {
				anchorEl.removeAttribute('aria-describedby')
			} else {
				anchorEl.setAttribute('aria-describedby', previousDescribedBy)
			}

			document.removeEventListener('keydown', onKeydown)
			document.removeEventListener('pointerdown', onPointerDown, true)
			window.removeEventListener('resize', reposition)
			window.removeEventListener('scroll', reposition, true)

			node.classList.remove('is-visible')
			setTimeout(function () {
				if (node.parentNode) {
					node.parentNode.removeChild(node)
				}
			}, 150)
		}

		close.addEventListener('click', hide)
		document.addEventListener('keydown', onKeydown)
		window.addEventListener('resize', reposition)
		// Captured, so the bubble keeps up with a scrolling panel and not just
		// the window.
		window.addEventListener('scroll', reposition, true)

		// Attached a tick late: the click that opened this toggletip is still
		// travelling, and would otherwise close it again immediately.
		setTimeout(function () {
			if (!hidden) {
				document.addEventListener('pointerdown', onPointerDown, true)
			}
		}, 0)

		var duration = typeof options.duration === 'number' ? options.duration : 0
		if (duration > 0) {
			timer = setTimeout(hide, duration)
		}

		open = { node: node, hide: hide }

		return hide
	}

	window.enroveHideToggletip = hideOpen
})()
