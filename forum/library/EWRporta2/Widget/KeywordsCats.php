<?php

class EWRporta2_Widget_KeywordsCats extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if (!$keywords = $this->fetchAllKeyed("
			SELECT EWRporta2_categories.*, COUNT(*) AS count
			FROM EWRporta2_catlinks
				LEFT JOIN EWRporta2_categories ON (EWRporta2_categories.category_id = EWRporta2_catlinks.category_id)
			WHERE EWRporta2_categories.category_type = 'category'
			GROUP BY EWRporta2_categories.category_id
			ORDER BY EWRporta2_categories.category_name ASC
		", 'category_id'))
		{
			return 'killWidget';
		}

		return $keywords;
	}
}