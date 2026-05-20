<?php

class EWRporta2_ViewPublic_FindTags extends XenForo_ViewPublic_Base
{
	public function renderJson()
	{
		$results = array();
		foreach ($this->_params['tags'] AS $tag)
		{
			$results[$tag['category_name']] = htmlspecialchars($tag['category_name']);
		}

		return array(
			'results' => $results
		);
	}
}