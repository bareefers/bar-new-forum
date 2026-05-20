<?php

namespace XFMG\XF\Widget;

use XFMG\Entity\Category;

class FeaturedContent extends XFCP_FeaturedContent
{
	protected function getContextualOptions(): array
	{
		$options = parent::getContextualOptions();

		$category = $this->contextParams['category'] ?? null;
		if ($category && $category instanceof Category)
		{
			return [
				'content_type' => 'xfmg_media',
				'content_container_id' => $category->category_id,
			];
		}

		return $options;
	}
}
