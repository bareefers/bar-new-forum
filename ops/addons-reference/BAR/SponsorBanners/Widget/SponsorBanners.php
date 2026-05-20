<?php

namespace BAR\SponsorBanners\Widget;

use XF\Http\Request;
use XF\Mvc\Entity\ArrayCollection;
use XF\Widget\AbstractWidget;

class SponsorBanners extends AbstractWidget
{
	protected $defaultOptions = [
		'max_width' => 468,
		'rotation_mode' => 'single',
	];

	public function render()
	{
		$banners = $this->repository('BAR\SponsorBanners:Banner')->findActiveBanners()->fetch();
		if (!$banners || $banners->count() === 0)
		{
			return '';
		}

		if ($this->options['rotation_mode'] === 'single') {
			$list = [];
			foreach ($banners as $banner) {
				$list[] = $banner;
			}

			if (count($list) > 0) {
				$idx = \XF::$time % count($list);
				$banners = new ArrayCollection([$list[$idx]]);
			}
		}

		return $this->renderer('bar_sb_widget', [
			'banners' => $banners,
		]);
	}

	public function verifyOptions(Request $request, array &$options, &$error = null)
	{
		$options = $request->filter([
			'max_width' => 'uint',
			'rotation_mode' => 'str',
		]);
		if ($options['max_width'] > 2000) {
			$options['max_width'] = 2000;
		}

		if (!in_array($options['rotation_mode'], ['single', 'all'], true)) {
			$options['rotation_mode'] = 'single';
		}

		return true;
	}
}
