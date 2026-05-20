<?php

namespace XFMG\Option;

use XF\Entity\Option;
use XF\Option\AbstractOption;

class Style extends AbstractOption
{
	public static function renderSelect(Option $option, array $htmlParams)
	{
		/** @var \XF\Repository\Style $styleRepo */
		$styleRepo = \XF::repository('XF:Style');

		$choices = [0 => ''];
		foreach ($styleRepo->getStyleTree(false)->getFlattened() AS $entry)
		{
			if ($entry['record']->user_selectable)
			{
				$choices[$entry['record']->style_id] = $entry['record']->title;
			}
		}

		return self::getSelectRow($option, $htmlParams, $choices);
	}
}
