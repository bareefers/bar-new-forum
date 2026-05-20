<?php

namespace XFMG\InlineMod;

use XF\InlineMod\AbstractHandler;
use XF\InlineMod\FeaturableTrait;
use XF\Mvc\Entity\Entity;
use XFMG\Entity\MediaItem;

/**
 * @extends AbstractHandler<\XFMG\Entity\MediaItem>
 */
class Media extends AbstractHandler
{
	use FeaturableTrait;

	public function getPossibleActions()
	{
		$actions = [];

		$actions['move'] = $this->getActionHandler('XFMG:Media\Move');

		$actions['delete'] = $this->getActionHandler('XFMG:Media\Delete');

		$actions['add_watermark'] = $this->getActionHandler('XFMG:Media\AddWatermark');
		$actions['remove_watermark'] = $this->getActionHandler('XFMG:Media\RemoveWatermark');

		$actions['undelete'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_undelete_media_items'),
			'canUndelete',
			function (Entity $entity)
			{
				/** @var MediaItem $entity */
				if ($entity->media_state == 'deleted')
				{
					$entity->media_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['approve'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_approve_media_items'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var MediaItem $entity */
				if ($entity->media_state == 'moderated')
				{
					$entity->media_state = 'visible';
					$entity->save();
				}
			}
		);

		$actions['unapprove'] = $this->getSimpleActionHandler(
			\XF::phrase('xfmg_unapprove_media_items'),
			'canApproveUnapprove',
			function (Entity $entity)
			{
				/** @var MediaItem $entity */
				if ($entity->media_state == 'visible')
				{
					$entity->media_state = 'moderated';
					$entity->save();
				}
			}
		);

		static::addPossibleFeatureActions(
			$this,
			$actions,
			\XF::phrase('xfmg_feature_media_items'),
			\XF::phrase('xfmg_unfeature_media_items'),
			'canFeatureUnfeature'
		);

		return $actions;
	}

	public function getEntityWith()
	{
		return 'User';
	}
}
