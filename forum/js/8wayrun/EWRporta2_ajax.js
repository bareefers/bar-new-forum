/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.ArticlesMason = function($grid)
	{
		$grid.imagesLoaded(function(){
			$grid.masonry({
				itemSelector: $grid.data('items'),
			});
		});
	}

	// *********************************************************************
	
	XenForo.InfiniteScroll = function($grid)
	{
		$grid.infinitescroll({
			errorCallback: function() { $($grid.data('control')).hide(); },
			itemSelector: $grid.data('items'),
			navSelector: $grid.data('nav'),
			nextSelector: $grid.data('next'),
		}, function( newElements ) {
			$($grid.data('control')).show();
			
			if ($grid.data('masonry'))
			{
				var $newElems = $( newElements ).css({ opacity: 0 });
				
				$grid.imagesLoaded(function(){
					$newElems.animate({ opacity: 1 });
					$grid.masonry( 'appended', $newElems, true ); 
				});
			}
		});
	}

	// *********************************************************************
	
	XenForo.InfiniteControl = function($control)
	{
		var target = $($control.data('target'));
		var pager = $($control.data('pager'));
		var pause = $control.find('.pause');
		var resume = $control.find('.resume');
	
		pause.click( function() {
			target.infinitescroll('pause');
			pager.show();
			pause.hide();
			resume.show();
		});
		
		resume.find('.button').click( function() {
			target.infinitescroll('resume');
			target.infinitescroll('retrieve');
			pause.show();
			resume.hide();
		});
	}

	// *********************************************************************
	
	XenForo.SortColumn = function($column)
	{
		$column.sortable({
			connectWith: ".SortColumn",
			cursor: "move",
			distance: 10,
			items: "div.portlet:not(.locked)",
			placeholder: "portlet-placeholder",
			revert: "true",
			tolerance: "pointer",
			update : function () { 
				$('input.position').each(function() {           
					var parentID = ($(this).parent().parent().attr('ID')).replace('_', '');
					$(this).val( parentID );
				});
			}
		});

		$column.disableSelection();
	}

	// *********************************************************************

	XenForo.CategoryEditor = function($form)
	{
		$form.bind('AutoValidationComplete', function(e)
		{
			if (e.ajaxData.templateHtml)
			{
				$('#categories_'+e.ajaxData.thread_id).replaceWith(e.ajaxData.templateHtml);
			}
		});
	}

	// *********************************************************************

	XenForo.TagEditor = function($form)
	{
		$form.bind('AutoValidationComplete', function(e)
		{
			if (e.ajaxData._redirectMessage)
			{
				$form.find('input[name="new_tags"]').val('');
			}
			
			if (e.ajaxData.templateHtml)
			{
				$('#tags_'+e.ajaxData.thread_id).replaceWith(e.ajaxData.templateHtml);
			}
		});
	}

	// *********************************************************************

	XenForo.WidlinkEditor = function($form)
	{
		$form.bind('AutoValidationComplete', function(e)
		{
			if (e.ajaxData.templateHtml)
			{
				if ($('#widlink_'+e.ajaxData.widlink_id).length != 0)
				{
					$('#widlink_'+e.ajaxData.widlink_id).replaceWith(e.ajaxData.templateHtml);
				}
				else
				{
					$(e.ajaxData.templateHtml).xfInsert('appendTo', '#_disabled', 'xfFadeIn');
				}
			}
		});
	}

	// *********************************************************************

	XenForo.WidlinkDeleter = function($form)
	{
		$form.bind('AutoValidationComplete', function(e)
		{
			$('#widlink_'+$form.data('widlink')).xfRemove("xfFadeOut");
		});
	}

	// *********************************************************************

	XenForo.ShowMoreLink = function($link)
	{
		var target = $link.data('target');
		$('#'+target).hide();
		
		$link.click(function(e)
		{
			e.preventDefault();
			$('#'+target).show();
			$link.hide();
		});
	}

	// *********************************************************************

	XenForo.ShowMoreCheck = function($input)
	{
		var target = $input.data('target');
		
		if (!$input.is(':checked'))
		{
			$('#'+target).hide();
		}
			
		$input.click(function(e)
		{
			if ($input.is(':checked'))
			{
				$('#'+target).slideDown(1000);
			}
			else
			{
				$('#'+target).slideUp(1000);
			}
		});
	}

	// *********************************************************************

	XenForo.register('.ArticlesMason', 'XenForo.ArticlesMason');
	XenForo.register('.InfiniteScroll', 'XenForo.InfiniteScroll');
	XenForo.register('.InfiniteControl', 'XenForo.InfiniteControl');
	XenForo.register('.SortColumn', 'XenForo.SortColumn');
	XenForo.register('.CategoryEditor', 'XenForo.CategoryEditor');
	XenForo.register('.TagEditor', 'XenForo.TagEditor');
	XenForo.register('.WidlinkEditor', 'XenForo.WidlinkEditor');
	XenForo.register('.WidlinkDeleter', 'XenForo.WidlinkDeleter');
	XenForo.register('.ShowMoreLink', 'XenForo.ShowMoreLink');
	XenForo.register('.ShowMoreCheck', 'XenForo.ShowMoreCheck');
}
(jQuery, this, document);