<?php

namespace BAR\SponsorBanners\Repository;

use XF\Mvc\Entity\Repository;

class Banner extends Repository
{
	public function findBannersForList()
	{
		return $this->finder('BAR\\SponsorBanners:Banner')
			->order('display_order')
			->order('banner_id');
	}

	public function findActiveBanners()
	{
		return $this->finder('BAR\\SponsorBanners:Banner')
			->where('is_active', 1)
			->whereOr(
				['image_path', '!=', ''],
				['remote_url', '!=', '']
			)
			->order('display_order')
			->order('banner_id');
	}

	/**
	 * Next slot for a new banner (append to end of current sort).
	 */
	public function getNextDisplayOrder(): int
	{
		$max = (int) $this->db()->fetchOne('SELECT MAX(display_order) FROM xf_bar_sponsor_banner');

		return $max > 0 ? $max + 10 : 10;
	}

	/**
	 * Normalize display_order to 10, 20, 30… in current list order (after deletes or upgrade).
	 */
	public function renumberAllDisplayOrders(): void
	{
		$banners = $this->findBannersForList()->fetch();
		$order = 10;

		foreach ($banners AS $banner)
		{
			if ((int) $banner->display_order !== $order)
			{
				$banner->fastUpdate('display_order', $order);
			}
			$order += 10;
		}
	}
}
