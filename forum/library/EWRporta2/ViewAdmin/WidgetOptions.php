<?php

class EWRporta2_ViewAdmin_WidgetOptions extends XenForo_ViewAdmin_Base
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
				$this, $optionGroup, true
			);

			foreach ($renderedOptions[$x] AS &$renderedOption)
			{
				$renderedOption = preg_replace('#options/edit-option/(\w+)#i', 'porta2/options/$1/edit', $renderedOption);
			}
		}

		$this->_params['preparedOptions'] = $renderedOptions;
	}
}