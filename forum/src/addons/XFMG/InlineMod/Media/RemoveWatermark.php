<?php

namespace XFMG\InlineMod\Media;

use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\Service\Media\Watermarker;

use function count;

/**
 * @extends AbstractAction<\XFMG\Entity\MediaItem>
 */
class RemoveWatermark extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('xfmg_unwatermark_media_items...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return ($entity->canRemoveWatermark(true, $error));
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var Watermarker $watermarker */
		$watermarker = \XF::service('XFMG:Media\Watermarker', $entity);
		$watermarker->unwatermark();
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$viewParams = [
			'mediaItems' => $entities,
			'total' => count($entities),
		];
		return $controller->view('XFMG:Public:InlineMod\Media\RemoveWatermark', 'xfmg_inline_mod_media_remove_watermark', $viewParams);
	}
}
