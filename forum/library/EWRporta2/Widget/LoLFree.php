<?php

class EWRporta2_Widget_LoLFree extends XenForo_Model
{
	public function getCachedData($widget, &$options)
	{
		try
		{
			$client = new Zend_Http_Client('https://' . $options['lolfree_region'] . '.api.pvp.net/api/lol/' .
				$options['lolfree_region'] . '/v1.2/champion?freeToPlay=true&api_key='.$options['lolfree_apikey']);
			$feed = $client->request()->getBody();
			$json = json_decode($feed, true);
		
			if (!empty($json['status'])) { return 'killWidget'; }
			
			$champions = array();
			foreach ($json['champions'] AS $champ)
			{
				$champions[$champ['id']] = $champ;
			}
			
			$client = new Zend_Http_Client('https://global.api.pvp.net/api/lol/static-data/' .
				$options['lolfree_region'] . '/v1.2/champion?champData=image&api_key='.$options['lolfree_apikey']);
			$feed = $client->request()->getBody();
			$json = json_decode($feed, true);
		
			if (!empty($json['status'])) { return 'killWidget'; }
			
			foreach ($json['data'] AS $champ)
			{
				if (!empty($champions[$champ['id']]))
				{
					$champions[$champ['id']]['name'] = $champ['name'];
					$champions[$champ['id']]['image'] = $champ['image']['full'];
				}
			}
		}
		catch (Exception $e)
		{
			return 'killWidget';
		}
		
		return $champions;
	}
}