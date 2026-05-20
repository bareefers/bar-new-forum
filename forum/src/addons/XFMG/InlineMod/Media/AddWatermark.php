<?php

namespace XFMG\InlineMod\Media;

use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\Repository\Media;
use XFMG\Service\Media\Watermarker;

use function count;

/**
 * @extends AbstractAction<\XFMG\Entity\MediaItem>
 */
class AddWatermark extends AbstractAction
{
	public function getTitle()
	{
		return \XF::phrase('xfmg_watermark_media_items...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return ($entity->canAddWatermark(true, $error));
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		if ($entity->canAddWatermark())
		{
			/** @var Media $mediaRepo */
			$mediaRepo = \XF::repository('XFMG:Media');
			$tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var Watermarker $watermarker */
			$watermarker = \XF::service('XFMG:Media\Watermarker', $entity, $tempWatermark);
			$watermarker->watermark();
		}
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$viewParams = [
			'mediaItems' => $entities,
			'total' => count($entities),
		];
		return $controller->view('XFMG:Public:InlineMod\Media\AddWatermark', 'xfmg_inline_mod_media_add_watermark', $viewParams);
	}
}
