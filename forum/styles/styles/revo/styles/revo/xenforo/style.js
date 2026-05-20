(function($) {

	var $doc = $(document),
		$w = $(window),
		// Browser/display
		isHD = false,
		isRTL = false,
		iOS = false,
		oldIE = false,
		canTransform = false,
		// Compact nodes variables
		compactNodes = false,
		compactNodesBreakPoint,
		compactNodesMaxColumns,
		compactNodesLastWidth = 0,
		// Compact threads
		compactThreads = false,
		compactThreadsLastWidth = 0,
		// Alternative navigation
		altNav = false,
		altNavActive = false,
		// Static navigation
		staticNavigation = false,
		staticNavigationConfig,
		/// Sticky footer
		stickyFooter = false,
		stickyFooterBlocks,
		stickyFooterContent,
		// Expand content
		expandContent = {expand: false, collapsed: false},
		// User profile
		scaleUserProfile = {scale: false, profile: false, message: false},
		// Event throttles
		resizeThrottled = false;

	/*
	* Alias for updateVisibleNavigationTabs to be overwritten later
	*/
	XenForo.forceUpdateNavigationTabs = function() {
		XenForo.updateVisibleNavigationTabs();
	};

	/*
	* Navigate to target
	*/
	function navigateTo(target)
	{
		var item = $(target).filter(':first');
		if (!item.length) return;

		var offset = Math.floor(item.offset().top) + 'px';
		$('html, body').animate({scrollTop: offset}, 150, 'easeInSine');
	}

	/*
	* Find all IDs and return them as array
	*
	* list = string: '1,2' '1,2,3-6'
	*/
	function findAllIDs(list, prefix)
	{
		var i, j, nodesRange, nodeIDs, startID, endID;

		nodesList = list.split(',');
		nodeIDs = [];
		for (i=0; i<nodesList.length; i++)
		{
			nodesRange = nodesList[i].split('-');
			startID = parseInt(nodesRange[0])
			endID = (nodesRange.length == 1) ? startID : parseInt(nodesRange[1]);

			if (isNaN(startID) || isNaN(endID) || startID < 1 || endID < startID || (endID - startID) > 100) return [];

			for (j=startID; j<=endID; j++)
			{
				nodeIDs.push(prefix + j);
			}
		}

		return nodeIDs;
	}

	/*
	* Get ID from list of classes
	*/
	function getID(classes, prefix)
	{
		var prefixLength = prefix.length,
			list, i, entry;

		classes = classes.replace("\t", ' ').replace("\n", ' ');
		list = classes.split(' ');

		for (i=0; i<list.length; i++)
		{
			entry = list[i].trim();
			if (entry.substr(0, prefixLength) == prefix)
			{
				entry = parseInt(entry.substr(prefixLength));
				if (!isNaN(entry) && entry)
				{
					return entry;
				}
			}
		}
		return 0;
	}

	/*
	* Toggle item
	*/
	function createToggle(link, items, cookieName, defaultCollapsed)
	{
		var collapsed = $.getCookie(cookieName),
			gotCookie = (collapsed != null),
			toggleItems = arguments.length > 4 ? arguments[4] : $();

		function collapse(animate)
		{
			collapsed = true;
			link.addClass('collapsed');
			toggleItems.addClass('collapsed');
			items.stop(true, true);

			if (animate)
			{
				items.slideUp(200, function() {
					items.css('display', 'none');
				});
			}
			else
			{
				items.css('display', 'none');
			}

			if (defaultCollapsed && gotCookie)
			{
				$.deleteCookie(cookieName);
			}
			else if(!defaultCollapsed)
			{
				$.setCookie(cookieName, '1');
				gotCookie = true;
			}
		}

		function expand(animate)
		{
			collapsed = false;
			link.removeClass('collapsed');
			toggleItems.removeClass('collapsed');
			items.stop(true, true);

			items.css('display', '');
			if (animate)
			{
				items.slideUp(0).slideDown(200);
			}

			if (!defaultCollapsed && gotCookie)
			{
				$.deleteCookie(cookieName);
			}
			else if(defaultCollapsed)
			{
				$.setCookie(cookieName, '0');
				gotCookie = true;
			}
		}

		collapsed = (gotCookie) ? collapsed : defaultCollapsed;
		if (collapsed)
		{
			collapse(false);
		}

		link.click(function() {
			if (collapsed)
			{
				expand(true);
			}
			else
			{
				collapse(true);
			}
			return false;
		});
	}

	/*
	* Compact nodes
	*/
	function fixCompactNodes(forceResize)
	{
		function resetNodes($this)
		{
			$this.removeClass('compact').find('.node').removeClass('compact-node lastRow firstRow firstColumn lastColumn').css('width', '').find('.nodeInfoInner').css('min-height', '');
		}

		if (!compactNodes) return;

		var windowWidth = $w.width();
		if (!forceResize && windowWidth == compactNodesLastWidth) return;
		compactNodesLastWidth = windowWidth;

		$('.node.auto-compact').each(function() {
			var $this = $(this),
				data = $this.attr('data-compact-width'),
				totalWidth = $this.width(),
				columns = 1,
				nodes,
				nodesCount,
				lastRow = 0,
				lastRowColumns = 1,
				rowWidth,
				lastNodeColumns;
			
			// Check if category width was changed
			if (!forceResize && totalWidth == data) return;
			$this.attr('data-compact-width', totalWidth);

			// Check if width is enough to fit 2 nodes on same row and if node is in compact mode
			columns = Math.min(Math.floor(totalWidth / compactNodesBreakPoint), compactNodesMaxColumns);
			if (!forceResize && columns < 2 && !$this.hasClass('compact')) return;

			// Remove compact mode
			if (columns < 2)
			{
				resetNodes($this);
				return;
			}

			// Get child nodes
			nodes = $this.find('.node.level_2');
			nodesCount = nodes.length;

			if (nodesCount < 2)
			{
				resetNodes($this);
				return;
			}

			// Change columns count for special sotuations
			if (nodesCount < columns)
			{
				// Fewer nodes than columns - split nodes evenly
				columns = nodesCount;
			} 
			else if (columns < nodesCount && (columns * 2) >= nodesCount && (Math.floor(nodesCount / 2) * 2) == nodesCount)
			{
				// Even number of columns. Too few for one row, too many for 2 rows - split it into 2 equal rows
				columns = nodesCount / 2;
			}

			// Calculate stuff
			resetNodes($this);
			$this.addClass('compact');
			lastRow = Math.floor((nodesCount - 1) / columns);
			lastRowColumns = nodesCount - lastRow * columns;
			rowWidth = Math.floor(1000 / columns) / 10 + '%';
			lastNodeColumns = columns - lastRowColumns + 1;

			// Align child nodes
			var lastCheckedRow = 2,
				columnItems = $(),
				maxHeight = 0,
				lastNode = false;
			nodes.each(function(i) {
				var row = Math.floor(i / columns),
					currentColumns = (row == lastRow) ? lastRowColumns : columns,
					node = $(this);

				if (row == 0)
				{
					node.addClass('firstRow');
				}
				if (row == lastRow)
				{
					node.addClass('lastRow');
				}

				if (currentColumns != columns && i == nodesCount - 1)
				{
					node.addClass('compact-node').css('width', Math.floor(1000 / columns) * lastNodeColumns / 10 + '%');
				}
				else
				{
					node.addClass('compact-node').css('width', rowWidth);
				}
				if (lastCheckedRow != row)
				{
					lastCheckedRow = row;
					if (lastNode !== false)
					{
						lastNode.addClass('lastColumn');
					}
					node.addClass('firstColumn');
					if (i == nodesCount - 1)
					{
						node.removeClass('compact-node');
						lastNode = node;
						return;
					}
				}
				lastNode = node;

				// Check height
				var mainBlock = node.find('.nodeInfoInner');
				if (mainBlock.length)
				{
					columnItems = columnItems.add(mainBlock);
					maxHeight = Math.max(maxHeight, Math.floor(mainBlock.height() + 1));

					if (i == (nodesCount - 1) || (columns * row + columns - 1) == i)
					{
						if (columnItems.length > 1)
						{
							columnItems.css('min-height', maxHeight + 'px');
						}
						columnItems = $();
						maxHeight = 0;
					}
				}
				else if (maxHeight)
				{
					columnItems = $();
					maxHeight = 0;
				}
			});
			if (lastNode !== false)
			{
				lastNode.addClass('lastColumn');
			}
		});
	}

	/*
	* Compact threads
	*/
	function fixCompactThreads(forceResize)
	{
		function resetThreads($this)
		{
			$this.removeClass('compact').children('.compact-thread').removeClass('compact-thread firstColumn lastColumn firstRow lastRow').css('width', '').find('.main').css('min-height', '');
			$this.prev('.sectionHeaders').removeClass('compact');
			$this.next('.sectionFooter').removeClass('compact');
		}

		function addCompact($this)
		{
			$this.addClass('compact');
			$this.prev('.sectionHeaders').addClass('compact')
			$this.next('.sectionFooter').addClass('compact')
		}

		if (!compactThreads) return;

		var windowWidth = $w.width();
		if (!forceResize && windowWidth == compactThreadsLastWidth) return;
		compactThreadsLastWidth = windowWidth;

		$('.discussionListItems.auto-compact').each(function() {
			var $this = $(this),
				data = $this.attr('data-compact-width'),
				totalWidth = $this.width(),
				compactThreadsMaxColumns = $this.attr('data-threads-max-columns'),
				compactThreadsBreakPoint = $this.attr('data-threads-breakpoint'),
				columns = 1,
				threads,
				threadsCount,
				lastRow = 0,
				lastRowColumns = 1,
				rowWidth,
				lastThreadColumns;
			
			// Check if category width was changed
			if (!forceResize && totalWidth == data) return;
			$this.attr('data-compact-width', totalWidth);

			// Check if width is enough to fit 2 threads on same row and if thread is in compact mode
			columns = Math.min(Math.floor(totalWidth / compactThreadsBreakPoint), compactThreadsMaxColumns);
			if (!forceResize && columns < 2 && !$this.hasClass('compact')) return;

			// Remove compact mode
			if (columns < 2)
			{
				resetThreads($this);
				return;
			}

			// Get child threads
			threads = $this.children('.discussionListItem');
			threadsCount = threads.length;

			if (threadsCount < 2)
			{
				resetThreads($this);
				return;
			}

			// Change columns count for special sotuations
			if (threadsCount < columns)
			{
				// Fewer threads than columns - split threads evenly
				columns = threadsCount;
			} 
			else if (columns < threadsCount && (columns * 2) >= threadsCount && (Math.floor(threadsCount / 2) * 2) == threadsCount)
			{
				// Even number of columns. Too few for one row, too many for 2 rows - split it into 2 equal rows
				columns = threadsCount / 2;
			}

			// Calculate stuff
			resetThreads($this);
			addCompact($this);
			lastRow = Math.floor((threadsCount - 1) / columns);
			lastRowColumns = threadsCount - lastRow * columns;
			rowWidth = Math.floor(1000 / columns) / 10 + '%';
			lastThreadColumns = columns - lastRowColumns + 1;

			// Align child threads
			var lastCheckedRow = 2,
				lastItem = false,
				columnItems = [],
				maxHeight = 0;
			threads.each(function(i) {
				var row = Math.floor(i / columns),
					currentColumns = (row == lastRow) ? lastRowColumns : columns,
					thread = $(this),
					j;

				if (row == 0)
				{
					thread.addClass('firstRow');
				}
				if (row == lastRow)
				{
					thread.addClass('lastRow');
				}

				if (currentColumns != columns && i == threadsCount - 1)
				{
					thread.addClass('compact-thread').css('width', Math.floor(1000 / columns) * lastThreadColumns / 10 + '%');
				}
				else
				{
					thread.addClass('compact-thread').css('width', rowWidth);
				}
				if (lastCheckedRow != row)
				{
					lastCheckedRow = row;
					if (lastItem !== false)
					{
						lastItem.addClass('lastColumn');
					}
					thread.addClass('firstColumn');
					if (i == threadsCount - 1)
					{
						thread.removeClass('compact-thread');
						lastItem = thread;
						return;
					}
				}
				lastItem = thread;

				// Check height
				var mainBlock = thread.find('.main:first'),
					innerBlock = thread.children('.innerBlock');

				if (mainBlock.length && innerBlock.length)
				{
					columnItems.push({
						item: mainBlock,
						diff: innerBlock.height() - mainBlock.height()
					});
					maxHeight = Math.max(maxHeight, innerBlock.height());

					if (i == (threadsCount - 1) || (columns * row + columns - 1) == i)
					{
						if (columnItems.length > 1)
						{
							for (j=0; j<columnItems.length; j++)
							{
								columnItems[j].item.css('min-height', (maxHeight - columnItems[j].diff) + 'px');
							}
						}
						columnItems = [];
						maxHeight = 0;
					}
				}
				else if (maxHeight)
				{
					columnItems = $();
					maxHeight = 0;
				}
			});
			if (lastItem !== false)
			{
				lastItem.addClass('lastColumn');
			}
		});
	}

	/*
	* Static navigation
	*/
	function checkStaticNav(checkHash)
	{
		var windowTop = 0,
			c = staticNavigationConfig,
			windowWidth = Math.floor($w.width());

		// Check window dimensions and position
		function checkWindow()
		{
			if (windowWidth < c.minWidth || $w.height() < c.minHeight) return false;
			if (!c.isStatic)
			{
				c.navHeight = c.item.height();
				if (c.isInHeader)
				{
					c.minTopPosition = c.header.height() - c.navHeight;
				}
				else
				{
					c.minTopPosition = c.header.height();
				}

				if (c.wrapper.length > 0)
				{
					c.minTopPosition += parseInt(c.wrapper.css('margin-top')) + parseInt(c.wrapper.css('padding-top')) + parseInt(c.wrapper.css('border-top-width'));
				}

				if (c.beforeHeader === false)
				{
					c.beforeHeader = c.headerMover.prevAll();
				}
				if (c.beforeHeader.length > 0)
				{
					c.beforeHeader.each(function() {
						c.minTopPosition += $(this).outerHeight();
					});
				}
			}
			windowTop = $w.scrollTop();
			return (windowTop > c.minTopPosition);
		}

		// Remove static mode
		function removeStatic()
		{
			c.isStatic = false;
			c.item.removeClass('static');
			if (c.item.hasClass('staticPageWidth'))
			{
				c.item.addClass('pageWidth');
			}
			if (altNav)
			{
				c.item.addClass('altNav');
				altNavActive = true;
			}
			c.header.css('min-height', '');
			c.minHeightItem.hide();
			XenForo.forceUpdateNavigationTabs();
		}

		// Set static mode
		function setStatic()
		{
			c.isStatic = true;
			c.item.addClass('static');
			if (c.item.hasClass('pageWidth'))
			{
				c.item.removeClass('pageWidth');
				if (!c.item.hasClass('staticPageWidth'))
				{
					c.item.children().addClass('pageWidth');
					c.item.addClass('staticPageWidth');
				}
			}
			if (altNav)
			{
				c.item.removeClass('altNav');
				altNavActive = false;
			}
			c.minHeightItem.css({
				display: 'block',
				height: c.navHeight + 'px'
			});
			XenForo.forceUpdateNavigationTabs();
		}

		// Check if static mode should be toggled
		if (!checkWindow())
		{
			// Do not use static layout
			if (c.isStatic)
			{
				removeStatic();
			}
			return;
		}
		if (!c.isStatic)
		{
			setStatic();
		}

		// Check hash
		if (checkHash)
		{
			var hash = (window.location.hash) ? window.location.hash : '';
			if (!hash)
			{
				return;
			}
			window.scrollTo($w.scrollLeft(), $w.scrollTop() - c.item.height() - 10);
		}
	}

	// Function to get list of glyphs
	function getGlyphConfig(data)
	{
		var result = {
				_total: 0,
			},
			list = data ? data.split("\n") : [],
			item, pair, i, j, keys, key, value, icon;

		for (i=0; i<list.length; i++)
		{
			// Split to key=value pair
			pair = list[i].split('=');
			if (pair.length > 1)
			{
				item = {
					key: pair[0].trim(),
					value: '',
					className: '',
					style: '',
				};
				pair.shift();
				item.value = pair.join('=');

				value = item.value.split(',');
				if (value[0].length > 3)
				{
					key = value[0].substr(0, 3);
					icon = value[0].substr(3);
					switch (key)
					{
						case 'fa-':
							item.className = 'fa fa-' + icon;
							break;
						case 'gi-':
							item.className = 'glyphicons glyphicons-' + icon;
							break;
					}
				}

				if (item.className != '')
				{
					// Custom style
					if (value.length > 1 && value[1] != '')
					{
						item.style += value[1];
					}
					// Add to list
					keys = item.key.split(',');
					for (j=0; j<keys.length; j++)
					{
						if (typeof result[keys[j]] == 'undefined')
						{
							result._total ++;
						}
						result[keys[j]] = item;
					}
				}
			}
		}
		return result;
	}

	/*
	* Align footer to bottom of page
	*/
	function checkFooter()
	{
		var windowHeight = $w.height(),
			contentHeight = 0,
			footerHeight = stickyFooter.outerHeight(true),
			total;

		stickyFooterContent.css('min-height', '');
		stickyFooterBlocks.each(function() {
			contentHeight += $(this).outerHeight(true);
		});

		total = contentHeight + footerHeight;

		if (total < windowHeight)
		{
			stickyFooterContent.css('min-height', Math.floor(stickyFooterContent.height() + windowHeight - total) + 'px');
		}
	}

	/*
	* Expand content below sidebar
	*/
	function doExpandContent(force)
	{
		if (!expandContent.expand) return;

		var windowWidth = $w.width(),
			c = expandContent,
			sidebarHeight, contentWidth, height, expand, children;

		function remove()
		{
			c.content.removeClass('expanded');
			c.expanded = false;
		}

		// Check window width
		if (!force && windowWidth == c.lastWindowWidth) return;

		c.lastWindowWidth = windowWidth;
		if (windowWidth < c.maxWidth || c.collapsed)
		{
			if (c.expanded)
			{
				remove();
			}
			return;
		}

		// Check sidebar height
		sidebarHeight = c.sidebar.height();
		contentWidth = c.content.width();

		if (!force && sidebarHeight == c.lastHeight && contentWidth == c.lastWidth) return;
		c.lastHeight = sidebarHeight;
		c.lastWidth = contentWidth;

		remove();
		if (!sidebarHeight || !contentWidth || c.content.height() < sidebarHeight) return;

		// Mark as expanded
		children = c.content.children().removeClass('expandItemWidth expandChildrenWidth');

		// Check items
		height = -10;
		expand = false;
		children.each(function() {
			var $this = $(this),
				itemHeight;

			if (expand)
			{
				$this.addClass('expandedParent');
				return;
			}

			if ($this.hasClass('messageList') || $this.hasClass('nodeList') || $this.hasClass('sectionMain'))
			{
				// Check children items
				$this.children().removeClass('expandWidth').each(function() {
					var $c = $(this);

					itemHeight = $c.height();

					if (itemHeight)
					{
						height += itemHeight;
						$c.attr('data-debug-height', height);
						if (height > sidebarHeight)
						{
							expand = true;
							$this.addClass('expandChildrenWidth');
							$c.nextAll().addClass('expandWidth');
							return false;
						}
					}
				});
			}
			else
			{
				itemHeight = $this.height();
				if (!itemHeight) return;

				height += itemHeight;
				if (height > sidebarHeight)
				{
					expand = true;
				}
			}

			if (expand)
			{
				c.expanded = true;
				c.content.addClass('expanded');
				$this.nextAll().addClass('expandItemWidth');
				return false;
			}
		});
	}

	/*
	* Scale user profiles
	*/
	function scaleUserProfiles()
	{
		$('.message .messageUserInfo .messageUserBlock').each(function() {
			var userBlock = $(this), 
				info = userBlock.parents('.messageUserInfo'),
				next = info.next('.messageInfo'),
				content;

			if (!next.length) return;

			if (scaleUserProfile.profile)
			{
				userBlock.css('min-height', '');
			}
			if (scaleUserProfile.message)
			{
				content = next.find('.messageContent:first');
				if (!content.length) return;
				content.css('min-height', '');
			}

			var message = info.parents('.message:first'),
				height, diff;

			if (message.width() <= (userBlock.width() * 2)) return;

			// Scale user profile
			if (scaleUserProfile.profile)
			{
				height = Math.floor(message.height());
				diff = Math.ceil(info.height() - userBlock.height());
				userBlock.css('min-height', (height - diff) + 'px');
			}

			// Scale message
			if (scaleUserProfile.message)
			{
				height = userBlock.outerHeight(false);
				diff = next.outerHeight(false) - content.outerHeight(false);
				content.css('min-height', Math.floor(height - diff) + 'px');
			}
		});
	}

	/*
	* Do style stuff when DOM is ready
	*/
	$doc.ready(function() 
	{
		// Check display
		(function()
		{
			var userAgent = (navigator) ? navigator.userAgent : '',
				test = document.createElement('div'),
				transforms = ['transform', 'webkitTransform', 'msTransform'];

			oldIE = (window.attachEvent && !window.addEventListener);
			isHD = window.matchMedia ? window.matchMedia('(-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi), (min-resolution: 1.5dppx)').matches : false;
			isRTL = ($('html').attr('dir') == 'RTL');
			iOS = (userAgent.indexOf('like Mac OS X') > 0);

			for (var i=0; i<transforms.length; i++)
			{
				if (typeof(test.style[transforms[i]]) != 'undefined')
				{
					canTransform = true;
					$('html').addClass('canTransform');
					break;
				}
			}

			delete test;
		}) ();

		/*
		* Add .overlayExposeMask to mask when .overlay() function is triggered.
		* This function is triggered every time new popup is shown. It is not triggered
		* when cached popups are shown, but .xfSlideOut below takes care of that.
		*/
		$.fn._jQueryToolsOverlayBackup = $.fn.overlay;
		$.fn.overlay = function(options)
		{
			var result = this._jQueryToolsOverlayBackup(options);
			setTimeout(function() { $('#exposeMask').addClass('overlayExposeMask'); }, 1);
			return result; 
		};

		/*
		* Remove .overlayExposeMask when .xfSlideIn() is triggered.
		* This function is triggered only by login button.
		*/
		$.fn._xfSlideInBackup = $.fn.xfSlideIn;
		$.fn.xfSlideIn = function(duration, easing, callback)
		{
			$('#exposeMask').removeClass('overlayExposeMask');
			return this._xfSlideInBackup(duration, easing, callback);
		};

		/*
		* Add .overlayExposeMask to mask after animation is over.
		* This is redundancy function that does the same as .overlay() above,
		* in case if popup is triggered without calling .overlay(). That happens
		* to cached member card popups.
		*/
		$.fn._xfSlideOutBackup = $.fn.xfSlideOut;
		$.fn.xfSlideOut = function(duration, easing, callback)
		{
			setTimeout(function() { $('#exposeMask').addClass('overlayExposeMask'); }, duration + 100);
			return this._xfSlideOutBackup(duration, easing, callback);
		};

		// Fix layout
		$('.pageContent > .xenForm, .pageContent > .xengalleryContainer > .insideContainer > .xenForm, .pageContent > .errorOverlay').not('#login').wrap('<div class="primaryContent" />');
		$('.discussionListItems, .showcaseList').parent(':not(.discussionListWrapper)').wrapInner('<div class="discussionListWrapper" />');
		$('.discussionListWrapper > h3').each(function() {
			$(this).parent().before($(this));
		});
		$($('.discussionListWrapper > .pageNavLinkGroup, .discussionListWrapper > .sectionFooter').get().reverse()).each(function() {
			$(this).parent().after($(this));
		});
		$('div.sidebar .tabs li a').each(function() {
			this.setAttribute('title', $(this).text().trim());
		});

		// Adjust external forums list
		(function() {
			var list = $('.subForumList.outside');
			if (!list.length) return;

			// Remove shadows from previous item
			list.prev().css('box-shadow', 'none');
			list.parents('.category.level_1').css('box-shadow', 'none');
		})();

		// Custom node icons
		$('.nodeList[data-custom-icons]').each(function() {
			if (oldIE) return;

			function checkIcons(params)
			{
				if (params.length < 2) return;

				var nodesList, nodeIDs, nodeIcons, style;

				// Find all node ids
				nodeIDs = findAllIDs(params[0], '.node_');

				// Find nodes
				if (nodeIDs.length < 1) return;

				nodeIcons = $(nodeIDs.join(', ')).find('.nodeIcon');
				if (nodeIcons.length < 1) return;

				// Check background parameters
				style = {
					'background-image': 'url("' + params[1].trim() + '")'
				};
				if (isHD && params.length > 2 && params[2] != '')
				{
					style['background-image'] = 'url("' + params[2].trim() + '")';
				}

				// Apply style
				nodeIcons.not('.customized').addClass('customized').css(style);
			}

			var icons = $(this).attr('data-custom-icons').split("\n");
			
			for (var i=0; i<icons.length; i++)
			{
				checkIcons(icons[i].trim().split('|'));
			}
		});

		// Custom node glyphs
		$('.nodeList[data-custom-glyphs]').each(function() {
			if (oldIE) return;

			var icons = $(this).attr('data-custom-glyphs').split("\n"),
				colors = $(this).attr('data-custom-glyph-colors').split(';');
			
			function checkIcons(params)
			{
				if (params.length < 2) return;

				var i, nodesList, nodeIDs, nodeGlyphs, html, style, customColors;

				// Generate HTML code and check for glyph classes
				html = '<i class="';
				if (/^fa\-[a-z\-]+$/.test(params[1]))
				{
					html += 'fa ' + params[1];
				}
				else if (/^gi\-[a-z\-_]+$/.test(params[1]))
				{
					html += 'glyphicons glyphicons-' + params[1].substr(3);
				}
				else
				{
					return;
				}

				// Custom style
				style = (params.length > 2) ? params[2] : '';
				html += '" style="' + style + '"></i>';

				// Find all node ids
				nodeIDs = findAllIDs(params[0], '.node_');

				// Find nodes
				if (nodeIDs.length < 1) return;

				nodeIcons = $(nodeIDs.join(', ')).find('.nodeIcon');
				if (nodeIcons.length < 1) return;

				// Custom colors
				customColors = [];
				for (i=0; i<4; i++)
				{
					customColors[i] = (params.length > (i + 3) && params[i + 3] != '') ? params[i + 3] : colors[i];
				}

				// Apply style
				nodeIcons.not('.customized').each(function() {
					var node = $(this).parents('.node:first'),
						index = 0;

					if (node.hasClass('link')) index = 3;
					else if (node.hasClass('page')) index = 2;
					else if (node.children('.nodeInfo.unread').length) index = 1;

					if (customColors[index] == 'none') return;

					$(this).css({
						color: customColors[index],
						'background-image': 'none'
					}).html(html).addClass('customized');
				});
			}

			if (colors.length < 4) return;

			for (var i=0; i<icons.length; i++)
			{
				checkIcons(icons[i].trim().split('|'));
			}
		});

		// Custom category glyphs
		$('.nodeList[data-category-glyphs]').each(function() {
			if (oldIE) return;

			var icons = $(this).attr('data-category-glyphs').split("\n");

			function checkIcons(params)
			{
				var i, nodesList, nodeIDs, extraParams, icon, style, html, titles;

				if (params.length != 2) return;
				extraParams = params[1].split(',', 2);

				// Generate HTML code and check for glyph classes
				html = '<i class="';
				if (/^fa\-[a-z\-]+$/.test(extraParams[0]))
				{
					html += 'fa ' + extraParams[0];
				}
				else if (/^gi\-[a-z\-_]+$/.test(extraParams[0]))
				{
					html += 'glyphicons glyphicons-' + extraParams[0].substr(3);
				}
				else
				{
					return;
				}

				// Custom style
				style = (extraParams.length > 1) ? extraParams[1] : '';
				html += '" style="' + style + '"></i>';

				// Find all node ids
				nodeIDs = findAllIDs(params[0], '.category.level_1.node_');

				// Find nodes
				if (nodeIDs.length < 1) return;

				// Find titles
				titles = $(nodeIDs.join(', ')).find('.categoryStrip .nodeTitle a').not('.has-category-glyph');
				if (titles.length < 1) return;

				// Set glyphs
				titles.addClass('has-category-glyph').before(html);
			}

			for (var i=0; i<icons.length; i++)
			{
				checkIcons(icons[i].trim().split('=', 2));
			}
		});

		// Fix nodes list
		$('.nodeList:not(.nodeList .nodeList)').each(function() {
			var nodeList = $(this);

			// Combine nodes into big list
			var children = nodeList.children(),
				total = children.length,
				joinList = $([]);

			function wrapList() {
				if (!joinList.length)
				{
					return;
				}
				joinList.filter(':first').addClass('first-node-without-title');
				joinList.wrapAll('<li class="node category level_1 nodeNoTitle nodeNoID"><ol class="nodeList" /></li>');
				joinList.removeClass('level_1').removeClass('groupNoChildren').addClass('level_2').find('.categoryStrip').remove();
				joinList.parents('ol.nodeList:first').before('<div class="nodeInfo categoryNodeInfo categoryStrip empty"></div>');
				joinList = $([]);
			};

			children.each(function(i) {
				var node = $(this);
				if (node.hasClass('groupNoChildren') || node.hasClass('level_2'))
				{
					joinList = joinList.add(node);
				}
				else
				{
					wrapList();
				}
			});
			wrapList();
		});

		// Check for compact nodes
		$('.nodeList[data-compact]').each(function() {
			if (oldIE) return;

			var $this = $(this),
				data = $this.attr('data-compact'),
				list;

			if (!data || !data.length) return;

			// Split data. Format: breakpoint|list_of_categories
			list = data.split('|');
			compactNodesBreakPoint = parseInt(list[0]);
			if (isNaN(compactNodesBreakPoint) || !compactNodesBreakPoint)
			{
				compactNodes = false;
				return;
			}

			compactNodesMaxColumns = (list.length > 1) ? list[1] : 999;
			if (isNaN(compactNodesMaxColumns) || !compactNodesMaxColumns)
			{
				compactNodesMaxColumns = 999;
			}

			if (list.length > 2 && list[2] == '')
			{
				// All nodes
				$this.find('.node .nodeList .node.level_2').parents('.node').each(function() {
					var $this = $(this),
						children = $this.find('.node');
					if (children.length > 1)
					{
						$this.addClass('auto-compact').attr('data-compact-width', 0);
						compactNodes = true;
					}
				});
			}
			else
			{
				list = list[2].split(',');
				for (var i=0; i<list.length; i++)
				{
					$this.find('.node.node_' + list[i] + ' .nodeList .node.level_2').parents('.node').each(function() {
						var $this = $(this),
							children = $this.find('.node');
						if (children.length > 1)
						{
							$this.addClass('auto-compact').attr('data-compact-width', 0);
							compactNodes = true;
						}
					});
				}
			}
		});
		if (compactNodes) 
		{
			fixCompactNodes(false);
		}

		// Expand/collapse categories
		$('.nodeList[data-expand]').each(function() {
			if (oldIE) return;

			var $this = $(this),
				data = $this.attr('data-expand'),
				list, categories, config, search, hidden;

			if (!data || !data.length) return;

			// Check configuration
			config = data.split('|');
			if (config.length < 3 || config[0] != '1') return;

			// Parse config and find categories
			if (config[1].length > 0)
			{
				list = config[1].split(',');
				nodeIDs = findAllIDs(config[1], '.category.node_');
				if (nodeIDs.length < 1) return;

				categories = $this.find(nodeIDs.join(', ')).children('.categoryNodeInfo.categoryStrip').not('.empty');
			}
			else
			{
				categories = $this.find('.category > .categoryNodeInfo.categoryStrip').not('.empty');
			}
			if (!categories.length) return;

			// Hide categories by default
			if (config.length > 2 && config[2].length > 0)
			{
				hidden = findAllIDs(config[2], '.category.node_');
				$this.find(hidden.join(', ')).attr('data-default-collapsed', '1');
			}

			categories.each(function() {
				var $this = $(this),
					category = $this.parent('.category'),
					collapsed = false,
					text, id, link, nodes;

				if (!category.length) return;
				id = getID(category.attr('class'), 'node_');

				if (category.attr('data-default-collapsed') == '1')
				{
					collapsed = true;
				}

				text = $this.find('.categoryText .nodeTitle');
				if (!text.length) return;

				nodes = $this.next('ol.nodeList');
				if (!nodes.length) return;

				text.before('<a class="categoryToggle" href="#"></a>');
				link = $this.find('.categoryToggle');

				createToggle(link, nodes, 'node-' + id, collapsed, category);
			});
		});

		// Custom thread icons
		$('.discussionListItems[data-custom-icons]').each(function() {
			if (oldIE) return;

			var icons = $(this).attr('data-custom-icons').split("\n"),
				list, i, row, ids, threads, url;

			for (i=0; i<icons.length; i++)
			{
				// Check configuration row
				// Format: ids;normal_image;hd_image
				row = icons[i].trim();
				if (!row.length) continue;

				list = row.split(';');
				if (list.length < 2) continue;

				// Get all ids from first parameters
				ids = findAllIDs(list[0], '#thread-');
				if (!ids.length) continue;

				// Find all threads
				threads = $(ids.join(', ')).not('.has-custom-icon');
				if (!threads.length) continue;

				// Assign images
				threads.each(function() {
					url = (isHD && list.length > 2 && list[2] != '') ? list[2] : list[1];
					threads.addClass('has-custom-icon').find('.posterAvatar > .avatarContainer').html('<a class="avatar" data-avatarhtml="true"><img src="' + url + '" width="48" height="48" alt="" /></a>');
				});
			}
		});

		// Remove empty thread lists
		$('.discussionListItems').each(function() {
			var $this = $(this);

			if ($this.children().length) return;
			$this.remove();
		});

		// Remove spacing for threads list without unread threads
		$('.discussionListItems').each(function() {
			if (!$(this).find('.unreadLink, .ReadToggle').length)
			{
				$(this).addClass('noUnreadLinks');
			}
		});

		// Add wrapper to threads and showcase items
		$('.discussionListItem, .showcaseListItem').not('.withInnerBlock').addClass('withInnerBlock').wrapInner('<div class="innerBlock" />');

		// Separate sticky threads
		$('.discussionListItems[data-threads-split]').each(function() {
			var $this = $(this),
				parent = $this.parent(),
				data = $this.attr('data-threads-split'),
				threads, stickies, normal, copy, prev;

			// Check config
			if (!parseInt(data))
			{
				return;
			}

			// Find all threads, check if we have sticky/normal threads
			threads = $this.children();

			stickies = threads.filter('.discussionListItem.sticky');
			if (!stickies.length)
			{
				$this.addClass('normal');
				return;
			}

			// Move normal threads to separate block
			$this.addClass('sticky');
			prev = $this.prev().filter('.sectionHeaders');
			prev.addClass('sticky');
			normal = threads.filter('.discussionListItem:not(.sticky)');

			if (!normal.length) return;

			// Create item after current item
			if (parent.hasClass('discussionListWrapper'))
			{
				parent.addClass('sticky').after('<div class="discussionListWrapper"><ol /></div>');
				copy = parent.next().children('ol');

				// Copy footer
				parent.next().append($this.nextAll());
			}
			else
			{
				$this.after('<ol />');
				copy = $this.next();
			}

			// Copy all attributes
			$.each(this.attributes, function() {
				if (this.specified)
				{
					copy.attr(this.name, this.value);
				}
			});
			copy.removeClass('sticky').addClass('split normal');

			// Copy items after last thread, copy normal threads
			copy.append(normal.nextAll());
			copy.prepend(normal);

			// Copy headers
			if (prev.length)
			{
				copy.before(prev.clone().removeClass('sticky'));
			}
		});

		// Compact threads list
		$('.discussionListItems[data-threads-compact]').each(function() {
			if (oldIE) return;

			var $this = $(this),
				data = $this.attr('data-threads-compact'),
				config = data.split('|'),
				nodeID = $this.attr('data-node-id'),
				nodesList, threads, compactThreadsBreakPoint, compactThreadsMaxColumns;

			// Check config
			if (config.length < 6) return;
			if ($this.hasClass('sticky'))
			{
				if (config[4] != '1') return;
				nodesList = config[5];
			}
			else
			{
				if (config[0] != '1') return;
				nodesList = config[1];
			}
			compactThreadsBreakPoint = parseInt(config[2]);
			compactThreadsMaxColumns = parseInt(config[3]);

			if (isNaN(compactThreadsBreakPoint) || !compactThreadsBreakPoint)
			{
				compactThreads = false;
				return;
			}

			if (isNaN(compactThreadsMaxColumns) || !compactThreadsMaxColumns)
			{
				compactThreadsMaxColumns = 999;
			}

			// Check if node id is in allowed nodes list
			if (nodeID && nodesList.length > 0)
			{
				nodesList = '-,' + nodesList + ',';
				if (nodesList.indexOf(',' + nodeID + ',') < 1)
				{
					return;
				}
			}

			// Set config
			$this.attr('data-threads-breakpoint', compactThreadsBreakPoint);
			$this.attr('data-threads-max-columns', compactThreadsMaxColumns);

			// Find all threads
			threads = $this.children();

			// Check if there is more than one thread or non-threads are present
			if (threads.length < 2 || threads.not('.discussionListItem').length > 0) return;

			// We are good to go
			$this.addClass('auto-compact').data('data-compact-width', 0);
			compactThreads = true;
		});
		if (compactThreads) 
		{
			// Override inline editor
			XenForo.DiscussionListItemEditor.prototype.saveSuccessOld = XenForo.DiscussionListItemEditor.prototype.saveSuccess;
			XenForo.DiscussionListItemEditor.prototype.saveSuccess = function(ajaxData, textStatus) {
				XenForo.DiscussionListItemEditor.prototype.saveSuccessOld.apply(this, arguments);
				$('#thread-' + ajaxData.threadId).stop(true, true);
				fixCompactThreads(true);
			};
			fixCompactThreads(false);
		}

		// Static and alternative navigation
		$('#navigation .pageContent > nav').eq(0).each(function() {
			if (oldIE) return;

			var primaryNavLinks = $(this).addClass('primaryNavLinks withSecondaryLinks');

			// Static navigation
			$('#navigation[data-static-navigation]:first').each(function() {
				var $this = $(this),
					data = $this.attr('data-static-navigation').split(',');

				if (!data.length || data[0] != '1') return;

				// Check if secondary navigation row exists
				if (!$('.navTabs .navTab.selected .tabLinks').children().length) return;

				// Set configuration
				staticNavigationConfig = {
					header: $('#header'),
					headerMover: $('#headerMover'),
					wrapper: $this.parents('.pageWidth:last'),
					isStatic: false,
					isInHeader: ($this.parents('#header').length > 0),
					showSecondRow: (data.length > 1 && data[1] == '1'),
					secondRowHover: (data.length > 2 && data[2] == '1'),
					minWidth: (data.length > 3 && data[3] != '') ? parseInt(data[3]) : 0,
					minHeight: (data.length > 4 && data[4] != '') ? parseInt(data[4]) : 0,
					item: $this,
					navHeight: 0,
					minTopPosition: 0,
					lastWindowWidth: 0,
					maxWidth: false,
					beforeHeader: false
				};

				var c = staticNavigationConfig;

				if (!c.header.length || !c.headerMover.length) return;
				staticNavigation = true;

				$this.before('<div class="staticNavDummy" style="display:none;" />');
				$this.wrapInner('<div class="staticContent" />');
				c.minHeightItem = $this.prev();

				if (isNaN(c.minWidth) || c.minWidth < 400) c.minWidth = 400;
				if (isNaN(c.minHeight) || c.minHeight < 300) c.minHeight = 300;
			});

			// Alternative navigation
			$('#navigation[data-alternative-nav]').each(function() {
				var nav = $(this),
					mainLinks = nav.find('.navTabs .publicTabs'),
					userLinks = nav.find('.navTabs .visitorTabs'),
					selectedTabs = $('.navTabs .navTab.selected .tabLinks'),
					logo = $('#header #logo'),
					data, changes, key,
					altTabs, altTabsContainer, altTabsParent,
					mainTabs,
					searchBar;

				if (!mainLinks.length || !selectedTabs.length || !logo.length) return;

				// Copy secondary links
				$('#navigation').addClass('split').find('.pageContent:first').append('<nav class="secondaryNavLinks"><div class="navTabs"><div class="navTab selected" /></div></nav>');
				$('#navigation .secondaryNavLinks .navTab').append(selectedTabs);

				// Copy user tabs
				if (userLinks.length)
				{
					mainTabs = nav.find('.secondaryNavLinks .navTabs').addClass('mainTabs');
					mainTabs.parent().addClass('withUserTabs');
					mainTabs.after('<div class="navTabs userTabs" />').next().append(userLinks.clone(true));
				}

				// Copy main tabs
				logo.after('<div class="altTabs" />');
				altTabs = logo.next();
				altTabsContainer = altTabs.append(mainLinks.clone(true)).children().attr('class', 'altTabsContainer');
				altTabsParent = altTabs.parent();

				// Move search bar
				searchBar = nav.next('#searchBar');
				if (searchBar.length)
				{
					nav.find('.secondaryNavLinks').after(searchBar);
				}

				// Remove hidden tab
				altTabsContainer.children('.navigationHiddenTabs').remove();

				// Enable
				$('#navigation, #header').addClass('altNav');
				altNav = true;
				altNavActive = true;
				primaryNavLinks.addClass('withoutSecondaryLinks').removeClass('withSecondaryLinks');
			});
		});

		// Add no-icon to each tab
		$('.navTab:not(.has-icon)').addClass('no-icon');

		// Assign navigation glyphs
		$('#navigation').each(function() {
			if (oldIE) return;

			// Function to parse glyph config
			function parseGlyphConfig(config)
			{
				function parseValue(value)
				{
					var result = parseInt(value);
					return (isNaN(result) || result < 1 || result > 2) ? 0 : result;
				}

				var result = {_default: 0},
					list = config.split(','), 
					i, pair;

				for (i=0; i<list.length; i++)
				{
					pair = list[i].split('=');
					if (pair.length == 1 && i == 0)
					{
						result._default = parseValue(list[i]);
					}
					else if(pair.length == 2)
					{
						result[pair[0]] = parseValue(pair[1]);
					}
				}

				return result;
			}

			// Function to add icons to tabs
			function parseTabs(items, icons, config)
			{
				// Set configuration
				var defaultConfig = config._default;
				for (var key in config)
				{
					if (key == '_default') continue;

					items.filter('.' + key).not('.has-icon-config').addClass('has-icon-config').attr('data-icon-config', config[key]);
				}

				// Set icons
				for (var key in icons)
				{
					if (key == '_total') continue;

					// Find tab with matching class name					
					items.filter('.' + key).not('.has-icon').each(function() {
						var $this = $(this),
							config = $this.hasClass('has-icon-config') ? parseInt($this.attr('data-icon-config')) : defaultConfig;

						if (!config) return;

						// Find tab text
						var link = $this.addClass('has-icon').removeClass('no-icon').find('.navLink:first');
						link.children('strong:not(.itemCount):first').each(function() {
							link = $(this);
						});

						// Add icon
						link.wrapInner('<span class="icon-text" />');
						var text = link.children('.icon-text:first');
						text.before('<span class="icon ' + icons[key].className + '" style="' + icons[key].style + '" />');
						text.after(text.children());
						link.find('.icon').attr('title', link.find('.icon-text').text().trim());

						// Add class
						$this.addClass((config == 2) ? 'icon-hide-text' : 'icon-show-text');
					});
				}
			}

			var nav = $(this), 
				config, icons;

			// Add non-zero-counter/zero-counter classes to tabs with counters
			$('.navLink > .itemCount').each(function() {
				$(this).parents('.navTab').addClass($(this).hasClass('.Zero') ? 'zero-counter' : 'non-zero-counter');
			});

			// Parse main tabs
			config = nav.attr('data-glyphs-main-config');
			icons = config != '' ? getGlyphConfig(nav.attr('data-glyphs-main')) : {_total: 0};
			if (icons._total > 0)
			{
				parseTabs($('#navigation .publicTabs').children(), icons, parseGlyphConfig(config));
			}

			// Parse visitor tabs
			config = nav.attr('data-glyphs-visitor-config');
			icons = config != '' ? getGlyphConfig(nav.attr('data-glyphs-visitor')) : {_total: 0};
			if (icons._total > 0)
			{
				parseTabs($('.visitorTabs').children(), icons, parseGlyphConfig(config));
			}

			// Parse alternative tabs
			if (altNav)
			{
				icons = nav.attr('data-alt-glyphs') + '\n' + nav.attr('data-glyphs-main');
				config = '1';
				icons = getGlyphConfig(icons);
				if (icons._total > 0)
				{
					parseTabs($('.altTabs .altTabsContainer').children(), icons, parseGlyphConfig('1'));
				}
			}
		});

		// Quick search icon
		$('#QuickSearch #QuickSearchQuery').each(function() {
			var $this = $(this),
				parent = $this.parents('#QuickSearch');

			parent.toggleClass('hasValue', (this.value != ''));

			$this.on('change keyup', function() {
				parent.toggleClass('hasValue', (this.value != ''));
			});
		});

		// Call to action glyphs
		$('#content[data-button-glyphs]:first').each(function() {
			if (oldIE) return;

			var items = $('a.callToAction[href]');
			if (!items.length) return;

			items.each(function() {
				var $this = $(this),
					list = $this.attr('href').split('/'),
					lastItem;

				if (list[list.length - 1] == '')
				{
					list.pop();
				}
				lastItem = list[list.length - 1].replace(/&amp;|[&\?]|=\d?/g, ' ');

				$this.addClass(lastItem.toLowerCase());
			});

			var icons = getGlyphConfig($(this).attr('data-button-glyphs'));
			for (var key in icons)
			{
				if (key == '_total') continue;

				// Find tab with matching class name					
				items.filter('.' + key).not('.has-icon').each(function() {
					$(this).addClass('has-icon').removeClass('no-icon').find('span:first').prepend('<i class="icon ' + icons[key].className + '" style="' + icons[key].style + '" />');
				});
			}

			// Add missing matching glyphs
			items.not('.has-icon').each(function() {
				var list = $(this).attr('href').split('/');
				if (list[list.length - 1] == '')
				{
					list.pop();
				}
				if (list.length < 2) return;

				var keyword = list[list.length - 2].toLowerCase();
				for (var key in icons)
				{
					if (key == '_total') continue;
					if (keyword.indexOf(key) >= 0) {
						$(this).addClass('has-icon').removeClass('no-icon').find('span:first').prepend('<i class="icon ' + icons[key].className + '" style="' + icons[key].style + '" />');
						break;
					}
				}
			});
		});

		// Flip tooltips
		$('.discussionListFilters .removeAllFilters').data('tipclass', 'flipped');

		// Footer link tooltips
		$('.footerLinks a').not('.Tooltip').each(function() {
			var $this = $(this);

			if (!$this.attr('title'))
			{
				$this.attr('title', $this.text());
			}
			$this.addClass('Tooltip').data('tipclass', 'flipped');
		});

		// Sticky footer
		$('#headerMover + footer, #headerMover > footer').children('.pageFooter[data-sticky-footer]').each(function(i) {
			if (i > 0) return;

			var $this = $(this);
			if ($this.attr('data-sticky-footer') == '1')
			{
				var footer = $this.parent(),
					parent = footer.parent();

				stickyFooterBlocks = footer.prevAll().add(parent.prevAll());
				stickyFooterContent = stickyFooterBlocks.find('#content');
				if (stickyFooterContent.length == 1)
				{
					stickyFooter = $this;
				}
			}
		});

		// Scale user profile and message
		$('#content[data-scale-message]:first').each(function() {
			var value = $(this).attr('data-scale-message');
			if (!value) return;

			var list = value.split(',');
			if (list.length < 3) return;

			var wrapped = (list[0] == '1'),
				scaleUser = (list[1] == '1'),
				scaleMessage = (list[2] == '1');

			if (!scaleUser && !scaleMessage) return;
			if (!$('.message .messageUserInfo .messageUserBlock').length) return;

			scaleUserProfile = {
				scale: true,
				profile: scaleUser,
				message: scaleMessage
			}
		});

		// Poll results
		$('.pollResult .barContainer').each(function() {
			if (!$(this).children().length)
			{
				$(this).addClass('empty');
			}
		});

		// Floating navigation
		$('#forumFooter .topLink:first').each(function() {
			if (oldIE) return;

			var data = $('#content').attr('data-floating-nav');
			if (!data || !data.length) return;

			var list = data.split(',');
			if (list.length < 2) return;

			var showTop = (list[0] == '1'),
				showBottom = (list[1] == '1');
			if (!showTop && !showBottom) return;

			var $this = $(this),
				body = $('body'),
				content = $('#content .pageWidth'),
				visible = false,
				visibleTop = false,
				visibleBottom = false,
				inputFocused = false;

			if (!content.length) return;

			body.append('<div id="floatingNavigation" />');
			var nav = $('#floatingNavigation');

			function check()
			{
				if (inputFocused)
				{
					// Hide
					if (visible)
					{
						nav.css('display', 'none');
						visible = false;
					}
					return;
				}

				var windowWidth = Math.floor($w.width()),
					windowHeight = (window.innerHeight) ? window.innerHeight : w.height(),
					contentWidth = content.width(),
					bodyHeight = body.outerHeight(true),
					scroll = $w.scrollTop();

				if (windowHeight < 350)
				{
					// Hide
					if (visible)
					{
						nav.css('display', 'none');
						visible = false;
					}
					return;
				}

				// Check for visible components
				var doShowTop = showTop && (scroll > (windowHeight / 2)),
					doShowBottom = showBottom && (scroll < (bodyHeight - windowHeight * 5 / 4));

				if (!doShowTop && !doShowBottom)
				{
					// Hide
					if (visible)
					{
						nav.css('display', 'none');
						visible = false;
					}
					return;
				}

				function checkRebuildNavigation()
				{
					if (doShowTop !== visibleTop) return true;
					if (doShowBottom !== visibleBottom) return true;
					return false;
				}

				function navigate()
				{
					var target = $(this).attr('data-target');
					navigateTo(target);
					return false;
				}

				// Re-create HTML code
				if (checkRebuildNavigation())
				{
					nav.html('');

					if (doShowTop)
					{
						nav.append('<a class="floating-top" href="javascript:void(0);" data-target="#headerMover">&uarr;</a>');
					}
					visibleTop = doShowTop;

					if (doShowBottom)
					{
						nav.append('<a class="floating-bottom" href="javascript:void(0);" data-target="#forumFooter">&darr;</a>');
					}
					visibleBottom = doShowBottom;

					nav.find('a').click(navigate);
				}

				var diff = 0;
				if (iOS && window.innerHeight && (window.innerHeight - $w.height()) > 40)
				{
					diff = 44;
				}

				// Show it
				visible = true;
				nav.css(isRTL ? 'left' : 'right', ((windowWidth - contentWidth > 30) ? Math.floor((windowWidth - contentWidth) / 2) : (windowWidth > 800 ? 15 : 5)) + 'px').css({
					top: (windowHeight - 32 - diff) + 'px',
					display: 'block'
				});
			}
			check();
			$w.on('scroll resize', check);

			$('input, iframe').focus(function() {
				inputFocused = true;
				check();
			}).blur(function() {
				inputFocused = false;
				check();
			});
		});

		// Move breadcrumb button
		$('#content[data-move-breadcrumb-button]').each(function() {
			var $this = $(this),
				buttons = $this.find('.breadBoxTop a.callToAction'),
				contentClass = $('#content').prop('class'),
				titlebar = (buttons.length) ? $this.find('.titleBar > h1') : false;

			if (!buttons.length || !titlebar.length || contentClass.indexOf('sonnb_xengallery_') != -1) return;

			titlebar.before('<div class="titleBarButtons" />').prev().append(buttons);
		});

		// Add sidebar container
		$('.sidebar').not('.mainContent .sidebar').each(function() {
			var $this = $(this),
				aside = $this.parent('aside');

			if (aside.length == 1)
			{
				aside.addClass('sidebarContainer');
				$this.addClass('sidebarWrapped');
				$('html').addClass('hasSidebarContainer');
			}
			// if ($this.hasClass('left')) { }
		});

		// Move sidebar signup button
		$('#content[data-sidebar-move-signup]').each(function() {
			var $this = $(this),
				data = $this.attr('data-sidebar-move-signup');
			if (data !== '1') return;

			var section = $(this).find('.sidebarContainer .sidebar .section.loginButton');
			if (!section.length) return;

			var parent = section.parent('.sidebar');
			if (!parent.length) return;

			parent.before('<div class="sidebar noWrapper" />');
			parent.prev().append(section);
		});

		// Add sidebar container and sidebar toggle
		$('.sidebarContainer').each(function(i) {
			try {
				var $this = $(this),
					sidebar = $this.children('.sidebar'),
					data = $('#content').attr('data-sidebar-toggle');

				if (!sidebar.length || data === false) return;

				var list = data.split(',', 5);
				if (list.length != 5) return;

				var toggleWidth = parseInt(list[3]),
					left = (list[2] == '1'),
					toggleDesktop = false,
					toggleMobile = false,
					mainContent = $('.mainContent'),
					text = list[4];

				var mobileToggle, mobileArrow,
					mobileToggleVisible = false,
					mobileToggled = false,
					desktopToggle,
					desktopToggleVisible = false,
					desktopToggled = false,
					desktopCookie = 'sidebar',

					// Add 5px buffer at each side to counter browsers inconsistency
					mobileMaxWidth = toggleWidth - 5,
					desktopMinWidth = toggleWidth + 5;
			} catch (e) {
				return;
			}

			if (!data || !toggleWidth || oldIE) return;

			function toggleMobileSidebar()
			{
				if (!mobileToggleVisible) return;
				mobileToggled = !mobileToggled;

				sidebar.css('display', mobileToggled ? '' : 'none');
				mobileToggle.toggleClass('active', mobileToggled);
				mobileArrow.toggleClass('down', !mobileToggled);
			}

			function toggleDesktopSidebar()
			{
				if (!desktopToggleVisible) return;
				desktopToggled = !desktopToggled;

				sidebar.toggleClass('sidebarToggled', desktopToggled);
				mainContent.toggleClass('sidebarToggled', desktopToggled);
				desktopToggle.toggleClass('toggled', desktopToggled);

				if (desktopToggled)
				{
					$.setCookie(desktopCookie, '1');
				}
				else
				{
					$.deleteCookie(desktopCookie);
				}

				setTimeout(function() {
					compactNodesLastWidth = 0;
					compactThreadsLastWidth = 0;
					$w.triggerHandler('resize');
					$('.mainContent').trigger('XenForoResize');
				}, 300);

			}

			if (list[0] == '1')
			{
				// Mobile sidebar
				toggleMobile = true;
				mobileToggleVisible = false;

				$this.prepend('<div class="sidebarToggle" style="display: none; clear: both;"><a class="callToAction" href="javascript:void(0);"><span></span></a></div>');

				mobileToggle = $this.children('.sidebarToggle');
				mobileArrow = mobileToggle.find('span').text(text).append('<strong class="arrow down"></strong>').find('strong:last');
			}

			if (list[1] == '1' && mainContent.length == 1)
			{
				// Desktop sidebar
				var crumbLink = $('.breadBoxTop .jumpMenuTrigger');
				if (crumbLink.length)
				{
					crumbLink.before('<a class="jumpMenuTrigger sidebarToggle' + (left ? ' leftSidebar' : '') + '" href="#"></a>');
					desktopToggle = crumbLink.prev();
					desktopToggle.attr('title', text);

					toggleDesktop = true;
					desktopToggleVisible = true;
				}
			}

			if (!toggleMobile && !toggleDesktop)
			{
				return;
			}

			function checkWidth()
			{
				var width = $(window).width();

				// Mobile
				if (toggleMobile)
				{
					if (width < mobileMaxWidth)
					{
						if (!mobileToggleVisible)
						{
							mobileToggle.css('display', '');
							sidebar.css('display', mobileToggled ? '' : 'none');
							mobileToggleVisible = true;
						}
					}
					else
					{
						if (mobileToggleVisible)
						{
							mobileToggle.css('display', 'none');
							sidebar.css('display', '');
							mobileToggleVisible = false;
						}
					}
				}

				// Desktop
				if (toggleDesktop)
				{
					if (width < desktopMinWidth)
					{
						if (desktopToggleVisible)
						{
							desktopToggle.hide();
							desktopToggleVisible = false;
						}
					}
					else
					{
						if (!desktopToggleVisible)
						{
							desktopToggle.show();
							desktopToggleVisible = true;
						}
					}
				}
			}

			checkWidth();
			$(window).resize(checkWidth);

			if (toggleMobile)
			{
				mobileToggle.find('a').click(function() {
					toggleMobileSidebar();
				});
			}

			if (toggleDesktop)
			{
				desktopToggle.click(function() {
					toggleDesktopSidebar();
					return false;
				});
			}

			// Toggle from cookie
			if (toggleDesktop && $.getCookie(desktopCookie))
			{
				toggleDesktopSidebar();
			}
		});

		// Expand content
		$('#content[data-expand-content]').each(function() {
			if (oldIE) return;

			var $this = $(this),
				data = $this.attr('data-expand-content').split(',');
			if (data.length < 2 || data[0] !== '1') return;

			// Get max width
			expandContent.maxWidth = parseInt(data[1]);
			if (isNaN(expandContent.maxWidth)) return;
			expandContent.maxWidth += 10;

			// Find sidebar and content
			var sidebar = $(this).find('.sidebarContainer .sidebar');
			if (!sidebar.length) return;

			var content = sidebar.parent().prev('.mainContainer').children('.mainContent');
			if (!content.length) return;

			var items = content.children(),
				available = false;

			items.each(function() {
				var $this = $(this);
				if ($this.hasClass('messageList') || $this.hasClass('nodeList') || $this.hasClass('sectionMain'))
				{
					available = true;
					return false;
				}
			});
			if (!available) return;

			// Set default values
			expandContent.expanded = false;
			expandContent.lastWidth = 0;
			expandContent.lastHeight = 0;
			expandContent.lastWindowWidth = 0;
			expandContent.sidebar = sidebar;
			expandContent.content = content;

			expandContent.expand = true;
		});

		// Rewrite XenForo.updateVisibleNavigationTabs and XenForo.updateVisibleNavigationLinks
		(function() {
			var header = $('#header'),
				nav = $('#navigation'),
				tabs = [],
				tabsTotal = 0;

			if (header.length != 1 || nav.length != 1) return;

			/*
				tabs element properties:
					type: tab type (string)
					container: container of tabs. children elements are tabs.
					parentContainer: parent item that contains this tabs block and its siblings. if missing, parent is used for calculating width
					maxRatio: maximum width ratio, applied to parentContainer if there are no siblings
					parent: parent item that might have margin/padding. it must be floating. false if its the same as container
					siblings: list of elements who's width should be deducted from item width
					tabs: if true, item is tabs block. if false, item is links block
					lastContainerWidth: last parent's container width
					lastMinWidth / lastMaxWidth: if new width is within this range, nothing needs to be parsed
			*/
			function baseTab(tab, type)
			{
				return {
					parent: false,
					parentContainer: false,
					maxRatio: 1,
					container: false,
					siblings: false,
					tabs: tab,
					lastContainerWidth: -1,
					lastMinWidth: -1,
					lastMaxWidth: -1,
					type: type
				};
			}

			function addTab(tab)
			{
				tab.id = 'NavigationHiddenMenu' + tabsTotal;
				tabs.push(tab);
				tabsTotal++;

				var children = tab.container.children('li:not(.navigationHidden, .navigationHiddenTabs)');
				children.first().addClass('firstVisible');
				children.last().addClass('lastVisible');
			}

			// Find user tabs
			$('.navTabs.userTabs').each(function() {
				var $this = $(this),
					item;

				// Alternative navigation - user links
				item = baseTab(true, 'alt-user');
				item.parent = $this;
				item.parentContainer = $this.parent();
				item.maxRatio = 0.6;
				item.container = $this.find('.visitorTabs:first');
				addTab(item);
			});

			// Find main navigation
			$('.navTabs').each(function() {
				var $this = $(this),
					item;

				// Alternative navigation - user links
				if ($this.hasClass('userTabs'))
				{
					return;
				}

				// Alternative navigation - secondary links
				if ($this.hasClass('mainTabs'))
				{
					item = baseTab(false, 'alt-secondary');
					item.parent = $this;
					item.parentContainer = $this.parent();
					item.siblings = $this.siblings();
					item.container = $this.find('.tabLinks > .blockLinksList');
					if (item.container.length == 1)
					{
						addTab(item);
					}
					return;
				}

				// Normal navigation
				var children = $this.children('ul');
				if (children.length > 0)
				{
					function addNormalTab(tab)
					{
						item = baseTab(true, 'normal');
						if (arguments.length > 1)
						{
							item.type = arguments[1];
						}
						item.parent = $this;
						item.container = tab;
						if (item.type == 'user')
						{
							item.maxRatio = 0.6;
						}
						else
						{
							item.siblings = tab.siblings();
						}
						if (!item.siblings.length) item.siblings = false;
						addTab(item);
					}

					// Add visitor tabs
					children.filter('.visitorTabs').each(function() {
						addNormalTab($(this), 'user');
					});

					// Add public tabs
					children.filter('.publicTabs').each(function() {
						addNormalTab($(this));
					});

					// Add other tabs ???
					children.not('.visitorTabs, .publicTabs').each(function() {
						addNormalTab($(this), 'public');
					});

					// Add secondary links
					$this.find('.navTab.selected .blockLinksList').each(function() {
						var $this = $(this);

						item = baseTab(false, 'secondary');
						item.parent = $this;
						item.container = $this;
						addTab(item);
					});

					return;
				}
			});

			// Alternative navigation main tabs
			$('.altTabs').each(function() {
				var $this = $(this),
					item;

				item = baseTab(true, 'alt');
				item.parent = $this.parent();
				item.container = $this.find('.altTabsContainer:first');
				item.siblings = $this.siblings();
				if (!item.siblings.length) item.siblings = false;
				addTab(item);
			});

			function updateTab(tab)
			{
				if (altNav)
				{
					// Do not process hidden tab blocks
					if (altNavActive && tab.type == 'normal') return;
					if (!altNavActive && tab.type == 'alt') return;
				}

				// Get parent width
				var containerWidth = Math.floor(tab.parentContainer ? tab.parentContainer.width() : tab.parent.width());
				if (!containerWidth || containerWidth == tab.lastContainerWidth) return;
				tab.lastContainerWidth = containerWidth;

				// Get links and hidden link
				var links = tab.container.children('li:not(.navigationHidden, .navigationHiddenTabs)'),
					hidden = tab.container.children('li.navigationHidden, li.navigationHiddenTabs');

				if (!hidden.length)
				{
					// Create hidden link
					if (tab.tabs)
					{
						tab.container.append('<li class="navigationHiddenTabs navTab Popup PopupControl PopupClosed"><a rel="Menu" class="navLink NoPopupGadget"><span class="menuIcon">...</span></a><div class="Menu blockLinksList primaryContent" id="' + tab.id + '"></div></li>');
						hidden = tab.container.children('.navigationHiddenTabs').hide();
					}
					else
					{
						tab.container.append('<li class="navigationHidden Popup PopupControl PopupClosed"><a rel="Menu" class="NoPopupGadget">...</a><div class="Menu blockLinksList primaryContent" id="' + tab.id + '"></div></li>');
						hidden = tab.container.children('.navigationHidden').hide();
					}
					new XenForo.PopupMenu(hidden);
				}

				// Calculate maximum width
				var maxWidth = containerWidth * tab.maxRatio;
				if (tab.siblings !== false)
				{
					tab.siblings.each(function() {
						var $this = $(this),
							width = $this.outerWidth(true);

						$this.attr('data-sibling', width);

						if (width > 0 && width < (containerWidth - 100))
						{
							maxWidth -= width;
						}
					});
				}

				// Calculate width difference
				var diff = tab.parentContainer ? tab.parent.outerWidth(true) - tab.container.width() : 0;
				if (diff > 0 && diff < containerWidth)
				{
					maxWidth -= diff;
					containerWidth -= diff;
				}

				if (maxWidth < 100 && tab.type == 'alt')
				{
					maxWidth = containerWidth;
				}
				maxWidth -= 10; // 10 extra pixels to give spacing between tabs and avoid incorrect (with huge margin for error) rounding of width in some browsers in zoomed mode

				// tab.container.attr('data-debug', 'containerWidth: ' + containerWidth + ', maxWidth: ' + maxWidth + ', lastMinWidth: ' + tab.lastMinWidth + ', lastMaxWidth: ' + tab.lastMaxWidth + ', diff: ' + diff);
				// tab.container.attr('data-debug-exit', '0');

				if (maxWidth == tab.lastMaxWidth)
				{
					// tab.container.attr('data-debug-exit', '1');
					return;
				}

				// Optimizations
				if (maxWidth < tab.lastMaxWidth && tab.lastMinWidth > 0 && maxWidth > tab.lastMinWidth)
				{
					// tab.container.attr('data-debug-exit', '2');
					return;
				}

				// Show all links, except hidden
				hidden.css('display', 'none');
				links.css('display', '');

				// Add each link
				var widthExtra = (tab.type == 'alt') ? 3 : 0, // Add 3px for inline-block
					startWidth = hidden.outerWidth(true) + widthExtra,
					total = startWidth,
					last = links.length - 1,
					hiding = false,
					hiddenList = $('<ul />'),
					firstVisible = true,
					lastVisible = false;

				// tab.container.attr('data-debug-start-width', startWidth);

				tab.lastMinWidth = -1;
				tab.lastMaxWidth = -1;
				links.removeClass('firstVisible lastVisible');

				// Always show selected link
				links.filter('.selected').each(function() {
					var width = $(this).outerWidth(true);
					if (width > 0)
					{
						total += width + widthExtra;
					}
				});

				links.each(function(i) {
					var $this = $(this),
						lastTotal = total,
						clone;

					if ($this.hasClass('selected'))
					{
						// Always show selected tab
						if (firstVisible)
						{
							$this.addClass('firstVisible');
							firstVisible = false;
						}
						lastVisible = $this;
						return;
					}

					if (!hiding)
					{
						var width = $this.outerWidth(true);
						if (width < 1) return;

						total += width + widthExtra;
						if (i == last)
						{
							// Do not hide only last tab if possible
							total -= startWidth;
						}
						if (total > maxWidth)
						{
							hiding = true;
							tab.lastMinWidth = lastTotal;
							tab.lastMaxWidth = total;
						}
					}

					// $this.attr('data-debug', 'total: ' + total + ', width: ' + width + ', hiding: ' + (hiding ? 'y' : 'n') + ', max: ' + maxWidth);

					if (hiding)
					{
						if (tab.tabs)
						{
							// Clone tab
							clone = $this.find('a.navLink').clone(true);
						}
						else
						{
							clone = $this.children();
							if (clone.hasClass('Popup'))
							{
								// Clone link with popup control, remove popup control
								clone = clone.find('.PopupControl').clone(false);
								clone.children('.arrowWidget').remove();
							}
							else
							{
								// Clone link
								clone = clone.clone(true);
							}
						}
						hiddenList.append($('<li />').html(clone));
						$this.css('display', 'none');
					}
					else
					{
						if (firstVisible)
						{
							$this.addClass('firstVisible');
							firstVisible = false;
						}
						lastVisible = $this;
					}
				});

				if (hiding)
				{
					var menu = $('#' + tab.id);
					if (!menu.length)
					{
						if (tab.tabs)
						{
							menu = $('#NavigationHiddenMenu');
						}
						else
						{
							menu = $('#NavigationLinksHiddenMenu');
						}
					}
					menu.html(hiddenList).xfActivate();
					hidden.css('display', '');
					lastVisible = hidden;
				}
				else
				{
					hidden.css('display', 'none');
				}

				if (lastVisible !== false)
				{
					lastVisible.addClass('lastVisible');
				}
			}

			function updateTabs(state)
			{
				for (var i=0; i<tabsTotal; i++)
				{
					if (tabs[i].tabs == state)
					{
						updateTab(tabs[i]);
					}
				}
			}

			XenForo.updateVisibleNavigationLinks = function() { 
				updateTabs(false);
			};
			XenForo.updateVisibleNavigationTabs = function() {
				updateTabs(true);
			};

			XenForo.forceUpdateNavigationTabs = function() {
				for (var i=0; i<tabsTotal; i++)
				{
					tabs[i].lastParentWidth = -1;
					tabs[i].lastMinWidth = -1;
					tabs[i].lastMaxWidth = -1;
					updateTab(tabs[i]);
				}
			};
		})();

		// Disable login bar handler
		var loginBar = XenForo.LoginBar;
		XenForo.LoginBar = function() 
		{
			if ($('#loginBar.loginBarOverlay').length) return;
			return loginBar.apply(this, arguments);
		};
	});

	/*
	* window.onload stuff
	*/
	$w.load(function() {
		if (compactNodes) 
		{
			fixCompactNodes(true);
		}
		if (compactThreads) 
		{
			fixCompactThreads(true);
		}
		if (staticNavigation)
		{
			checkStaticNav(true);
			$('.Menu.tabMenu').hover(function() {
				$('#navigation').addClass('hover');
			}, function() {
				$('#navigation').removeClass('hover');
			});
		}
		if (expandContent.expand)
		{
			doExpandContent(true);
		}
		if (stickyFooter)
		{
			checkFooter();
		}
		if (scaleUserProfile.scale)
		{
			scaleUserProfiles();
		}
	});

	/*
	* window.onresize stuff
	*/
	function processResizeEvent() {
		// Functions that should not be called more than once per .5 second
		resizeThrottled = false;

		if (compactNodes) 
		{
			fixCompactNodes(false);
		}
		if (compactThreads) 
		{
			fixCompactThreads(false);
		}
		if (staticNavigation)
		{
			checkStaticNav(false);
		}
		if (expandContent.expand)
		{
			doExpandContent(false);
		}
		if (stickyFooter)
		{
			checkFooter();
		}
		if (scaleUserProfile.scale)
		{
			scaleUserProfiles();
		}
		if (altNavActive)
		{
			XenForo.updateVisibleNavigationTabs();
		}
	}

	$w.resize(function() {
		if (!resizeThrottled)
		{
			resizeThrottled = true;
			setTimeout(processResizeEvent, 500);
		}

		// Run functions that are not throttled
	});

	/*
	* window.onscroll stuff
	*/
	$w.scroll(function() {
		if (staticNavigation)
		{
			checkStaticNav(false);
		}
	});

	/*
	* window.haschange stuff
	*/
	$w.on('hashchange', function() {
		if (staticNavigation)
		{
			checkStaticNav(true);
		}
	});

	/**
	* XenForo events
	*/
	$(document).bind('XenForoActivationComplete OverlayOpened TitlePrefixRecalc', function() {
		if (compactThreads)
		{
			// Move edit dialog
			$('.discussionListItems.compact .inlineCtrlGroup').each(function() {
				var $this = $(this),
					prev = $this.parent().prev();

				if (!prev.hasClass('compact-thread')) return;

				var top = Math.floor(prev.offset().top - prev.parent().offset().top + prev.outerHeight()) + 2,
					left = Math.floor(prev.offset().left - prev.parent().offset().left);

				if (!prev.hasClass('firstColumn')) left += 5;

				$this.css({
					top: top + 'px',
					left: left + 'px',
					width: Math.floor(prev.width()) + 'px'
				});
			});
		}
	});

	$(document).bind('OverlayOpened', function() {
		// Add search to chooser overlay
		$('.xenOverlay.chooserOverlay').each(function() {
			var $this = $(this),
				checked = $this.attr('data-cheched-search');

			if (checked) return;
			$this.attr('data-cheched-search', true);

			var defaultToggle = $this.find('.subHeading + .secondaryContent'),
				items = $('.primaryContent.chooserColumns > li');

			if (!defaultToggle.length || items.length < 10 || items.find('.title').length < items.length) return;

			// Add search
			defaultToggle.addClass('chooserOverlaySearchWrapper').append('<div class="chooserOverlaySearch"><input type="search" class="textCtrl" value="" /></div>');

			defaultToggle.find('input').bind('change keyup', function() {
				var search = this.value.toLowerCase();

				if (!search.length)
				{
					items.css('display', '');
					return;
				}

				// Find matches
				items.each(function() {
					var $this = $(this), 
						title = $this.find('.title').text().toLowerCase();

					if (title.indexOf(search) == -1)
					{
						$this.css('display', 'none');
					}
					else
					{
						$this.css('display', '');
					}
				});
			});
		});
	});
})(jQuery);