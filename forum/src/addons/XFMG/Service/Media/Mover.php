<?php

namespace XFMG\Service\Media;

use XF\App;
use XF\Mvc\Entity\AbstractCollection;
use XF\Repository\ActivityLogRepository;
use XF\Service\AbstractService;
use XF\Service\FeaturedContent\Creator;
use XF\Service\FeaturedContent\Deleter;
use XFMG\Entity\Album;
use XFMG\Entity\Category;
use XFMG\Entity\MediaItem;
use XFMG\Repository\Media;

use function is_array;

class Mover extends AbstractService
{
	/**
	 * @var Category|null
	 */
	protected $targetCategory;

	/**
	 * @var Album|null
	 */
	protected $targetAlbum;

	/**
	 * @var MediaItem[]
	 */
	protected $sourceMedia;

	/**
	 * @var Album[]
	 */
	protected $sourceAlbums;

	/**
	 * @var Category[]
	 */
	protected $sourceCategories;

	protected $alert = false;
	protected $alertReason = '';

	protected $log = true;

	public function __construct(App $app, ?Category $targetCategory = null, ?Album $targetAlbum = null)
	{
		parent::__construct($app);

		$this->targetCategory = $targetCategory;
		$this->targetAlbum = $targetAlbum;
	}

	public function setSendAlert($alert, $reason = null)
	{
		$this->alert = (bool) $alert;
		if ($reason !== null)
		{
			$this->alertReason = $reason;
		}
	}

	public function move($sourceMediaRaw)
	{
		if ($sourceMediaRaw instanceof AbstractCollection)
		{
			$sourceMediaRaw = $sourceMediaRaw->toArray();
		}
		else if ($sourceMediaRaw instanceof MediaItem)
		{
			$sourceMediaRaw = [$sourceMediaRaw];
		}
		else if (!is_array($sourceMediaRaw))
		{
			throw new \InvalidArgumentException('Media items must be provided as collection, array or entity');
		}

		if (!$sourceMediaRaw)
		{
			return false;
		}

		$db = $this->db();

		/** @var MediaItem[] $sourceMedia */
		/** @var MediaItem[] $sourceMediaRaw */
		$sourceMedia = [];

		/** @var Album[] $sourceAlbums */
		/** @var Category[] $sourceCategories */
		$sourceAlbums = [];
		$sourceCategories = [];

		foreach ($sourceMediaRaw AS $sourceMediaItem)
		{
			$sourceMediaItem->setOption('log_moderator', false);
			$sourceMedia[$sourceMediaItem->media_id] = $sourceMediaItem;

			if ($sourceMediaItem->Album)
			{
				$sourceAlbums[] = $sourceMediaItem->Album;
			}
			if ($sourceMediaItem->Category)
			{
				$sourceCategories[] = $sourceMediaItem->Category;
			}
		}

		$this->sourceMedia = $sourceMedia;
		$this->sourceAlbums = $sourceAlbums;
		$this->sourceCategories = $sourceCategories;

		$db->beginTransaction();

		$this->moveDataToTarget();
		$this->updateTargetData();
		$this->updateSourceData();

		if ($this->alert)
		{
			$this->sendAlert();
		}

		$this->finalActions();

		$db->commit();

		return true;
	}

	protected function moveDataToTarget()
	{
		$db = $this->db();
		$sourceMediaIds = array_keys($this->sourceMedia);
		$sourceIdsQuoted = $db->quote($sourceMediaIds);

		$targetCategory = $this->targetCategory;
		$targetAlbum = $this->targetAlbum;

		$db->update('xf_mg_media_item', [
			'category_id' => $targetCategory ? $targetCategory->category_id : 0,
			'album_id' => $targetAlbum ? $targetAlbum->album_id : 0,
		], "media_id IN ($sourceIdsQuoted)");

		foreach ($this->sourceMedia AS $mediaItem)
		{
			$this->updateActivityLog($mediaItem);
			$this->updateAutoFeatures($mediaItem);
		}
	}

	protected function updateActivityLog(MediaItem $mediaItem): void
	{
		$activityLogRepo = $this->repository(ActivityLogRepository::class);
		$activityLogRepo->updateContainerId($mediaItem, true);
	}

	protected function updateAutoFeatures(MediaItem $mediaItem): void
	{
		$category = $this->targetCategory;

		if ($mediaItem->isFeatured() && $mediaItem->Feature)
		{
			$feature = $mediaItem->Feature;
			if (
				$feature->auto_featured
				&& (!$category || !$category->auto_feature)
			)
			{
				/** @var Deleter $deleter */
				$deleter = $this->service(
					'XF:FeaturedContent\Deleter',
					$feature
				);
				$deleter->delete();
			}
			else
			{
				$feature->fastUpdate(
					'content_container_id',
					$category ? $category->category_id : 0
				);
			}
		}
		else if ($category && $category->auto_feature)
		{
			/** @var Creator $creator */
			$creator = $this->service(
				'XF:FeaturedContent\Creator',
				$mediaItem
			);
			$creator->setAutoFeatured();
			$creator->save();
		}
	}

	protected function updateTargetData()
	{
		$targetCategory = $this->targetCategory;
		$targetAlbum = $this->targetAlbum;

		if ($targetAlbum)
		{
			$targetAlbum->rebuildCounters();
			$targetAlbum->rebuildAlbumThumbnail();
			$targetAlbum->save();
		}
		if ($targetCategory)
		{
			$targetCategory->rebuildCounters();
			$targetCategory->save();
		}
	}

	protected function updateSourceData()
	{
		foreach ($this->sourceAlbums AS $album)
		{
			$album->rebuildCounters();
			$album->rebuildAlbumThumbnail();
			$album->save();
		}
		foreach ($this->sourceCategories AS $category)
		{
			$category->rebuildCounters();
			$category->save();
		}
	}

	protected function sendAlert()
	{
		// only need to send one alert so send for album OR category (not both)
		$targetCategory = $this->targetCategory;
		$targetAlbum = $this->targetAlbum;

		/** @var Media $mediaRepo */
		$mediaRepo = $this->repository('XFMG:Media');

		if ($targetAlbum)
		{
			$alertExtras = [
				'targetTitle' => $targetAlbum->title,
				'targetLink' => $this->app->router('public')->buildLink('nopath:media/albums', $targetAlbum),
			];

			foreach ($this->sourceMedia AS $sourceMediaItem)
			{
				if ($targetAlbum->album_state == 'visible'
					&& $targetAlbum->canView()
					&& $sourceMediaItem->media_state == 'visible'
					&& $sourceMediaItem->user_id != \XF::visitor()->user_id
				)
				{
					$mediaRepo->sendModeratorActionAlert($sourceMediaItem, 'move', $this->alertReason, $alertExtras);
				}
			}
		}
		else // category
		{
			$alertExtras = [
				'targetTitle' => $targetCategory->title,
				'targetLink' => $this->app->router('public')->buildLink('nopath:media/categories', $targetCategory),
			];

			foreach ($this->sourceMedia AS $sourceMediaItem)
			{
				if ($targetCategory->canView()
					&& $sourceMediaItem->media_state == 'visible'
					&& $sourceMediaItem->user_id != \XF::visitor()->user_id
				)
				{
					$mediaRepo->sendModeratorActionAlert($sourceMediaItem, 'move', $this->alertReason, $alertExtras);
				}
			}
		}
	}

	protected function finalActions()
	{
		$targetAlbum = $this->targetAlbum;

		$mediaIds = array_keys($this->sourceMedia);

		if ($mediaIds)
		{
			$this->app->jobManager()->enqueue('XF:SearchIndex', [
				'content_type' => 'xfmg_media',
				'content_ids' => $mediaIds,
			]);
		}

		if ($this->log)
		{
			if ($targetAlbum)
			{
				$this->app->logger()->logModeratorAction(
					'xfmg_album',
					$targetAlbum,
					'media_move_target',
					['ids' => implode(', ', $mediaIds)]
				);

				foreach ($this->sourceAlbums AS $sourceAlbum)
				{
					$this->app->logger()->logModeratorAction('xfmg_album', $sourceAlbum, 'media_move_source', [
						'title' => $targetAlbum->title,
					]);
				}
			}
		}
	}
}
