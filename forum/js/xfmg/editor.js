window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XF.Inserter = XF.extend(XF.Inserter, {

		__backup: {
			'_applyAppend': '__applyAppend',
		},

		_applyAppend (selectorOld, oldEl, newEl)
		{
			const validSelectors = ['.js-yourMediaList', '.js-yourAlbumsList', '.js-browseMediaList', '.js-browseAlbumsList']
			if (validSelectors.indexOf(selectorOld) < 0)
			{
				this.__applyAppend(selectorOld, oldEl, newEl)
				return
			}

			const placeholders = Array.from(oldEl.querySelectorAll('.itemList-item--placeholder'))
			const children = Array.from(newEl.children)

			if (!placeholders.length)
			{
				this.__applyAppend(selectorOld, oldEl, newEl)
				return
			}

			children.forEach(child => child.classList.add('itemList-item--placeholder--temp'))

			this.__applyAppend(selectorOld, oldEl, newEl)

			setTimeout(() =>
			{
				children.forEach(child => child.classList.remove('itemList-item--placeholder--temp'))
				placeholders.forEach(placeholder => placeholder.remove())

				XF.layoutChange()
			}, 10)
		},
	})

	XFMG.editorButton = {
		container: null,

		init ()
		{
			XFMG.editorButton.initializeDialog()
			XF.EditorHelpers.dialogs.gallery = new XFMG.EditorDialogGallery('gallery')

			if (FroalaEditor.COMMANDS.xfCustom_gallery)
			{
				FroalaEditor.COMMANDS.xfCustom_gallery.callback = XFMG.editorButton.callback
			}
		},

		initializeDialog ()
		{
			XFMG.EditorDialogGallery = XF.extend(XF.EditorDialog, {
				cache: false,
				container: null,

				_init (overlay)
				{
					const container = overlay.container
					XF.onDelegated(container, 'change', '.js-mediaPicker', XF.proxy(this, 'pick'))
					this.container = container

					XF.on(document.querySelector('#xfmg_editor_dialog_form'), 'submit', XF.proxy(this, 'submit'))
				},

				_afterShow (overlay)
				{
					this.tabCounts = {
						yourMedia: 0,
						yourAlbums: 0,
						browseMedia: 0,
						browseAlbums: 0,
					}
				},

				pick (e)
				{
					const checkbox = e.target
					const checked = checkbox.checked
					const item = checkbox.parentNode

					if (checked)
					{
						this.checked(item)
					}
					else
					{
						this.unchecked(item)
					}
				},

				checked (item, checkbox)
				{
					item.classList.add('is-selected')

					const pane = item.closest('ul > li.is-active')
					const tab = this.container.querySelector(pane.dataset.tab)
					const tabType = tab.getAttribute('id')

					if (!tab.classList.contains('has-selected'))
					{
						tab.classList.add('has-selected')
					}

					const valueEl = this.container.querySelector('.js-embedValue')
					const value = JSON.parse(valueEl.value)
					const type = item.dataset.type
					const id = item.dataset.id
					const itemId = type + '-' + id

					if (XF.hasOwn(value, itemId))
					{
						return
					}

					value[itemId] = 1

					const countEl = tab.querySelector('.js-tabCounter')

					this.tabCounts[tabType] += 1
					countEl.textContent = this.tabCounts[tabType]

					valueEl.value = JSON.stringify(value)
				},

				unchecked (item, checkbox)
				{
					item.classList.remove('is-selected')

					const pane = item.closest('ul > li.is-active')
					const tab = this.container.querySelector(pane.dataset.tab)
					const tabType = tab.getAttribute('id')

					const valueEl = this.container.querySelector('.js-embedValue')
					const value = JSON.parse(valueEl.value)
					const type = item.dataset.type
					const id = item.dataset.id
					const itemId = type + '-' + id

					if (!XF.hasOwn(value, itemId))
					{
						return
					}

					delete value[itemId]

					const countEl = tab.querySelector('.js-tabCounter')

					this.tabCounts[tabType] -= 1

					if (this.tabCounts[tabType])
					{
						countEl.textContent = this.tabCounts[tabType]
					}
					else
					{
						countEl.textContent = '0'
						tab.classList.remove('has-selected')
					}

					valueEl.value = JSON.stringify(value)
				},

				submit (e)
				{
					e.preventDefault()

					const ed = this.ed
					const overlay = this.overlay
					const valueEl = this.container.querySelector('.js-embedValue')
					const value = JSON.parse(valueEl.value)
					let output = ''

					for (const key in value)
					{
						if (!XF.hasOwn(value, key))
						{
							continue
						}

						const parts = key.split('-')
						const type = parts[0]
						const id = parts[1]

						output += XF.htmlspecialchars('[GALLERY=' + type + ', ' + parseInt(id) + '][/GALLERY]')
						output += '<p><br></p>'
					}

					ed.selection.restore()
					ed.html.insert(output)

					if (typeof XF.EditorHelpers.normalizeAfterInsert === 'function')
					{
						XF.EditorHelpers.normalizeAfterInsert(ed)
					}

					overlay.hide()
				},
			})
		},

		callback ()
		{
			XF.EditorHelpers.loadDialog(this, 'gallery')
		},
	}

	XF.on(document, 'editor:first-start', XFMG.editorButton.init)
})(window, document)
