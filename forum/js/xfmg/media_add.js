window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XFMG.attachmentManager = null

	XFMG.MediaManager = XF.Element.newHandler({

		// Some option defaults set on the attachment-manager element
		options: {
			mediaActionUrl: null,
			onInsertHandler: '.js-mediaOnInsertHandler',
		},

		init ()
		{
			XFMG.attachmentManager = XF.Element.applyHandler(this.target, 'attachment-manager', this.options)

			// Merge options from attachment manager
			this.options = XF.extendObject({}, this.options, XFMG.attachmentManager.options)

			const filesContainer = XFMG.attachmentManager.filesContainer
			XF.onDelegated(filesContainer, 'click', this.options.actionButton, XF.proxy(this, 'actionButtonClick'))
		},

		actionButtonClick (e)
		{
			e.preventDefault()

			let target = e.target
			if (!target.matches('button'))
			{
				target = target.closest('button')
			}

			const action = target.getAttribute('data-action')
			const row = target.closest(this.options.fileRow)

			switch (action)
			{
				case 'delete':
					this.deleteMediaItem(row)
					break
			}
		},

		deleteMediaItem (row)
		{
			const tempMediaId = row.dataset.tempMediaId
			const mediaActionUrl = this.options.mediaActionUrl

			if (!tempMediaId)
			{
				return
			}

			if (mediaActionUrl)
			{
				XF.ajax(
					'post',
					mediaActionUrl,
					{ delete: tempMediaId },
					data =>
					{
						if (data.delete)
						{
							XFMG.attachmentManager.removeFileRow(row)
						}
					},
					{ skipDefaultSuccess: true },
				)
			}
			else
			{
				XFMG.attachmentManager.removeFileRow(row)
			}
		},
	})

	XFMG.LinkChecker = XF.Element.newHandler({

		options: {
			pasteInput: '.js-pasteInput',
			pasteError: '.js-pasteError',
		},

		pasteInput: null,
		pasteError: null,

		attachmentManager: null,

		init ()
		{
			const target = this.target

			this.attachmentManager = XFMG.attachmentManager

			this.pasteInput = target.querySelector(this.options.pasteInput)
			if (!this.pasteInput)
			{
				console.error('No input to monitor for pasted text.')
				return
			}

			this.pasteError = target.querySelector(this.options.pasteError)

			XF.on(this.pasteInput, 'paste', XF.proxy(this, 'paste'))
			XF.on(target, 'ajax-submit:before', XF.proxy(this, 'beforeSubmit'))
			XF.on(target, 'ajax-submit:response', XF.proxy(this, 'complete'))
			XF.on(target, 'ajax-submit:error', XF.proxy(this, 'error'))
		},

		paste (e)
		{
			const pasteError = this.pasteError

			setTimeout(() =>
			{
				XF.trigger(this.target, 'submit')
				XF.Transition.removeClassTransitioned(pasteError, 'is-active')
			}, 100)
		},

		beforeSubmit (e)
		{
			if (this.attachmentManager == null)
			{
				e.preventSubmit = true
				console.error('No attachment manager available on %o.', this.target)
			}
		},

		complete (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			this.pasteInput.value = ''

			XF.hideOverlays()

			this.attachmentManager.insertUploadedRow(data.attachment)
		},

		error (e)
		{
			const { data } = e
			const pasteError = this.pasteError

			if (pasteError)
			{
				e.preventDefault()

				pasteError.querySelector('div').textContent = data.errors[0]
				XF.Transition.addClassTransitioned(pasteError, 'is-active')

				this.pasteInput.value = ''
			}
		},
	})

	XF.Element.register('media-manager', 'XFMG.MediaManager')
	XF.Element.register('link-checker', 'XFMG.LinkChecker')
})(window, document)
