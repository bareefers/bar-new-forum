<?php

namespace XFMG\EmbedData;

use GuzzleHttp\Utils;

class YouTube extends BaseData
{
	public function getTempThumbnailPath($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		if (strpos($siteMediaId, ':') !== false)
		{
			$siteMediaId = preg_replace('/(.*)(:\d+)/', '\\1', $siteMediaId);
		}

		if (strpos($siteMediaId, ', list:') !== false)
		{
			$siteMediaId = preg_replace('/^(.*), list:.*$/m', '\\1', $siteMediaId);
		}

		$siteMediaId = rawurlencode($siteMediaId);

		$preferredThumbnail = "https://i.ytimg.com/vi/{$siteMediaId}/maxresdefault.jpg";
		$fallbackThumbnail = "https://i.ytimg.com/vi/{$siteMediaId}/hqdefault.jpg";

		$reader = $this->app->http()->reader();

		$response = $reader->getUntrusted($preferredThumbnail);
		if (!$response || $response->getStatusCode() != 200)
		{
			$response = $reader->getUntrusted($fallbackThumbnail);
			if (!$response || $response->getStatusCode() != 200)
			{
				return null;
			}
			$body = $response->getBody();
		}
		else
		{
			$body = $response->getBody();
		}

		return $this->createTempThumbnailFromBody($body);
	}

	public function getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId)
	{
		$reader = $this->app->http()->reader();

		$apiUrl = 'https://www.youtube.com/oembed?url=' . urlencode($url) . '&format=json';

		$response = $reader->getUntrusted($apiUrl);
		if (!$response || $response->getStatusCode() != 200)
		{
			return parent::getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId);
		}

		$apiResponse = Utils::jsonDecode($response->getBody()->getContents(), true);
		if (!isset($apiResponse['title']))
		{
			return parent::getTitleAndDescription($url, $bbCodeMediaSiteId, $siteMediaId);
		}

		return [
			'title' => $apiResponse['title'],
			'description' => $apiResponse['author_name'] ?? '',
		];
	}
}
