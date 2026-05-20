window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XF.Inserter = XF.extend(XF.Inserter, {

		__backup: {
			'_applyReplace': '__applyReplace',
		},

		inProgress: false,

		_applyReplace: function (selectorOld, oldEl, newEl)
		{
			if (selectorOld != '.js-filmStrip')
			{
				this.__applyReplace(selectorOld, oldEl, newEl)
				return
			}

			if (this.inProgress)
			{
				return
			}

			this.inProgress = true

			if (oldEl)
			{
				const oldButtons = Array.from(oldEl.querySelectorAll('.js-filmStrip-button'))
				oldButtons.forEach(button => button.classList.add('is-loading'))

				const oldItems = Array.from(oldEl.querySelectorAll('.js-filmStrip-item'))
				oldButtons.forEach(button =>
				{
					XF.Transition.addClassTransitioned(button, 'itemList-item--fading', () =>
					{
						if (newEl)
						{
							const newButtons = Array.from(newEl.querySelectorAll('.js-filmStrip-button'))
							newButtons.forEach(button => button.classList.add('is-loading'))

							oldEl.style.visibility = 'hidden'
							newEl.style.visibility = 'hidden'

							const newItems = Array.from(newEl.querySelectorAll('.js-filmStrip-item'))
							newItems.forEach(item => item.classList.add('itemList-item--fading'))

							oldEl.replaceWith(newEl)
							newEl.style.visibility = 'visible'

							newItems.forEach(item =>
							{
								XF.Transition.removeClassTransitioned(item, 'itemList-item--fading', () =>
								{
									newButtons.forEach(button => button.classList.remove('is-loading'))
									this.inProgress = false
								})
							})
						}
					})
				})
			}
		},
	})
})(window, document)
