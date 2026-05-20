<?php


namespace PB\SimpleStats;


use XF;

class Addon
{
	const ID = 'PB/SimpleStats';
	const NAMESPACE = 'PB\SimpleStats';
	const PREFIX = 'pb_ss';
	const PHRASE_PREFIX = self::PREFIX . '_';
	const TEMPLATE_PREFIX = self::PREFIX . '_';

	public static function phrase($phrase, array $params = [], $allowHtml = true)
	{
		return XF::phrase(self::PHRASE_PREFIX . $phrase, $params, $allowHtml);
	}

	public static function phraseDeferred($name, array $params = [])
	{
		return XF::phraseDeferred(self::PHRASE_PREFIX . $name, $params);
	}

	public static function shortName(string $content = '')
	{
		return self::NAMESPACE . ':' . $content;
	}

	public static function prefix(string $content = '')
	{
		return self::PREFIX . '_' . strtolower($content);
	}
}
