<?php

namespace XFMG\Service\Media;

use XF\App;
use XF\Entity\Attachment;
use XF\Entity\User;
use XF\Repository\BbCodeMediaSite;
use XF\Service\AbstractService;
use XF\Service\ValidateAndSavableTrait;
use XF\Util\File;
use XF\Validator\Url;
use XFMG\Entity\MediaTemp;
use XFMG\Repository\Media;
use XFMG\VideoInfo\Preparer;

class TempCreator extends AbstractService
{
	use ValidateAndSavableTrait;

	/**
	 * @var MediaTemp
	 */
	protected $mediaTemp;

	/**
	 * @var Attachment
	 */
	protected $attachment;

	protected $mediaSiteUrl;
	protected $mediaSiteId;
	protected $siteMediaId;

	public function __construct(App $app)
	{
		parent::__construct($app);
		$this->setMediaTemp();
	}

	protected function setMediaTemp()
	{
		$this->mediaTemp = $this->em()->create('XFMG:MediaTemp');

		$this->setUser(\XF::visitor());
	}

	public function setUser(User $user)
	{
		$this->mediaTemp->user_id = $user->user_id;
	}

	public function setAttachment(Attachment $attachment)
	{
		$this->attachment = $attachment;

		/** @var Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');
		$this->mediaTemp->media_type = $mediaRepo->getMediaTypeFromAttachment($attachment);
	}

	public function setMediaSite($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$this->mediaSiteUrl = $url;
		$this->mediaSiteId = $bbCodeMediaSiteId;
		$this->siteMediaId = $siteMediaId;

		$this->mediaTemp->media_type = 'embed';
	}

	public function validateAndSetEmbedUrl($url, &$error = null)
	{
		/** @var Url $validator */
		$validator = $this->app->validator('Url');
		$url = $validator->coerceValue($url);

		if (!$validator->isValid($url) || !$this->app->http()->reader()->isRequestableUntrustedUrl($url))
		{
			$error = \XF::phraseDeferred('xfmg_pasted_text_does_not_appear_to_be_valid_url');
			return null;
		}

		/** @var BbCodeMediaSite $bbCodeMediaSiteRepo */
		$bbCodeMediaSiteRepo = $this->repository('XF:BbCodeMediaSite');

		$sites = $bbCodeMediaSiteRepo->findActiveMediaSites()->fetch();
		$match = $bbCodeMediaSiteRepo->urlMatchesMediaSiteList($url, $sites);

		if (!$match)
		{
			$error = \XF::phraseDeferred('specified_url_cannot_be_embedded_as_media');
			return null;
		}

		$this->setMediaSite($url, $match['media_site_id'], $match['media_id']);

		return '[MEDIA=' . $match['media_site_id'] . ']' . $match['media_id'] . '[/MEDIA]';
	}

	public function getMediaSiteId()
	{
		return $this->mediaSiteId;
	}

	public function getMediaSiteUrl()
	{
		return $this->mediaSiteUrl;
	}

	public function getMediaSiteMediaId()
	{
		return $this->siteMediaId;
	}

	public function setExif(array $exif)
	{
		$this->mediaTemp->exif_data = $exif;
	}

	public function finalSetup()
	{
		$mediaTemp = $this->mediaTemp;
		$mediaTemp->temp_media_date = time();
	}

	protected function _validate()
	{
		$this->finalSetup();

		$mediaTemp = $this->mediaTemp;
		$mediaTemp->preSave();
		$errors = $mediaTemp->getErrors();

		return $errors;
	}

	protected function _save()
	{
		$mediaTemp = $this->mediaTemp;
		$mediaTemp->save();

		$abstractedThumbnailPath = $mediaTemp->getAbstractedTempThumbnailPath();
		$abstractedPosterPath = $mediaTemp->getAbstractedTempPosterPath();

		$thumbnailDate = 0;
		$posterDate = 0;

		/** @var ThumbnailGenerator $thumbnailGenerator */
		$thumbnailGenerator = $this->service('XFMG:Media\ThumbnailGenerator');

		$updates = [];

		if ($this->attachment)
		{
			$attachment = $this->attachment;

			$updates += [
				'title' => $attachment->Data->filename,
				'attachment_id' => $attachment->attachment_id,
			];

			if ($thumbnailGenerator->createTempThumbnailFromAttachment($attachment, $abstractedThumbnailPath, $mediaTemp->media_type))
			{
				$thumbnailDate = time();
			}

			if ($thumbnailGenerator->createTempPosterFromAttachment($attachment, $abstractedPosterPath, $mediaTemp->media_type))
			{
				$posterDate = time();
			}

			$abstractedPath = null;

			$ffmpegOptions = $this->app->options()->xfmgFfmpeg;

			if ($mediaTemp->media_type == 'video')
			{
				$abstractedPath = $attachment->Data->getAbstractedDataPath();
				$tempPath = File::copyAbstractedPathToTempFile($abstractedPath);

				if ($ffmpegOptions['forceTranscode'])
				{
					$updates['requires_transcoding'] = true;
				}
				else
				{
					$videoInfo = new Preparer($tempPath);
					$result = $videoInfo->getInfo();

					$updates['requires_transcoding'] = (!$result->isValid() || $result->requiresTranscoding());
				}
			}
			else if ($mediaTemp->media_type == 'audio')
			{
				$abstractedPath = $attachment->Data->getAbstractedDataPath();
				$tempPath = File::copyAbstractedPathToTempFile($abstractedPath);

				/** @var MP3Detector $MP3Detector */
				$MP3Detector = $this->app->service('XFMG:Media\MP3Detector', $tempPath);

				$updates['requires_transcoding'] = ($MP3Detector->isValidMP3() ? false : true);
			}
		}
		else if ($this->mediaSiteId)
		{
			/** @var Media $mediaRepo */
			$mediaRepo = $this->repository('XFMG:Media');

			$embedDataHandler = $mediaRepo->createEmbedDataHandler($this->mediaSiteId);
			$tempFile = $embedDataHandler->getTempThumbnailPath($this->mediaSiteUrl, $this->mediaSiteId, $this->siteMediaId);

			if ($tempFile && $thumbnailGenerator->getTempThumbnailFromImage($tempFile, $abstractedThumbnailPath))
			{
				$thumbnailDate = time();
			}

			$titleData = $embedDataHandler->getTitleAndDescription($this->mediaSiteUrl, $this->mediaSiteId, $this->siteMediaId);

			$updates += [
				'title' => $titleData['title'] ?? '',
				'description' => $titleData['description'] ?? '',
			];
		}

		if ($thumbnailDate)
		{
			$updates['thumbnail_date'] = $thumbnailDate;
		}
		if ($posterDate)
		{
			$updates['poster_date'] = $posterDate;
		}

		if ($updates)
		{
			$mediaTemp->fastUpdate($updates);
		}

		return $mediaTemp;
	}
}
