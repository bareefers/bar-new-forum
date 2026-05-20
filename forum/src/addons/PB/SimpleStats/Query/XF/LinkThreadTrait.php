<?php


namespace PB\SimpleStats\Query\XF;


trait LinkThreadTrait
{
	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		switch ($key)
		{
			case 'thread_id':
			case 'title':
				return $this->app->router('public')->buildLink(
					$canonical ? 'canonical:threads/' : 'threads', ['thread_id' => $stat['thread_id']]
				);
		}

		return null;
	}
}
