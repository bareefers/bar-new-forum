var PB = window.PB || {};

!function ($, window, document, _undefined)
{
	"use strict";

	PB.SimpleStatsOptions = XF.Element.newHandler({
		$container: null,
		$input: null,
		url: null,
		baseUrl: null,

		options: {
			container: '.js-pbSSOptions',
			url: null,
			baseUrl: null,
		},

		init: function ()
		{
			this.$input = this.$target;
			const $input = this.$input;
			this.$container = $(this.options.container);
			this.url = this.options.url ?? XF.canonicalizeUrl('admin.php?simplestats/options');
			this.baseUrl = this.options.baseUrl ?? XF.canonicalizeUrl('admin.php?simplestats');

			if (!this.url)
			{
				console.log('Url is empty');
				return;
			}

			$input.on('change submit', XF.proxy(this, 'handleAjax'));
		},

		requestUrl: '',

		handleAjax: function (event)
		{
			const t = this;
			const $input = this.$input;
			event.preventDefault();

			XF.ajax('POST', XF.canonicalizeUrl(t.url + '&query=' + $input.val()), null, function (data)
			{
				t.requestUrl = t.baseUrl + '&query=' + $input.val();

				if (!data.html || !data.html.content)
				{
					t.$container.html('');
					return;
				}

				history.pushState({}, document.title, t.requestUrl);

				XF.setupHtmlInsert(data.html, function ($html, container, onComplete)
				{
					t.$container.html(data.html.content);
					XF.activate(t.$container);

					t.loading = false;

					onComplete(true);
					return false;
				});
			})
		},
	});

	XF.Element.register('pb-ss-options', 'PB.SimpleStatsOptions');
}
(jQuery, window, document)