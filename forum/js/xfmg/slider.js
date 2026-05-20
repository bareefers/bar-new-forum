window.XFMG = window.XFMG || {}

;((window, document) =>
{
	'use strict'

	XFMG.ItemSlider = XF.Element.newHandler({
		options: {
			auto: false,
			loop: false,
			pager: false,
			item: 6,
			itemWide: 0,
			itemMedium: 0,
			itemNarrow: 0,
			pauseOnHover: false,
		},

		init ()
		{
			const items = this.target.querySelectorAll('.itemList-item--slider')
			items.forEach(item => item.classList.add('f-carousel__slide'))

			this.slider = new Carousel(
				this.target,
				this.getCarouselOptions(),
				this.getCarouselPlugins()
			)
		},

		getCarouselOptions ()
		{
			const options = {
				center: false,
				direction: XF.isRtl() ? 'rtl' : 'ltr',
				infinite: this.options.loop,
				l10n: XFMG.SliderL10n(),
				on: {
					ready: () =>
					{
						this.target.style.overflow = 'visible'
					},
				},
				Dots: this.options.pager,
				Navigation: true,
			}

			if (this.options.auto)
			{
				options.Autoplay = {
					pauseOnHover: this.options.pauseOnHover,
					showProgress: false,
				}
			}

			return options
		},

		getCarouselPlugins ()
		{
			const plugins = {}

			if (this.options.auto)
			{
				plugins.Autoplay = Autoplay
			}

			return plugins
		},
	})


	XFMG.SliderL10n = () =>
	{
		return {
			NEXT: XF.phrase('next_slide'),
			PREV: XF.phrase('previous_slide'),
			GOTO: XF.phrase('go_to_slide_x'),
		}
	}

	XF.Element.register('item-slider', 'XFMG.ItemSlider')
})(window, document)
