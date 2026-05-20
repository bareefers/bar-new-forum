<?php

class EWRporta2_Widget_KeywordsTags extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		if (!$keywords['tags'] = $this->fetchAllKeyed("
			SELECT EWRporta2_categories.*, COUNT(*) AS count
			FROM EWRporta2_catlinks
				LEFT JOIN EWRporta2_categories ON (EWRporta2_categories.category_id = EWRporta2_catlinks.category_id)
			WHERE EWRporta2_categories.category_type = 'tag'
			GROUP BY EWRporta2_categories.category_id
			ORDER BY EWRporta2_categories.category_name ASC
			LIMIT ?
		", 'category_id', $options['keywordstags_limit']))
		{
			return 'killWidget';
		}
		
		$counts = array();
		
		foreach ($keywords['tags'] AS $tag)
		{
			$counts[$tag['category_id']] = $tag['count'];
		}

		$max_qty = max(array_values($counts));
		$min_qty = min(array_values($counts));
		
		$spread = $max_qty - $min_qty;
		$spread = $spread ? $spread : 1;
		$step = ($options['keywordstags_max'] - $options['keywordstags_min']) / $spread;
	
		foreach ($keywords['tags'] AS $key => &$value)
		{
			$value['size'] = floor($options['keywordstags_min'] + (($counts[$key] - $min_qty) * $step));
			$value['count'] = $counts[$key];
		}
		
		if ($options['keywordstags_animated'])
		{
			$keywords['animated'] = '';
		
			foreach ($keywords['tags'] AS $tag)
			{
				$keywords['animated'] .= "<a href='" . XenForo_Link::buildPublicLink('articles/category', $tag) .
					"' style='font-size: " . $tag['size']."px;'>".$tag['category_name']."</a>";
			}
		}

		return $keywords;
	}
}