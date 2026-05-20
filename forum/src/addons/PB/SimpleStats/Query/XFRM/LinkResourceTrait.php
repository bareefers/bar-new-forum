<?php


namespace PB\SimpleStats\Query\XFRM;


trait LinkResourceTrait
{
	protected function assertUrl($stat, $key, $value, bool $canonical = false)
	{
		switch ($key)
		{
			case 'resource_id':
			case 'title':
				return $this->app->router('public')
					->buildLink($canonical ? 'canonical:resources' : 'resources', ['resource_id' => $stat['resource_id']]);
		}

		return null;
	}
}
