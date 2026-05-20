<?php


namespace PB\SimpleStats\Repository;


use PB\SimpleStats\Query\AbstractHandler;
use PB\SimpleStats\Addon;
use XF;
use XF\Mvc\Entity\Repository;

class Query extends Repository
{
	/**
	 * @return array
	 * 		['addon_id']					string
	 * 			['content_type']			string
	 * 				['handler_class']		string if unset automatically parses camelcased "PB\SimpleStats:{addon_id}\{content_type}"
	 * 				['title'] 				string if unset automatically parses lowercased "pb_ss_{addon_id}.content_type"
	 */
	public function getSupportedQueryTypes(): array
	{
		return [
			'XF' => [
				'user_count' => [],
				'user_active_count' => [],
				'user_thread_count' => [],
				'user_post_count' => [],
				'user_reaction_given_count' => [],
				'user_reaction_received_count' => [],
				'user_bookmark_count' => [],
				'user_attachment_size' => [],
				'user_reported_count' => [],
				'user_report_assigned_count' => [],
				'user_warning_count' => [],
				'user_warning_given_count' => [],
				'thread_count' => [],
				'thread_reply_count' => [],
				'thread_view_count' => [],
				'thread_watch_count' => [],
				'post_count' => [],
				'post_reaction_count' => []
			],
			'XFRM' => [
				'xfrm.resource_update_count' => [],
				'xfrm.user_resource_count' => [],
				'xfrm.user_rating_count' => [],
			],
			'XFMG' => [
				'xfmg.user_media_count' => [],
				'xfmg.media_view_count' => [],
				'xfmg.media_reaction_count' => [],
				'xfmg.media_comment_count' => [],
			],
		];
	}

	public function getAvailableQueryTypes($withData = false, $groupByAddon = false): array
	{
		$arr = [];
		$addons = $this->getSupportedQueryTypes();
		$addOnCache = $this->app()->container('addon.cache');

		$defaultQueryData = function ($queryType)
		{
			$parts = explode('.', $queryType);

			if (isset($parts[1]))
			{
				$addonId = strtoupper($parts[0]);
				$handlerClass = ucfirst(\XF\Util\Php::camelCase($parts[1]));
			}
			else
			{
				$addonId = 'XF';
				$handlerClass = ucfirst(\XF\Util\Php::camelCase($parts[0]));
			}

			return [
				'title' => Addon::phrase($queryType),
				'handler_class' => Addon::shortName(":$addonId\\$handlerClass")
			];
		};

		foreach ($addons as $addonId => $queryTypes)
		{
			if (!isset($addOnCache[$addonId]))
			{
				continue;
			}

			foreach ($queryTypes as $queryType => $queryTypeData)
			{
				if ($withData)
				{
					if ($groupByAddon)
					{
						$arr[$addonId][$queryType] = $queryTypeData ?: $defaultQueryData($queryType);
					}
					else
					{
						$arr[$queryType] = $queryTypeData ?: $defaultQueryData($queryType);
					}
				}
				else
				{
					if ($groupByAddon)
					{
						$arr[$addonId] = $queryType;
					}
					else
					{
						$arr[] = $queryType;
					}
				}
			}
		}

		return $arr;
	}

	public function getQueryTypeData($type)
	{
		$contentTypes = $this->getAvailableQueryTypes(true);
		return $contentTypes[$type] ?? [];
	}

	public function isValidQueryType($type): bool
	{
		$availableContentTypes = $this->getAvailableQueryTypes();
		return in_array($type, $availableContentTypes);
	}

	/**
	 * @param string $type
	 * @return AbstractHandler|null
	 * @throws \Exception
	 */
	public function getHandlerForQueryType(string $type)
	{
		$contentData = $this->getQueryTypeData($type);
		$handlerClass = $contentData['handler_class'] ?? null;

		if (!$handlerClass)
		{
			return null;
		}

		$class = XF::stringToClass($handlerClass, '%s\Query\%s');
		if (!class_exists($class))
		{
			return null;
		}
		$class = XF::extendClass($class);

		return new $class($this->app());
	}
}
