<?php

namespace XFMG\Import\Data;

use XF\Import\Data\AbstractEmulatedData;

/**
 * @mixin \XFMG\Entity\MediaNote
 */
class MediaNote extends AbstractEmulatedData
{
	public function getImportType()
	{
		return 'xfmg_media_note';
	}

	protected function getEntityShortName()
	{
		return 'XFMG:MediaNote';
	}
}
