<?php

namespace XFMG\Service\Media;

use XF\Entity\Attachment;
use XF\Image\Imagick;
use XF\Service\AbstractService;
use XF\Util\File;
use XFMG\Ffmpeg\Runner;

class ThumbnailGenerator extends AbstractService
{
	/**
	 * @var null|int
	 */
	protected $format = null;

	/**
	 * @var null|int
	 */
	protected $quality = null;

	public function setFormat(int $format)
	{
		switch ($format)
		{
			case IMAGETYPE_GIF:
			case IMAGETYPE_JPEG:
			case IMAGETYPE_PNG:
			case IMAGETYPE_WEBP:
				break;

			default:
				$format = null;
		}

		$this->format = $format;
	}

	public function setQuality(int $quality)
	{
		$this->quality = $quality;
	}

	public function createTempThumbnailFromAttachment(Attachment $attachment, $abstractedDestination, $mediaType)
	{
		$data = $attachment->Data;

		$dataPath = $data->getAbstractedDataPath();

		if ($mediaType == 'image')
		{
			$sourceFile = File::copyAbstractedPathToTempFile($dataPath);

			$width = $data->width;
			$height = $data->height;

			return $this->getTempThumbnailFromImage($sourceFile, $abstractedDestination, $width, $height);
		}
		else if ($mediaType == 'video' || $mediaType == 'audio')
		{
			$ffmpegOptions = \XF::options()->xfmgFfmpeg;
			if (!$ffmpegOptions['ffmpegPath'] || !$ffmpegOptions['thumbnail'])
			{
				return false;
			}

			$tempFrame = $this->getTempFrameFromFfMpeg($dataPath, $mediaType);
			if ($tempFrame)
			{
				return $this->getTempThumbnailFromImage($tempFrame, $abstractedDestination);
			}
		}

		return false;
	}

	public function createTempPosterFromAttachment(Attachment $attachment, $abstractedDestination, $mediaType)
	{
		$data = $attachment->Data;

		$dataPath = $data->getAbstractedDataPath();

		if ($mediaType == 'video' || $mediaType == 'audio')
		{
			$ffmpegOptions = \XF::options()->xfmgFfmpeg;
			if (!$ffmpegOptions['ffmpegPath'] || !$ffmpegOptions['poster'])
			{
				return false;
			}

			$tempFrame = $this->getTempFrameFromFfMpeg($dataPath, $mediaType);
			if ($tempFrame)
			{
				return $this->getTempPosterFromImage($tempFrame, $abstractedDestination);
			}
		}

		return false;
	}

	protected $cachedFrames = [];

	public function getTempFrameFromFfMpeg($abstractedSourcePath, $mediaType)
	{
		if (isset($this->cachedFrames[$abstractedSourcePath]))
		{
			return $this->cachedFrames[$abstractedSourcePath];
		}

		$sourceFile = File::copyAbstractedPathToTempFile($abstractedSourcePath);

		$ffmpegOptions = $this->app->options()->xfmgFfmpeg;
		if (!$ffmpegOptions['ffmpegPath'])
		{
			return false;
		}

		$class = 'XFMG\Ffmpeg\Runner';
		$class = \XF::extendClass($class);

		/** @var Runner $ffmpeg */
		$ffmpeg = new $class($ffmpegOptions['ffmpegPath']);
		$ffmpeg->setFileName($sourceFile);
		$ffmpeg->setType($mediaType);

		$tempFrameFile = $ffmpeg->getKeyFrame();
		if (!$tempFrameFile)
		{
			return false;
		}

		$this->cachedFrames[$abstractedSourcePath] = $tempFrameFile;

		return $tempFrameFile;
	}

	public function getTempThumbnailFromImage($sourceFile, $abstractedDestination, $width = null, $height = null)
	{
		$tempThumbFile = null;

		if ($width === null || $height === null)
		{
			$imageInfo = getimagesize($sourceFile);
			if (!$imageInfo)
			{
				return false;
			}

			$imageType = $imageInfo[2];
			switch ($imageType)
			{
				case IMAGETYPE_GIF:
				case IMAGETYPE_JPEG:
				case IMAGETYPE_PNG:
				case IMAGETYPE_WEBP:
					break;

				default:
					return false;
			}

			$width = $imageInfo[0];
			$height = $imageInfo[1];
		}

		if ($width && $height && $this->app->imageManager()->canResize($width, $height))
		{
			$tempThumbFile = $this->generateThumbnailFromFile($sourceFile);
		}

		if (!$tempThumbFile)
		{
			return false;
		}

		try
		{
			File::copyFileToAbstractedPath($tempThumbFile, $abstractedDestination);
		}
		catch (\Exception $e)
		{
			File::deleteFromAbstractedPath($abstractedDestination);

			throw $e;
		}

		return true;
	}

	public function getTempPosterFromImage($sourceFile, $abstractedDestination, $width = null, $height = null)
	{
		$tempThumbFile = null;

		if ($width === null || $height === null)
		{
			$imageInfo = getimagesize($sourceFile);
			if (!$imageInfo)
			{
				return false;
			}

			$imageType = $imageInfo[2];
			switch ($imageType)
			{
				case IMAGETYPE_GIF:
				case IMAGETYPE_JPEG:
				case IMAGETYPE_PNG:
				case IMAGETYPE_WEBP:
					break;

				default:
					return false;
			}

			$width = $imageInfo[0];
			$height = $imageInfo[1];
		}

		if ($width && $height && $this->app->imageManager()->canResize($width, $height))
		{
			$tempThumbFile = $this->generatePosterFromFile($sourceFile);
		}

		if (!$tempThumbFile)
		{
			return false;
		}

		try
		{
			File::copyFileToAbstractedPath($tempThumbFile, $abstractedDestination);
		}
		catch (\Exception $e)
		{
			File::deleteFromAbstractedPath($abstractedDestination);

			throw $e;
		}

		return true;
	}

	public function generateThumbnailFromFile($sourceFile, &$width = null, &$height = null)
	{
		$image = $this->app->imageManager()->imageFromFile($sourceFile);
		if (!$image)
		{
			return null;
		}

		if ($image instanceof Imagick)
		{
			// Workaround to only use the first frame of a multi-frame image for the thumb
			foreach ($image->getImage() AS $imagick)
			{
				$image->setImage($imagick->getImage());
				break;
			}
		}

		$options = $this->app->options();

		if ($options->imageOptimization === 'optimize')
		{
			$this->setFormat(IMAGETYPE_WEBP);
		}

		$thumbDimensions = $options->xfmgThumbnailDimensions;
		$thumbWidth = $thumbDimensions['width'];
		$thumbHeight = $thumbDimensions['height'];

		$image->resizeAndCrop($thumbWidth, $thumbHeight)
			->unsharpMask();

		$newTempFile = File::getTempFile();
		if ($newTempFile && $image->save($newTempFile, $this->format, $this->quality))
		{
			$width = $image->getWidth();
			$height = $image->getHeight();

			return $newTempFile;
		}
		else
		{
			return null;
		}
	}

	public function generatePosterFromFile($sourceFile, &$width = null, &$height = null)
	{
		$image = $this->app->imageManager()->imageFromFile($sourceFile);
		if (!$image)
		{
			return null;
		}

		if ($image instanceof Imagick)
		{
			// Workaround to only use the first frame of a multi-frame image for the thumb
			foreach ($image->getImage() AS $imagick)
			{
				$image->setImage($imagick->getImage());
				break;
			}
		}

		$posterWidth = 1280;
		$image->resizeWidth($posterWidth)->unsharpMask();

		if ($this->app->options()->imageOptimization === 'optimize')
		{
			$this->setFormat(IMAGETYPE_WEBP);
		}

		$newTempFile = File::getTempFile();
		if ($newTempFile && $image->save($newTempFile, $this->format, $this->quality))
		{
			$width = $image->getWidth();
			$height = $image->getHeight();

			return $newTempFile;
		}
		else
		{
			return null;
		}
	}
}
