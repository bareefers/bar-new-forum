window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XFMG.ImageEditor = XF.Element.newHandler({

		options: {
			image: '.js-mediaImg',
			cropData: '.js-cropData',
			move: '.js-ctrlDragMove',
			crop: '.js-ctrlDragCrop',
			zoomIn: '.js-ctrlZoomIn',
			zoomOut: '.js-ctrlZoomOut',
			rotateLeft: '.js-ctrlRotateLeft',
			rotateRight: '.js-ctrlRotateRight',
			flipH: '.js-ctrlFlipH',
			flipV: '.js-ctrlFlipV',
			clear: '.js-ctrlClear',
		},

		image: null,
		cropper: null,

		init ()
		{
			const form = this.target
			const image = form.querySelector(this.options.image)
			const cropData = form.querySelector(this.options.cropData)

			if (!image || !image.matches('img'))
			{
				console.error('Image editor must contain an img element')
			}

			this.image = image

			this.cropper = new Cropper(image, {
				viewMode: 2,
				autoCrop: false,
				ready: () =>
				{
					XF.on(form.querySelector(`${ this.options.move }`), 'click', XF.proxy(this, 'dragMode'))
					XF.on(form.querySelector(`${ this.options.crop }`), 'click', XF.proxy(this, 'dragMode'))

					XF.on(form.querySelector(`${ this.options.zoomIn }`), 'click', XF.proxy(this, 'zoom'))
					XF.on(form.querySelector(`${ this.options.zoomOut }`), 'click', XF.proxy(this, 'zoom'))

					XF.on(form.querySelector(`${ this.options.rotateLeft }`), 'click', XF.proxy(this, 'rotate'))
					XF.on(form.querySelector(`${ this.options.rotateRight }`), 'click', XF.proxy(this, 'rotate'))

					XF.on(form.querySelector(`${ this.options.flipH }`), 'click', XF.proxy(this, 'flip'))
					XF.on(form.querySelector(`${ this.options.flipV }`), 'click', XF.proxy(this, 'flip'))

					XF.on(form.querySelector(this.options.clear), 'click', XF.proxy(this, 'clear'))
				},
				crop: (e) =>
				{
					// true == rounded values
					cropData.value = JSON.stringify(this.cropper.getData(true))
				},
			})
		},

		dragMode (e)
		{
			const button = e.currentTarget

			if (button.matches(this.options.move))
			{
				this.cropper.setDragMode('move')
			}
			else if (button.matches(this.options.crop))
			{
				this.cropper.setDragMode('crop')
			}
		},

		zoom (e)
		{
			const button = e.currentTarget

			if (button.matches(this.options.zoomIn))
			{
				this.cropper.zoom(0.1)
			}
			else if (button.matches(this.options.zoomOut))
			{
				this.cropper.zoom(-0.1)
			}
		},

		rotate: function (e)
		{
			const button = e.currentTarget

			if (button.matches(this.options.rotateLeft))
			{
				this.cropper.rotate(-10)
			}
			else if (button.matches(this.options.rotateRight))
			{
				this.cropper.rotate(10)
			}
		},

		flip (e)
		{
			const button = e.currentTarget
			let scale = button.dataset.scale

			if (button.matches(this.options.flipH))
			{
				this.cropper.scaleX(scale)
			}
			else if (button.matches(this.options.flipV))
			{
				this.cropper.scaleY(scale)
			}

			scale *= -1
			button.dataset.scale = scale
		},

		clear: function (e)
		{
			this.cropper.clear()
		},
	})

	XF.Element.register('image-editor', 'XFMG.ImageEditor')
})(window, document)
