<?php

namespace XFMG\Service\Album;

use XF\App;
use XF\Image\Gd;
use XF\Image\Imagick;
use XF\Mvc\Entity\AbstractCollection;
use XF\Service\AbstractService;
use XF\Util\File;
use XFMG\Entity\Album;
use XFMG\Entity\MediaItem;

class ThumbnailGenerator extends AbstractService
{
	/**
	 * @var Album
	 */
	protected $album;

	/**
	 * @var null|int
	 */
	protected $format = IMAGETYPE_JPEG;

	/**
	 * @var null|int
	 */
	protected $quality = null;

	public function __construct(App $app, Album $album)
	{
		parent::__construct($app);
		$this->setAlbum($album);
	}

	protected function setAlbum(Album $album)
	{
		$this->album = $album;
	}

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

	public function createAlbumThumbnail()
	{
		$album = $this->album;

		/** @var \XFMG\Repository\Album $repo */
		$repo = $this->repository('XFMG:Album');
		$mediaFinder = $repo->findMediaItemsForAlbumThumbnail($album);

		/** @var AbstractCollection<MediaItem> $media */
		$media = $mediaFinder->fetch(4);

		$count = $media->count();
		if (!$count)
		{
			return false;
		}

		$dimensions = $this->app->options()->xfmgThumbnailDimensions;
		$width = $dimensions['width'];
		$height = $dimensions['height'];
		$padding = 5;

		$imageManager = $this->app->imageManager();
		$baseImage = $imageManager->createImage($width * 2 + $padding, $height * 2 + $padding);

		if (!$baseImage)
		{
			return false;
		}

		switch ($count)
		{
			case 4:

				$coordMap = [
					1 => [
						'x' => 0,
						'y' => 0,
					],
					2 => [
						'x' => $width + $padding,
						'y' => 0,
					],
					3 => [
						'x' => 0,
						'y' => $height + $padding,
					],
					4 => [
						'x' => $width + $padding,
						'y' => $height + $padding,
					],
				];

				break;

			case 3:

				$coordMap = [
					1 => [
						'x' => 0,
						'y' => 0,
						'width' => $width * 2 + $padding,
						'height' => $height,
					],
					2 => [
						'x' => 0,
						'y' => $height + $padding,
					],
					3 => [
						'x' => $width + $padding,
						'y' => $height + $padding,
					],
				];

				break;

			case 2:

				$coordMap = [
					1 => [
						'x' => 0,
						'y' => 0,
						'width' => $width * 2 + $padding,
						'height' => $height,
					],
					2 => [
						'x' => 0,
						'y' => $height + $padding,
						'width' => $width * 2 + $padding,
						'height' => $height,
					],
				];

				break;

			case 1:

				$coordMap = [
					1 => [
						'x' => 0,
						'y' => 0,
						'width' => $width * 2 + $padding,
						'height' => $height * 2 + $padding,
					],
				];

				break;

			default:
				throw new \InvalidArgumentException('Not enough images available to create an album thumbnail.');
		}

		$i = 0;
		foreach ($media AS $mediaItem)
		{
			$i++;
			if (!isset($coordMap[$i]))
			{
				continue;
			}
			$coords = $coordMap[$i];

			if ($mediaItem->custom_thumbnail_date)
			{
				$abstractedPath = $mediaItem->getAbstractedCustomThumbnailPath();
			}
			else
			{
				$abstractedPath = $mediaItem->getAbstractedThumbnailPath();
			}

			if (!$abstractedPath)
			{
				continue;
			}
			$itemTempFile = File::copyAbstractedPathToTempFile($abstractedPath);

			/** @var Gd|Imagick $image */
			$image = $imageManager->imageFromFile($itemTempFile);

			if (!$image)
			{
				continue;
			}

			$image->resizeAndCrop(
				$coords['width'] ?? $width,
				$coords['height'] ?? $height
			);

			$baseImage->appendImageAt($coords['x'], $coords['y'], $image->getImage());
		}

		$baseImage->resizeTo($width, $height);

		$albumTempFile = File::getTempFile();
		$baseImage->save($albumTempFile, $this->format, $this->quality);
		File::copyFileToAbstractedPath($albumTempFile, $album->getAbstractedThumbnailPath());

		return true;
	}
}
