<?php

namespace XFMG\Import\Importer;

use XF\Import\Importer\AbstractAddOnImporter;
use XF\Job\PermissionRebuild;
use XF\Util\Arr;
use XFMG\Job\Album;
use XFMG\Job\AlbumRatingRebuild;
use XFMG\Job\AlbumThumb;
use XFMG\Job\Category;
use XFMG\Job\MediaItem;
use XFMG\Job\MediaRatingRebuild;
use XFMG\Job\UserCount;
use XFMG\Job\UserMediaQuota;

use function in_array;

abstract class AbstractMGImporter extends AbstractAddOnImporter
{
	protected function getMediaTypeAndFilePathFromExtension($extension)
	{
		[$imageExtensions, $videoExtensions, $audioExtensions] = $this->getAllowedUploadExtensions();

		$mediaType = null;
		$filePath = null;

		if (in_array($extension, $imageExtensions, true))
		{
			$mediaType = 'image';
		}
		else if (in_array($extension, $videoExtensions))
		{
			$mediaType = 'video';
			$filePath = 'data://xfmg/video/%FLOOR%/%DATA_ID%-%HASH%.mp4';
		}
		else if (in_array($extension, $audioExtensions))
		{
			$mediaType = 'audio';
			$filePath = 'data://xfmg/audio/%FLOOR%/%DATA_ID%-%HASH%.mp3';
		}

		return [
			$mediaType,
			$filePath,
		];
	}

	protected function getAllowedUploadExtensions()
	{
		$options = $this->app->options();

		$imageExtensions = Arr::stringToArray($options->xfmgImageExtensions);
		$videoExtensions = Arr::stringToArray($options->xfmgVideoExtensions);
		$audioExtensions = Arr::stringToArray($options->xfmgAudioExtensions);

		return [
			$imageExtensions,
			$videoExtensions,
			$audioExtensions,
		];
	}

	protected function isForumType($importType)
	{
		return (strpos($importType, 'xfmg_') !== 0);
	}

	public function canRetainIds()
	{
		$db = $this->app->db();

		$maxMediaId = $db->fetchOne("SELECT MAX(media_id) FROM xf_mg_media_item");
		if ($maxMediaId)
		{
			return false;
		}

		$maxAlbumId = $db->fetchOne("SELECT MAX(album_id) FROM xf_mg_album");
		if ($maxAlbumId)
		{
			return false;
		}

		$maxCategoryId = $db->fetchOne("SELECT MAX(category_id) FROM xf_mg_category");
		if ($maxCategoryId > 1)
		{
			return false;
		}

		$maxCommentId = $db->fetchOne("SELECT MAX(comment_id) FROM xf_mg_comment");
		if ($maxCommentId)
		{
			return false;
		}

		return true;
	}

	public function resetDataForRetainIds()
	{
		// category 1 is created by default in the installer so we need to remove that if retaining IDs
		$category = $this->em()->find(\XFMG\Entity\Category::class, 1);
		if ($category)
		{
			$category->delete();
		}
	}

	public function getFinalizeJobs(array $stepsRun)
	{
		$jobs = [];

		$jobs[] = Category::class;
		$jobs[] = Album::class;
		$jobs[] = MediaItem::class;
		$jobs[] = AlbumRatingRebuild::class;
		$jobs[] = MediaRatingRebuild::class;
		$jobs[] = UserCount::class;
		$jobs[] = UserMediaQuota::class;
		$jobs[] = AlbumThumb::class;
		$jobs[] = PermissionRebuild::class;

		return $jobs;
	}
}
