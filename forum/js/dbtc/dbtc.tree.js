/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined) {
	$.fn.tree_structure = function(options) {
		var defaults = {
			'add_entry_html': "index.php?dbtc-node-entry",
			'edit_entry_html': "index.php?dbtc-node-entry",
			'delete_entry_html': "index.php?dbtc-node-delete",
			'add_option': true,
			'edit_option': true,
			'delete_option': true,
			'confirm_before_delete' : true,
			'animate_option': [true, 5],
			'fullwidth_option': false,
			'fullheight_option': false,
			'align_option': 'center',
			'draggable_option': false,
		};
		return this.each(function() {
			if(options) $.extend(defaults, options);
			var statusmap = [ "growing", "available", "failed", "unknown" ];
			var add_entry_html = defaults['add_entry_html'];
			var edit_entry_html = defaults['edit_entry_html'];
			var delete_entry_html = defaults['delete_entry_html'];
			var add_option = defaults['add_option'];
			var edit_option = defaults['edit_option'];
			var delete_option = defaults['delete_option'];
			var confirm_before_delete = defaults['confirm_before_delete'];
			var animate_option = defaults['animate_option'];
			var fullwidth_option = defaults['fullwidth_option'];
			var fullheight_option = defaults['fullheight_option'];
			var align_option = defaults['align_option'];
			var draggable_option = defaults['draggable_option'];
			var vertical_line_text = '<span class="vertical"></span>';
			var horizontal_line_text = '<span class="horizontal"></span>';
			var add_action_text = add_option == true ? '<span class="add_action" title="Click for Add"></span>' : '';
			var edit_action_text = edit_option == true ? '<span class="edit_action" title="Click for Edit"></span>' : '';
			var delete_action_text = delete_option == true ? '<span class="delete_action" title="Click for Delete"></span>' : '';
			var highlight_text = '<span class="highlight" title="Click for Highlight | dblClick"></span>';
			var class_name = $(this).attr('class');
			var event_name = 'pageload';
			if(align_option != 'center') $('.'+class_name+' li').css({'text-align':align_option});
			
			if(fullwidth_option) {
				var i = 0;
				var prev_width;
				var get_element;
				$('.'+class_name+' li li').each(function() {
					var this_width = $(this).width();
					if(i == 0 || this_width > prev_width) {
						prev_width = $(this).width();
						get_element = $(this);
					}
					i++;
				});
				var loop = get_element.closest('ul').children('li').eq(0).nextAll().length;
				var fullwidth = parseInt(0);
				for($i=0; $i<=loop; $i++) {
					fullwidth += parseInt(get_element.closest('ul').children('li').eq($i).width());
				}
				$('.'+class_name+'').closest('div').width(fullwidth);
			}
	
			if(fullheight_option) {
				var i = 0;
				var prev_height;
				var get_element;
				$('.'+class_name+' li li').each(function() {
					var this_height = $(this).height();
					if(i == 0 || this_height > prev_height) {
						prev_height = $(this).height();
						get_element = $(this);
					}
					i++;
				});
				var loop = get_element.closest('ul').children('li').eq(0).nextAll().length;
				var fullheight = parseInt(0);
				for($i=0; $i<=loop; $i++) {
					fullheight += parseInt(get_element.closest('ul').children('li').eq($i).height());
				}
				$('.'+class_name+'').closest('div').height(fullheight);
			}
			$('.'+class_name+' li.hide').each(function() {
				$(this).children('ul').hide();
			});
			function prepend_data(target) {
				target.prepend(vertical_line_text + horizontal_line_text).children('div').prepend(add_action_text + delete_action_text + edit_action_text);
				if(target.children('ul').length != 0) target.hasClass('hide') ? target.children('div').prepend('<b class="hide show"></b>') : target.children('div').prepend('<b class="hide"></b>');
				target.children('div').prepend(highlight_text);
			}
			function draw_line(target) {
				var child_width = target.children('div').outerWidth(true) / 2;
				var child_left = target.children('div').offset().left;
				if(target.parents('li').offset() != null) var parent_child_height = target.parents('li').offset().top;
				vertical_height = (target.offset().top - parent_child_height) - target.parents('li').children('div').outerHeight(true) / 2 ;
				target.children('span.vertical').css({'height':vertical_height, 'margin-top':-vertical_height, 'margin-left':child_width, 'left':child_left});
				if(target.parents('li').offset() == null) {
					var width = 0;
				} else {
					var parents_width = target.parents('li').children('div').offset().left + (target.parents('li').children('div').width() / 2);
					var current_width = child_left + (target.children('div').width() / 2);
					var width = parents_width - current_width;
				}
				var horizontal_left_margin = width < 0 ? -Math.abs(width) + child_width : child_width;
				target.children('span.horizontal').css({'width':Math.abs(width), 'margin-top':-vertical_height, 'margin-left':horizontal_left_margin, 'left':child_left});
			}
			if(animate_option[0] == true) {
				function animate_call_structure() {
					$timeout = setInterval(function() {
						animate_li();
					}, animate_option[1]);
				}
				var length = $('.'+class_name+' li').length;
				var i = 0;
				function animate_li() {
					prepend_data($('.'+class_name+' li').eq(i));
					draw_line($('.'+class_name+' li').eq(i));
					i++;
					if(i == length) {
						i = 0;
						clearInterval($timeout);
					}
				}
			}
			function call_structure() {
				$('.'+class_name+' li').each(function() {
					if(event_name == 'pageload') prepend_data($(this));
					draw_line($(this));
				});
			}
			animate_option[0] ? animate_call_structure() : call_structure();
			event_name = 'others';
			$(window).resize(function() { call_structure(); });

			// $(this).scroll(function() { console.log("SCROLLING"); call_structure(); });
			
			// console.log($('ul.'+class_name+' li'));
			
			$('.'+class_name+' b.hide').live('click', function() {
				$(this).toggleClass('show');
				$(this).closest('li').toggleClass('hide').children('ul').toggle();
				call_structure();
			});
			$('.'+class_name+' li > div').live('hover', function(event) {
				if(event.type == 'mouseenter' || event.type == 'mouseover') {
					$('.'+class_name+' li > div.current').removeClass('current');
					$('.'+class_name+' li > div.children').removeClass('children');
					$('.'+class_name+' li > div.parent').removeClass('parent');
					$(this).addClass('current');
					$(this).closest('li').children('ul').children('li').children('div').addClass('children');
					$(this).closest('li').closest('ul').closest('li').children('div').addClass('parent');
					$(this).children('span.highlight, span.add_action, span.delete_action, span.edit_action').show();
				} else {
					$(this).children('span.highlight, span.add_action, span.delete_action, span.edit_action').hide();
					$('.'+class_name+' li > div.current').removeClass('current');
				}
			});
			$('.'+class_name+' span.highlight').live('click', function() {
				$('.'+class_name+' li.highlight').removeClass('highlight');
				$('.'+class_name+' li > div.parent').removeClass('parent');
				$('.'+class_name+' li > div.children').removeClass('children');
				$(this).closest('li').addClass('highlight');
				$('.highlight li > div').addClass('children');
				var _this = $(this).closest('li').closest('ul').closest('li');
				find_parent(_this);
			});
			$('.'+class_name+' span.highlight').live('dblclick', function() {
				if(fullwidth_option) $('.'+class_name+'').parent('div').parent('div').scrollLeft(0);
				if(fullheight_option) $('.'+class_name+'').parent('div').parent('div').scrollTop(0);
				$('.'+class_name+' li > div').not(".parent, .current, .children").closest('li').addClass('none');
				$('.'+class_name+' li div b.hide.show').closest('div').closest('li').children('ul').addClass('show');
				$('.'+class_name+' li div b.hide').addClass('none');
				$('body').prepend('<img src="images/back.png" class="back_btn" />');
				call_structure();
				$('.back_btn').click(function() {
					$('.'+class_name+' ul.show').removeClass('show');
					$('.'+class_name+' li.none').removeClass('none');
					$('.'+class_name+' li div b.hide').removeClass('none');
					$(this).remove();
					call_structure();
				});
			});
			function find_parent(_this) {
				if(_this.length > 0) {
					_this.children('div').addClass('parent');
					_this = _this.closest('li').closest('ul').closest('li');
					return find_parent(_this);
				}
			}
			if(add_option) {
				$(document.body).on('click', '.add_action', function(e) {
		
					var dbtc_node = $(this).parent(".node");
					var _addthis = dbtc_node;

					// display form
					addFormPopUp(dbtc_node, e); 
	
					$(document.body).off('AutoValidationComplete', '.formOverlay').on('AutoValidationComplete', '.formOverlay', function(e) {
						if (e.ajaxData && e.ajaxData.dbtc_receiver_id) {
							// get formatted html of the new node we're adding
							var html_value = NodeHtml(e.ajaxData);
							
							// add the html value to the closest list element
							if (_addthis.closest('li').children('ul').length > 0) {
								// if it's already got children
								_addthis.closest('li').children('ul').append(html_value);
							} else {
								// if the node doesn't have children
								_addthis.closest('li').append($('<ul></ul>').append(html_value));
							}
							$('li > div.zindex').removeClass('zindex');
							call_structure();
						} else {
							_addthis.closest('form').find('textarea').addClass('error');
						}
					});
				});

			}
			if(delete_option) {
				$(document.body).on('click', '.delete_action', function(e) {
					var dbtc_node = $(this).parent(".node");
					
					deleteFormPopUp(dbtc_node, e);
					
					$(document.body).off('AutoValidationComplete', '.formOverlay').on('AutoValidationComplete', '.formOverlay', function(e) {
						if (e.ajaxData && e.ajaxData.dbtc_transaction_id) {
							// get formatted html of the new node we're adding
							// add the html value to the closest list element
							if (dbtc_node.closest('li').children('ul').length > 0) {
								// if it's already got children do nothing
							} else {
								// if the node doesn't have children
								if ($(dbtc_node).parent('li').parent('ul').children('li').length == 1) { 
									$(dbtc_node).parent('li').parent('ul').fadeOut().remove();
								} else {
									$(dbtc_node).parent('li').fadeOut().remove();
								}
							}
							$('li > div.zindex').removeClass('zindex');
							call_structure();
						} else {
							dbtc_node.closest('form').find('textarea').addClass('error');
						}
					});
					
				});
			}
			if(edit_option) {
				$(document.body).on('click', '.edit_action', function(e) {

					var dbtc_node = $(this).parent(".node");
					var _editthis = dbtc_node;
					// console.log(dbtc_node);
					editFormPopUp(dbtc_node, e);
					
					$(document.body).off('AutoValidationComplete', '.formOverlay').on('AutoValidationComplete', '.formOverlay', function(e) {
						if (e.ajaxData && e.ajaxData.dbtc_receiver_id) {
							$(dbtc_node).attr("class", "node " + statusmap[e.ajaxData.dbtc_status_id] + " current");
							$(dbtc_node).find(".dbtc_username").val(e.ajaxData.dbtc_receiver_id);
							$(dbtc_node).find(".dbtc_username").text(e.ajaxData.dbtc_receiver_name);
							$(dbtc_node).find(".avatar").replaceWith(e.ajaxData.dbtc_receiver_avatar_html);

							$(dbtc_node).find(".dbtc_date").text(e.ajaxData.dbtc_date);
						
							$('li > div.zindex').removeClass('zindex');
							call_structure();
						} else {
							_editthis.closest('form').find('textarea').addClass('error');
						}
					});
				});
			}
			if(draggable_option) {
				function draggable_event() {
					$('.'+class_name+' li > div').draggable({
						cursor: 'move',
						distance: 40,
						zIndex: 5,
						revert : true,
						revertDuration: 100,
						snap: '.tree li div',
						snapMode: 'inner',
						start: function(event, ui) {
							$('li.li_children').removeClass('li_children');
							$(this).closest('li').addClass('li_children');
						},
						stop: function(event, ul) {
							var drop_err = droppable_event();
							if(drop_err == undefined) {
								$('body').prepend('<div class="drag_error">Drag it Correctly...</div>');
								$('div.drag_error').animate({
									top : 200
								}, 4000, function() {
									$(this).remove();
								});
							}
						}
					});
				}
				function droppable_event() {
					$('.'+class_name+' li > div').droppable({
						accept: '.tree li div',
						drop: function(event, ui) {
							$('div.check_div').removeClass('check_div');
							$('.li_children div').addClass('check_div');
							if($(this).hasClass('check_div')) {
								alert('Cant Move on Child Element.');
							} else {
								var data = 'data={"action":"drag", "id":"'+$(ui.draggable[0]).attr('id')+'", "parentid":"'+$(this).attr('id')+'"}';
								//$.ajax({
									//type: 'POST',
									//url: 'ajax.php',
									//data: data,
									//success: function(data) {
									//}
								//});
								$(this).next('ul').length == 0 ? $(this).after('<ul><li>'+$(ui.draggable[0]).attr({'style':''}).closest('li').html()+'</li></ul>') : $(this).next('ul').append('<li>'+$(ui.draggable[0]).attr({'style':''}).closest('li').html()+'</li>');
								$(ui.draggable[0]).closest('ul').children('li').length == 1 ? $(ui.draggable[0]).closest('ul').remove() : $(ui.draggable[0]).closest('li').remove();
								call_structure();
								draggable_event();
								$('body').prepend('<div class="drop_msg">Drag Successfully...</div>');
								$('div.drop_msg').animate({
									top : 200
								}, 4000, function() {
									$(this).remove();
								});
							}
						}
					});
				}
				$('.'+class_name+' li > div').disableSelection();
				draggable_event();
			}
			
			
			// display form and return data
			function addFormPopUp(container, e) {
				e.preventDefault()
				XenForo.ajax(
					add_entry_html,
					{},
					function (ajaxData, textStatus) {
						if (ajaxData.templateHtml) {
							new XenForo.ExtLoader(e, function() {
								XenForo.createOverlay('',ajaxData.templateHtml, '').load();
								console.log($(container));
								$('div.overlayHeading').text("Add Frag Entry");
								$('[name=dbtc_thread_id]').val($(container).find('div#dbtc_thread_id').attr('value'));
								// id of donor is in the dbtc_username value field
								$('[name=dbtc_donor_id]').val($(container).find('div#dbtc_receiver_id').attr('value'));
								$('[name=dbtc_date]').val($(container).find('div#dbtc_date').text());
								$('[name=dbtc_parent_transaction_id]').val($(container).attr('data-id'));
							});
						}
					}
				);
			}
			
			function editFormPopUp(container, e) {
				e.preventDefault()
				XenForo.ajax(
					edit_entry_html,
					{},
					function (ajaxData, textStatus) {
						if (ajaxData.templateHtml) {
							new XenForo.ExtLoader(e, function() {
								XenForo.createOverlay('',ajaxData.templateHtml, '').load();
								// set default stuff
								var status = $(container).attr('class').match('growing|available|failed|unknown');

								$('div.overlayHeading').text("Edit Frag");
								
								$('[name=dbtc_transaction_id]').val($(container).parent().find('div#dbtc_transaction').data('id'));
								$('[name=dbtc_thread_id]').val($(container).find('div#dbtc_thread_id').attr('value'));
								$('[name=dbtc_donor_id]').val($(container).find('div#dbtc_donor_id').attr('value'));
								$('[name=dbtc_parent_transaction_id]').val($(container).find('div#dbtc_parent_transaction_id').attr('value'));
								$('[name=dbtc_receiver_name]').val($(container).find('div#dbtc_username').text());
								$('[name=dbtc_status_id]').val(jQuery.inArray(status[0], statusmap));
								$('[name=dbtc_date]').val($(container).find('div#dbtc_date').text());
							});
						}
					}
				);
			}
			
			function deleteFormPopUp(container, e) {
				e.preventDefault()
				XenForo.ajax(
					delete_entry_html,
					{},
					function (ajaxData, textStatus) {
						if (ajaxData.templateHtml) {
							new XenForo.ExtLoader(e, function() {
								XenForo.createOverlay('',ajaxData.templateHtml, '').load();
								// set form action to edit mode
								console.log($(container).parent('li').children('ul').length);
								if ($(container).parent('li').children('ul').length == 0) {
									
									var status = $(container).attr('class').match('growing|available|failed|unknown');
									
									$('[name=dbtc_transaction_id]').val($(container).parent().find('div#dbtc_transaction').data('id'));
									$('[name=dbtc_receiver_name]').val($(container).find('div#dbtc_username').text());
									$('[name=dbtc_status_id]').val(jQuery.inArray(status[0], statusmap));
									$('[name=dbtc_date]').val($(container).find('div#dbtc_date').text());
									
								} else {
									$('div.overlayHeading #deletetext').text("DELETION NOT ALLOWED AT THIS LEVEL");
									$('dl.ctrlUnit').remove();
								}
							});
						}
					}
				);
			}
			
			function NodeHtml(data) {
				var node, listing;
				// set hidden fields:
				listing = $('<li>'+ vertical_line_text + horizontal_line_text + '</li>');
				node = $('<div id="dbtc_transaction" class="node" data-id="' + data.dbtc_transaction_id + '"></div>');
				node.attr("class", "node " + statusmap[data.dbtc_status_id] + " current");
				//console.log(data);
				node.append(highlight_text + add_action_text + delete_action_text + edit_action_text);
				node.append('<div id="dbtc_thread_id" class="dbtc_thread_id" value="'+data.dbtc_thread_id+'"></div>');
				node.append('<div id="dbtc_donor_id" class="dbtc_donor_id" value="'+data.dbtc_donor_id+'"></div>');
				node.append(data.dbtc_receiver_avatar_html);
				node.append('<div id="dbtc_username" class="dbtc_username" value="'+data.dbtc_receiver_id+'">'+data.dbtc_receiver_name+'</div>');
				node.append('<div id="dbtc_date" class="dbtc_date">'+data.dbtc_date+'</div>');
				//console.log(node);
				listing.append(node);
				return listing;
			}
			
		});
	};
}
(jQuery, this, document);