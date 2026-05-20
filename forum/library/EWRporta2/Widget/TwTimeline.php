<?php

class EWRporta2_Widget_TwTimeline extends XenForo_Model
{
	public function getUncachedData($widget, &$options)
	{
		$chrome = array();
	
		if ($options['twtimeline_options']['scroll']) { $chrome[] = 'noscrollbar'; }
		if ($options['twtimeline_options']['header']) { $chrome[] = 'noheader'; }
		if ($options['twtimeline_options']['footer']) { $chrome[] = 'nofooter'; }
		if ($options['twtimeline_options']['border']) { $chrome[] = 'noborders'; }
		if ($options['twtimeline_options']['transparent']) { $chrome[] = 'transparent'; }
		
		return implode(' ', $chrome);
	}
}