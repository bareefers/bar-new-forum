/** @param {jQuery} $ jQuery Object */
!function($, window, document, _undefined)
{
	XenForo.GoogleMap = function($map)
	{
		$map.bind('keyup', function() {
			var address = $('#ctrl_address').val();
			var citystate = $('#ctrl_citystate').val();
			var zipcode = $('#ctrl_zipcode').val();

			$('#ctrl_googlemap').attr('src', geoLocUrl.replace('{location}', encodeURIComponent(address+','+citystate+' '+zipcode)));
		});
	}

	// *********************************************************************

	XenForo.register('.GoogleMap', 'XenForo.GoogleMap');
}
(jQuery, this, document);