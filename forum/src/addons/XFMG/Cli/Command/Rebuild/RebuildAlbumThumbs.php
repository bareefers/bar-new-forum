<?php

namespace XFMG\Cli\Command\Rebuild;

use XF\Cli\Command\Rebuild\AbstractRebuildCommand;

class RebuildAlbumThumbs extends AbstractRebuildCommand
{
	protected function getRebuildName()
	{
		return 'xfmg-album-thumbs';
	}

	protected function getRebuildDescription()
	{
		return 'Rebuilds album thumbnails.';
	}

	protected function getRebuildClass()
	{
		return 'XFMG:AlbumThumb';
	}
}
