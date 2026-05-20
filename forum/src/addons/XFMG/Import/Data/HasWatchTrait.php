<?php

namespace XFMG\Import\Data;

use XFMG\Import\DataHelper\Watch;

trait HasWatchTrait
{
	protected $watchers = [];

	public function addWatcher($userId, array $params)
	{
		$this->watchers[$userId] = $params;
	}

	protected function insertWatchers($newId)
	{
		if ($this->watchers)
		{
			/** @var Watch $watchHelper */
			$watchHelper = $this->dataManager->helper(Watch::class);
			$watchHelper->importWatchBulk($newId, $this->getImportType(), $this->watchers);
		}
	}
}
