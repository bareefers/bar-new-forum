<?php

namespace XFMG\Attachment;

use XF\Attachment\AbstractHandler;
use XF\Attachment\Manipulator;
use XF\Entity\Attachment;
use XF\FileWrapper;
use XF\Http\Upload;
use XF\Mvc\Entity\Entity;
use XF\Util\File;
use XFMG\Entity\Album;
use XFMG\Entity\Category;
use XFMG\Entity\MediaItem;
use XFMG\Entity\MediaTemp;
use XFMG\Service\Media\MP3Detector;
use XFMG\Service\Media\TempCreator;
use XFMG\Service\Media\Watermarker;
use XFMG\VideoInfo\Preparer;
use XFMG\XF\Entity\User;

use function count, in_array, intval;

/**
 * @extends AbstractHandler<MediaItem, array{
 *     media_id?: int|null,
 *     album_id?: int|null,
 *     category_id?: int|null,
 * }>
 */
class Media extends AbstractHandler
{
	public function getContainerWith()
	{
		$visitor = \XF::visitor();

		return ['Album', 'Category', 'Category.Permissions|' . $visitor->permission_combination_id];
	}

	public function canView(Attachment $attachment, Entity $container, &$error = null)
	{
		return $container->canView();
	}

	public function canManageAttachments(array $context, &$error = null)
	{
		$em = \XF::em();

		/** @var User $visitor */
		$visitor = \XF::visitor();

		if (!empty($context['media_id']))
		{
			/** @var MediaItem $mediaItem */
			$mediaItem = $em->find('XFMG:MediaItem', intval($context['media_id']));
			if (!$mediaItem || !$mediaItem->canView() || !$mediaItem->canEdit())
			{
				return false;
			}
		}
		else if (!empty($context['category_id']))
		{
			/** @var Category $category */
			$category = $em->find('XFMG:Category', intval($context['category_id']));
			if (!$category || !$category->canView() || !$category->canAddMedia())
			{
				return false;
			}
		}
		else if (!empty($context['album_id']))
		{
			/** @var Album $album */
			$album = $em->find('XFMG:Album', intval($context['album_id']));
			if (!$album || !$album->canView() || !$album->canAddMedia())
			{
				return false;
			}
		}
		else
		{
			// Uploading but not created an album yet.
			return $visitor->canCreateAlbum();
		}

		return true;
	}

	public function validateAttachmentUpload(Upload $upload, Manipulator $manipulator)
	{
		if (!$upload->getTempFile())
		{
			return;
		}

		$extension = $upload->getExtension();

		/** @var \XFMG\Repository\Media $repo */
		$repo = \XF::repository('XFMG:Media');

		$mediaType = $repo->getMediaTypeFromExtension($extension);

		if ($mediaType == 'video')
		{
			$videoInfo = new Preparer($upload->getTempFile());
			$result = $videoInfo->getInfo();

			$requiresTranscoding = (!$result->isValid() || $result->requiresTranscoding());
		}
		else if ($mediaType == 'audio')
		{
			/** @var MP3Detector $MP3Detector */
			$MP3Detector = \XF::service('XFMG:Media\MP3Detector', $upload->getTempFile());

			$requiresTranscoding = ($MP3Detector->isValidMP3() ? false : true);
		}
		else
		{
			$requiresTranscoding = false;
		}

		if ($requiresTranscoding)
		{
			$ffmpegOptions = \XF::options()->xfmgFfmpeg;
			if ($ffmpegOptions['transcode'])
			{
				$validator = \XF::app()->validator('XFMG:Ffmpeg');
				$ffmpegPath = $validator->coerceValue($ffmpegOptions['ffmpegPath']);
				$canTranscode = $validator->isValid($ffmpegPath, $errorKey);
			}
			else
			{
				$canTranscode = false;
			}

			if (!$canTranscode)
			{
				$upload->logError('xfmgCannotTranscode', \XF::phrase('xfmg_this_file_is_not_encoded_in_supported_format_and_cannot_be_transcoded'));
			}
		}

		if (in_array($mediaType, ['audio', 'image', 'video']))
		{
			$visitor = \XF::visitor();
			$constraints = $manipulator->getConstraints();

			$thisFileSize = $runningTotal = $upload->getFileSize();
			$newAttachments = $manipulator->getNewAttachments();
			if (count($newAttachments))
			{
				foreach ($newAttachments AS $attachment)
				{
					$runningTotal += intval($attachment->getFileSize());
				}
			}

			$totalSize = $constraints['total'];
			$userQuota = $visitor->xfmg_media_quota;

			if ($totalSize !== PHP_INT_MAX && ($userQuota + $runningTotal > $totalSize))
			{
				// calculate remaining quota, minus the file that tipped it over the limit.
				$fileSize = \XF::language()->fileSizeFormat($thisFileSize);
				$remaining = \XF::language()->fileSizeFormat($totalSize - $userQuota - $runningTotal + $thisFileSize);
				$upload->logError('xfmgStorageExceeded', \XF::phrase('xfmg_file_you_have_uploaded_is_x_which_exceeds_your_storage_quota', ['size' => $fileSize, 'remaining' => $remaining]));
			}
		}
	}

	public function onNewAttachment(Attachment $attachment, FileWrapper $file)
	{
		/** @var TempCreator $tempCreator */
		$tempCreator = \XF::app()->service('XFMG:Media\TempCreator');
		$tempCreator->setAttachment($attachment);
		if ($file->getExif())
		{
			$tempCreator->setExif($file->getExif());
		}
		$tempCreator->save();
	}

	/**
	 * @return array{
	 *     attachment: array{
	 *         attachment_id: int|null,
	 *         filename: string,
	 *         file_size: int,
	 *         file_size_printable: string,
	 *         thumbnail_url: string,
	 *         retina_thumbnail_url: string,
	 *         width: int,
	 *         height: int,
	 *         icon: string,
	 *         icon_name: string,
	 *         is_video: bool,
	 *         is_audio: bool,
	 *         link: string,
	 *         type_grouping: string,
	 *         temp_media_id: int|null,
	 *         media_hash: string,
	 *         media_type: string,
	 *         temp_thumbnail_url: string|null,
	 *         thumbnail_date: int,
	 *         title: string,
	 *         description: string,
	 *         requires_transcoding: bool,
	 *     },
	 *     link: string,
	 * }
	 */
	public function prepareAttachmentJson(Attachment $attachment, array $context, array $json)
	{
		$em = \XF::em();

		/** @var MediaTemp $tempMedia */
		$tempMedia = $em->findOne('XFMG:MediaTemp', ['attachment_id' => $attachment->attachment_id]);

		if (!$tempMedia)
		{
			return $json;
		}

		$json['attachment'] += [
			'temp_media_id' => $tempMedia->temp_media_id,
			'media_hash' => $tempMedia->media_hash,
			'media_type' => $tempMedia->media_type,
			'temp_thumbnail_url' => $tempMedia->temp_thumbnail_url,
			'thumbnail_date' => $tempMedia->thumbnail_date,
			'title' => $tempMedia->title,
			'description' => $tempMedia->description,
			'requires_transcoding' => $tempMedia->requires_transcoding,
		];
		return $json;
	}

	public function onAssociation(Attachment $attachment, ?Entity $container = null)
	{
		/** @var MediaTemp $tempMedia */
		$tempMedia = \XF::em()->findOne('XFMG:MediaTemp', ['attachment_id' => $attachment->attachment_id]);

		if (!$container)
		{
			$tempMedia->delete();
			return;
		}

		if ($tempMedia->thumbnail_date)
		{
			$tempThumbPath = $tempMedia->getAbstractedTempThumbnailPath();
			$mediaThumbPath = $container->getAbstractedThumbnailPath();

			$tempFile = File::copyAbstractedPathToTempFile($tempThumbPath);
			File::copyFileToAbstractedPath($tempFile, $mediaThumbPath);

			$container->fastUpdate('thumbnail_date', $tempMedia->thumbnail_date);

			File::deleteFromAbstractedPath($tempThumbPath);
		}
		if ($tempMedia->poster_date)
		{
			$tempPosterPath = $tempMedia->getAbstractedTempPosterPath();
			$mediaPosterPath = $container->getAbstractedPosterPath();

			$tempFile = File::copyAbstractedPathToTempFile($tempPosterPath);
			File::copyFileToAbstractedPath($tempFile, $mediaPosterPath);

			$container->fastUpdate('poster_date', $tempMedia->poster_date);

			File::deleteFromAbstractedPath($tempPosterPath);
		}

		$updatePath = false;

		$extension = strtolower($attachment->extension);

		if ($container->media_type == 'video')
		{
			if (!File::isVideoInlineDisplaySafe($extension) && !$tempMedia->requires_transcoding)
			{
				throw new \UnexpectedValueException("File extension '$extension' is not valid for video");
			}

			$updatePath = 'data://xfmg/video/%FLOOR%/%DATA_ID%-%HASH%.' . $extension;
		}
		else if ($container->media_type == 'audio')
		{
			if (!File::isAudioInlineDisplaySafe($extension) && !$tempMedia->requires_transcoding)
			{
				throw new \UnexpectedValueException("File extension '$extension' is not valid for audio");
			}

			$updatePath = 'data://xfmg/audio/%FLOOR%/%DATA_ID%-%HASH%.' . $extension;
		}

		if ($updatePath)
		{
			$oldPath = $container->getAbstractedDataPath();
			$attachment->Data->fastUpdate('file_path', $updatePath);
			$newPath = $container->getAbstractedDataPath();

			\XF::fs()->move($oldPath, $newPath);
		}

		$tempMedia->delete();

		if ($container->canAddWatermark(false) && !$container->canAddMediaWithoutWatermark())
		{
			/** @var \XFMG\Repository\Media $mediaRepo */
			$mediaRepo = \XF::repository('XFMG:Media');
			$tempWatermark = $tempWatermark = $mediaRepo->getWatermarkAsTempFile();

			/** @var Watermarker $watermarker */
			$watermarker = \XF::service('XFMG:Media\Watermarker', $container, $tempWatermark);
			$watermarker->watermark();
		}
	}

	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		if (!$container)
		{
			return;
		}

		$container->delete(false);
	}

	public function getConstraints(array $context)
	{
		$em = \XF::em();

		if (!empty($context['media_id']))
		{
			/** @var MediaItem $mediaItem */
			$mediaItem = $em->find('XFMG:MediaItem', intval($context['media_id']));
			return $mediaItem->getAttachmentConstraints();
		}
		else if (!empty($context['category_id']))
		{
			/** @var Category $category */
			$category = $em->find('XFMG:Category', intval($context['category_id']));
			return $category->getAttachmentConstraints();
		}
		else if (!empty($context['album_id']))
		{
			/** @var Album $album */
			$album = $em->find('XFMG:Album', intval($context['album_id']));
			return $album->getAttachmentConstraints();
		}
		else
		{
			// no context will mean we're in the proces of creating an album

			/** @var Album $album */
			$album = $em->create('XFMG:Album');
			return $album->getAttachmentConstraints();
		}
	}

	public function getContainerIdFromContext(array $context)
	{
		return isset($context['media_id']) ? intval($context['media_id']) : null;
	}

	public function getContext(?Entity $entity = null, array $extraContext = [])
	{
		if ($entity instanceof MediaItem)
		{
			$extraContext['media_id'] = $entity->media_id;
		}
		else if ($entity instanceof Album)
		{
			$extraContext['album_id'] = $entity->album_id;
		}
		else if ($entity instanceof Category)
		{
			$extraContext['category_id'] = $entity->category_id;
		}
		else if (!$entity)
		{
			// need nothing
		}
		else
		{
			throw new \InvalidArgumentException("Entity must be media, album or category");
		}

		return $extraContext;
	}
}
