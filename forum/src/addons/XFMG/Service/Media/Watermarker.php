<?php

namespace XFMG\Service\Media;

use XF\App;
use XF\FileWrapper;
use XF\Image\Gd;
use XF\Image\Imagick;
use XF\Service\AbstractService;
use XF\Service\Attachment\Preparer;
use XF\Util\File;
use XFMG\Entity\MediaItem;

class Watermarker extends AbstractService
{
	protected const WATERMARK_X_PERCENT = 20;
	protected const WATERMARK_Y_PERCENT = 15;

	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $tempWatermark;

	public function __construct(App $app, MediaItem $mediaItem, $tempWatermark = null)
	{
		parent::__construct($app);

		$this->mediaItem = $mediaItem;
		$this->tempWatermark = $tempWatermark;
	}

	public function watermark($save = true)
	{
		$mediaItem = $this->mediaItem;

		$tempWatermark = $this->tempWatermark;

		$abstractedPath = $mediaItem->getAbstractedDataPath();
		if (!$abstractedPath || !$this->app->fs()->has($abstractedPath))
		{
			return false;
		}

		$tempFile = File::copyAbstractedPathToTempFile($abstractedPath);

		$this->copyToOriginalPathIfNeeded();

		$imageManager = $this->app->imageManager();
		$baseImage = $imageManager->imageFromFile($tempFile);
		if (!$baseImage)
		{
			return false;
		}

		/** @var Gd|Imagick $watermarkImage */
		$watermarkImage = $imageManager->imageFromFile($tempWatermark);

		$watermarkImage
			->setOpacity(0.8)
			->resize(
				($baseImage->getWidth() / 100) * self::WATERMARK_X_PERCENT,
				($baseImage->getHeight() / 100) * self::WATERMARK_Y_PERCENT,
				true
			);

		$x = $baseImage->getWidth() - $watermarkImage->getWidth() - 10;
		$y = $baseImage->getHeight() - $watermarkImage->getHeight() - 10;

		$baseImage->appendImageAt($x, $y, $watermarkImage->getImage());
		$baseImage->save($tempFile);

		$attachData = $mediaItem->Attachment->Data;
		$fileWrapper = new FileWrapper($tempFile, $attachData->filename);

		/** @var Preparer $attachmentPreparer */
		$attachmentPreparer = $this->service('XF:Attachment\Preparer');
		$attachmentPreparer->updateDataFromFile($attachData, $fileWrapper);

		$mediaItem->last_edit_date = time();
		$mediaItem->watermarked = true;

		if ($save)
		{
			return $mediaItem->save(false);
		}
		else
		{
			return true;
		}
	}

	public function unwatermark($save = true)
	{
		$mediaItem = $this->mediaItem;

		$originalPath = $mediaItem->getOriginalAbstractedDataPath();
		if (!$this->app->fs()->has($originalPath))
		{
			// if we think this is watermarked but we can't find the original image
			// we should consider the image to be no longer watermarked
			$mediaItem->watermarked = false;
			$mediaItem->save(false);
			return false;
		}

		$tempFile = File::copyAbstractedPathToTempFile($originalPath);

		$attachData = $mediaItem->Attachment->Data;
		$fileWrapper = new FileWrapper($tempFile, $attachData->filename);

		/** @var Preparer $attachmentPreparer */
		$attachmentPreparer = $this->service('XF:Attachment\Preparer');
		$attachmentPreparer->updateDataFromFile($attachData, $fileWrapper);

		$mediaItem->last_edit_date = time();
		$mediaItem->watermarked = false;

		if ($save)
		{
			return $mediaItem->save(false);
		}
		else
		{
			return true;
		}
	}

	protected function copyToOriginalPathIfNeeded()
	{
		$mediaItem = $this->mediaItem;
		$abstractedPath = $mediaItem->getAbstractedDataPath();
		$abstractedOriginalPath = $mediaItem->getOriginalAbstractedDataPath();

		if ($this->app->fs()->has($abstractedOriginalPath))
		{
			return;
		}

		$tempFile = File::copyAbstractedPathToTempFile($abstractedPath);
		File::copyFileToAbstractedPath($tempFile, $abstractedOriginalPath);
	}
}
