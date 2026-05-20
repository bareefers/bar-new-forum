<?php

namespace XFMG\XF\Service\User;

use XF\Mvc\Entity\AbstractCollection;
use XFMG\Entity\Album;

use function count;

class ContentChange extends XFCP_ContentChange
{
	/**
	 * @return string[]
	 */
	protected function getSteps(): array
	{
		$steps = parent::getSteps();

		$steps[] = 'stepXfmgAlbumViewPrivacy';
		$steps[] = 'stepXfmgAlbumAddPrivacy';

		return $steps;
	}

	/**
	 * @return int|null
	 */
	protected function stepXfmgAlbumViewPrivacy(
		?int $lastOffset,
		float $maxRunTime
	)
	{
		if (!$this->newUserId)
		{
			return null;
		}

		$start = microtime(true);

		$maxFetch = 500;
		$oldUserId = $this->originalUserId;
		$newUserId = $this->newUserId;

		$albumIds = $this->db()->fetchAllColumn(
			'SELECT album_id
				FROM xf_mg_shared_map_view
				WHERE album_id > ? AND user_id = ?
				ORDER BY album_id
				LIMIT ?',
			[
				$lastOffset !== null ? $lastOffset : 0,
				$newUserId,
				$maxFetch,
			]
		);
		$albumFinder = $this->finder('XFMG:Album')
			->whereIds($albumIds)
			->order('album_id');
		/** @var Album[]|AbstractCollection $albums */
		$albums = $albumFinder->fetch($maxFetch);

		foreach ($albums AS $album)
		{
			$lastOffset = $album->album_id;

			$album->fastUpdate('view_users', array_map(
				function ($userId) use ($oldUserId, $newUserId)
				{
					return ($userId === $oldUserId) ? $newUserId : $userId;
				},
				$album->view_users
			));

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				return $lastOffset;
			}
		}

		if (count($albumIds) === $maxFetch)
		{
			return $lastOffset;
		}

		return null;
	}

	/**
	 * @return int|null
	 */
	protected function stepXfmgAlbumAddPrivacy(
		?int $lastOffset,
		float $maxRunTime
	)
	{
		if (!$this->newUserId)
		{
			return null;
		}

		$start = microtime(true);

		$maxFetch = 500;
		$oldUserId = $this->originalUserId;
		$newUserId = $this->newUserId;

		$albumIds = $this->db()->fetchAllColumn(
			'SELECT album_id
				FROM xf_mg_shared_map_add
				WHERE album_id > ? AND user_id = ?
				ORDER BY album_id
				LIMIT ?',
			[
				$lastOffset !== null ? $lastOffset : 0,
				$newUserId,
				$maxFetch,
			]
		);
		$albumFinder = $this->finder('XFMG:Album')
			->whereIds($albumIds)
			->order('album_id');
		/** @var Album[]|AbstractCollection $albums */
		$albums = $albumFinder->fetch($maxFetch);

		foreach ($albums AS $album)
		{
			$lastOffset = $album->album_id;

			$album->fastUpdate('add_users', array_map(
				function ($userId) use ($oldUserId, $newUserId)
				{
					return ($userId === $oldUserId) ? $newUserId : $userId;
				},
				$album->add_users
			));

			if ($maxRunTime && microtime(true) - $start > $maxRunTime)
			{
				return $lastOffset;
			}
		}

		if (count($albumIds) === $maxFetch)
		{
			return $lastOffset;
		}

		return null;
	}
}
