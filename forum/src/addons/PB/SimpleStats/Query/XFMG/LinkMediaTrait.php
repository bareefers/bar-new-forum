<?php


namespace PB\SimpleStats\Query\XFMG;


trait LinkMediaTrait
{
	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		switch ($key)
		{
			case 'media_id':
			case 'title':
				return $this->app->router('public')
					->buildLink($canonical ? 'canonical:media' : 'media', ['media_id' => $stat['media_id']]);
		}

		return null;
	}
}