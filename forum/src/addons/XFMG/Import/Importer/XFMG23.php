<?php

namespace XFMG\Import\Importer;

use XF\Db\AbstractAdapter;
use XF\Entity\FeaturedContent;
use XF\Import\StepState;
use XFMG\Entity\MediaItem;

use function intval;

class XFMG23 extends XFMG22
{
	public static function getListInfo()
	{
		return [
			'target' => 'XenForo Media Gallery',
			'source' => 'XenForo Media Gallery 2.3',
		];
	}

	protected function validateVersion(AbstractAdapter $db, &$error)
	{
		$versionId = $db->fetchOne(
			"SELECT version_id
				FROM xf_addon
				WHERE addon_id = 'XFMG'"
		);
		if (!$versionId || intval($versionId) < 2030031)
		{
			$error = \XF::phrase('xfmg_you_may_only_import_from_xenforo_media_gallery_x', [
				'version' => '2.3',
			]);
			return false;
		}

		return true;
	}

	public function getSteps()
	{
		$steps = parent::getSteps();

		$steps = $this->extendSteps(
			$steps,
			[
				'title' => \XF::phrase('xfmg_featured_media'),
				'depends' => ['mediaItems'],
			],
			'featuredMedia',
			'comments'
		);

		return $steps;
	}

	public function getStepEndFeaturedMedia(): int
	{
		return $this->getMaxFeaturedContentIdForContentType('xfmg_media');
	}

	public function stepFeaturedMedia(
		StepState $state,
		array $stepConfig,
		int $maxTime
	): StepState
	{
		return $this->getFeatureStepStateForContentType(
			'xfmg_media',
			$state,
			$stepConfig,
			$maxTime,
			function (MediaItem $content, FeaturedContent $feature): void
			{
				$content->fastUpdate('featured', true);
			}
		);
	}
}
