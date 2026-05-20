<?php


namespace PB\SimpleStats\Query\XF;


trait LinkUserTrait
{
	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		switch ($key)
		{
			case 'user_id':
			case 'username':
				return $this->app->router('public')->buildLink(
					$canonical ? 'canonical:members' : 'members', [
						'user_id' => $stat['user_id'],
						'username' => $stat['username'],
					]);
		}

		return null;
	}
}