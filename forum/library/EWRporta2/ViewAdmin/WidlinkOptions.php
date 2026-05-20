<?php

class EWRporta2_ViewAdmin_WidlinkOptions extends XenForo_ViewAdmin_Base
{
	public function renderHtml()
	{
		$options = array();

		foreach ($this->_params['preparedOptions'] AS $optionId => $option)
		{
			$x = floor($option['display_order'] / 100);
			$options[$x][$optionId] = $option;
		}

		$renderedOptions = array();

		foreach ($options AS $x => $optionGroup)
		{
			$renderedOptions[$x] = XenForo_ViewAdmin_Helper_Option::renderPreparedOptionsHtml(
				$this, $optionGroup, false
			);
		}

		$this->_params['preparedOptions'] = $renderedOptions;
	}
}