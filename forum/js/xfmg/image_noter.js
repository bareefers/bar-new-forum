window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XFMG.ImageNoter = XF.Element.newHandler({

		options: {
			image: '.js-mediaImage',
			toggleId: '#js-noterToggle',
			editUrl: null,
		},

		image: null,
		cropper: null,
		toggle: null,
		cropBox: null,
		editingNote: null,

		active: false,
		trigger: null,
		tooltip: null,

		init ()
		{
			const container = this.target
			const image = container.querySelector(this.options.image)
			const toggle = document.querySelector(this.options.toggleId)

			if (!image || !image.matches('img'))
			{
				console.error('Image noter must contain an img element')
			}

			this.image = image

			if (!image.complete)
			{
				XF.on(image, 'load', XF.proxy(this, 'prepareNotes'))
			}
			else
			{
				this.prepareNotes()
			}

			XF.on(window, 'resize', XF.proxy(this, 'prepareNotes'), { passive: true })

			// no toggle == no permission to add notes
			if (toggle)
			{
				this.toggle = toggle
				XF.on(toggle, 'click', XF.proxy(this, 'toggleNewNoteEditor'))
			}
		},

		prepareNotes ()
		{
			const image = this.image
			const width = image.width
			const naturalWidth = image.naturalWidth
			const multiplier = (naturalWidth / width)
			const notes = Array.from(this.target.querySelectorAll('.js-mediaNote'))

			notes.forEach(note =>
			{
				const coords = JSON.parse(note.dataset.noteData) || {}

				for (const key in coords)
				{
					if (!XF.hasOwn(coords, key))
					{
						continue
					}

					// we need to keep the original values so adjusted values should be suffixed with _
					coords[key + '_'] = (coords[key] / multiplier)
				}

				// adjusted values based on multiplier
				note.style.left = `${coords['tag_x1_']}px`
				note.style.top = `${coords['tag_y1_']}px`
				note.style.width = `${coords['tag_width_']}px`
				note.style.height = `${coords['tag_height_']}px`

				if (!XF.DataStore.get(note, 'prepared'))
				{
					this.initNote(note)
				}
			})
		},

		initNote (note)
		{
			const element = this.getTooltipElement(note)

			const tooltip = new XF.TooltipElement(element.cloneNode(true).outerHTML, {
				extraClass: 'tooltip--mediaNote tooltip--mediaNote--plain',
				noTouch: false,
				html: true,
			})

			const mediaContainer = this.target.querySelector('.media-container-image')

			tooltip.addSetupCallback(el =>
			{
				XF.on(el, 'mouseenter', () => mediaContainer.classList.add('is-tooltip-active'))
				XF.on(el, 'mouseleave', () => mediaContainer.classList.remove('is-tooltip-active'))

				const edit = el.querySelector('.js-mediaNoteTooltipEdit')
				if (edit)
				{
					XF.on(edit, 'click', XF.proxy(this, 'editNote', note))
				}
			})

			const trigger = new XF.TooltipTrigger(note, tooltip, {
				maintain: true,
				trigger: 'hover focus click',
			})

			trigger.init()

			XF.DataStore.set(note, 'prepared', true)
			XF.DataStore.set(note, 'tooltip', tooltip)
			XF.DataStore.set(note, 'trigger', trigger)
			XF.display(note)

			element.remove()
		},

		getTooltipElement (note)
		{
			return XF.findRelativeIf('< .js-mediaContainerImage | .js-mediaNoteTooltip' + note.dataset.noteId, note)
		},

		editNote (note)
		{
			const coords = JSON.parse(note.dataset.noteData)

			XF.trigger(note, 'tooltip:hide')

			this.cropper = new Cropper(this.image, {
				viewMode: 2,
				dragMode: 'none',
				aspectRatio: 1,
				modal: false,
				highlight: false,
				movable: false,
				rotatable: false,
				scalable: false,
				zoomable: false,
				toggleDragModeOnDblclick: false,
				data: {
					x: coords['tag_x1'],
					y: coords['tag_y1'],
					width: coords['tag_width'],
					height: coords['tag_height'],
				},
				ready: () =>
				{
					this.editingNote = note
					this.cropBox = this.target.querySelector('.cropper-crop-box')

					if (!this.tooltip && !this.trigger)
					{
						this.tooltip = new XF.TooltipElement(XF.proxy(this, 'getEditNoteTooltipContent'), {
							extraClass: 'tooltip--mediaNote',
							html: true,
							loadRequired: true,
						})
						this.trigger = new XF.TooltipTrigger(this.cropBox, this.tooltip, {
							maintain: true,
							trigger: '',
							onShow (trigger, tooltip)
							{
								const tooltipEl = tooltip.tooltip
								const textarea = tooltipEl.querySelector('textarea')

								if (textarea)
								{
									XF.on(tooltipEl, 'tooltip:shown', () => XF.trigger(textarea, 'autosize'))
								}
							},
						})

						this.trigger.init()
						XF.trigger(this.cropBox, 'tooltip:show')

						// seems to workaround issue where cropend doesn't fire after the first crop
						XF.trigger(this.image, 'cropend')
					}
				},
				cropstart: XF.proxy(this, 'editNoteCropstart'),
				cropend: XF.proxy(this, 'editNoteCropend'),
			})
		},

		editNoteCropstart (e)
		{
			if (!this.trigger)
			{
				return
			}

			XF.trigger(this.cropBox, 'tooltip:hide')
		},

		editNoteCropend (e)
		{
			XF.trigger(this.cropBox, 'tooltip:show')
			XF.on(this.tooltip.tooltip, 'tooltip:shown', e =>
			{
				const tooltip = e.target
				const coords = this.getCoordsFromCropper()

				tooltip.querySelector('.js-noteData').value = JSON.stringify(coords)
			})
		},

		getEditNoteTooltipContent (onContent)
		{
			const options = {
				skipDefault: true,
				skipError: true,
				global: false,
			}

			XF.ajax(
				'get',
				this.options.editUrl,
				{ note_id: this.editingNote.dataset.noteId },
				data => { this.editNoteLoaded(data, onContent) },
				options,
			)
		},

		editNoteLoaded: function (data, onContent)
		{
			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				onContent(html)

				const cancel = html.querySelector('.js-cancelButton')
				XF.on(cancel, 'click', XF.proxy(this, 'editNoteCancel'))

				XF.on(html, 'ajax-submit:response', XF.proxy(this, 'editNoteHandle'))
			})
		},

		editNoteCancel (message)
		{
			if (this.cropBox)
			{
				XF.trigger(this.cropBox, 'tooltip:hide')
				this.cropBox = null
			}

			if (this.tooltip)
			{
				this.tooltip.destroy()
				this.tooltip = null
				this.trigger = null
			}

			this.cropper.destroy()
			this.editingNote = null

			if (typeof message === 'string')
			{
				XF.flashMessage(message, 3000)
			}
		},

		editNoteHandle (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			const noteTooltip = XF.DataStore.get(this.editingNote, 'tooltip')
			noteTooltip.destroy()

			this.editingNote.remove()

			if (data.deleted)
			{
				this.editNoteCancel(data.message)
			}
			else
			{
				XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
				{
					const imageContainer = this.target.querySelector('.js-mediaContainerImage')
					imageContainer.prepend(html)
					this.editNoteCancel(data.message)
					XF.activate(html)
				})

				setTimeout(() => this.prepareNotes(), 500)
			}
		},

		toggleNewNoteEditor: function ()
		{
			if (this.active)
			{
				this.disableNewNoteEditor()
			}
			else
			{
				this.enableNewNoteEditor()
			}
		},

		disableNewNoteEditor (message)
		{
			if (!this.active)
			{
				return
			}

			if (this.cropBox)
			{
				XF.trigger(this.cropBox, 'tooltip:hide')
			}

			if (this.tooltip)
			{
				this.tooltip.destroy()
				this.tooltip = null
				this.trigger = null
			}

			this.cropper.destroy()

			const toggle = this.toggle

			toggle.querySelector('.button-text').innerHTML = XF.htmlspecialchars(toggle.dataset.inactiveLabel)
			toggle.querySelector('i.fa--xf')?.remove()
			if (toggle.dataset.inactiveIcon)
			{
				toggle.prepend(XF.createElementFromString(XF.Icon.getIcon(
					'default',
					toggle.dataset.inactiveIcon,
				)))
			}

			if (message)
			{
				XF.flashMessage(message, 3000)
			}
			else
			{
				XF.flashMessage(toggle.dataset.inactiveMessage, 3000)
			}
			this.active = false
		},

		enableNewNoteEditor ()
		{
			if (this.active)
			{
				return
			}

			this.cropper = new Cropper(this.image, {
				viewMode: 2,
				dragMode: 'crop',
				aspectRatio: 1,
				modal: false,
				highlight: false,
				autoCrop: false,
				movable: false,
				rotatable: false,
				scalable: false,
				zoomable: false,
				toggleDragModeOnDblclick: false,
				ready: XF.proxy(this, 'newNoteReady'),
				cropstart: XF.proxy(this, 'newNoteCropstart'),
				cropend: XF.proxy(this, 'newNoteCropend'),
			})
		},

		newNoteReady ()
		{
			const toggle = this.toggle

			toggle.querySelector('.button-text').innerHTML = XF.htmlspecialchars(toggle.dataset.activeLabel)
			toggle.querySelector('i.fa--xf')?.remove()
			if (toggle.dataset.activeIcon)
			{
				toggle.prepend(XF.createElementFromString(XF.Icon.getIcon(
					'default',
					toggle.dataset.activeIcon,
				)))
			}

			this.cropBox = this.target.querySelector('.cropper-crop-box')

			XF.flashMessage(toggle.dataset.activeMessage, 3000)
			this.active = true
		},

		newNoteCropstart: function (e)
		{
			if (!this.trigger)
			{
				return
			}

			XF.trigger(this.cropBox, 'tooltip:hide')
		},

		newNoteCropend: function (e)
		{
			if (!this.tooltip && !this.trigger)
			{
				this.tooltip = new XF.TooltipElement(XF.proxy(this, 'getNewNoteTooltipContent'), {
					extraClass: 'tooltip--mediaNote',
					html: true,
					loadRequired: true,
				})
				this.trigger = new XF.TooltipTrigger(this.cropBox, this.tooltip, {
					maintain: true,
					trigger: '',
				})

				this.trigger.init()
			}

			XF.trigger(this.cropBox, 'tooltip:show')
			XF.on(this.tooltip.tooltip, 'tooltip:shown', e =>
			{
				const tooltip = e.target
				const coords = this.getCoordsFromCropper()

				tooltip.querySelector('.js-noteData').value = JSON.stringify(coords)
			})
		},

		getNewNoteTooltipContent (onContent)
		{
			const options = {
				skipDefault: true,
				skipError: true,
				global: false,
			}

			XF.ajax(
				'get',
				this.options.editUrl,
				{},
				data => { this.newNoteLoaded(data, onContent) },
				options,
			)
		},

		newNoteLoaded (data, onContent)
		{
			if (!data.html)
			{
				return
			}

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				onContent(html)

				const cancel = html.querySelector('.js-cancelButton')
				XF.on(cancel, 'click', XF.proxy(this, 'newNoteCancel'))

				XF.on(html, 'ajax-submit:response', XF.proxy(this, 'newNoteHandle'))
			})
		},

		newNoteCancel ()
		{
			XF.trigger(this.cropBox, 'tooltip:hide')
			this.cropper.clear()
		},

		newNoteHandle (e)
		{
			const { data } = e

			if (data.errors || data.exception)
			{
				return
			}

			e.preventDefault()

			XF.setupHtmlInsert(data.html, (html, container, onComplete) =>
			{
				const imageContainer = this.target.querySelector('.js-mediaContainerImage')
				imageContainer.prepend(html)
				this.disableNewNoteEditor(data.message)
				XF.activate(html)
			})

			setTimeout(() => this.prepareNotes(), 500)
		},

		getCoordsFromCropper: function ()
		{
			const image = this.image
			const data = this.cropper.getData(true)

			// naming mostly for XFMG 1.x backwards compatibility
			return {
				tag_x1: data.x,
				tag_y1: data.y,
				tag_width: data.width,
				tag_height: data.height,
			}
		},
	})

	XF.Element.register('image-noter', 'XFMG.ImageNoter')
})(window, document)
