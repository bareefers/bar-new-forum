<?php

namespace BAR\SponsorBanners\Service;

class RemoteImageProbe
{
	public static function getWarning(\XF\App $app, string $url): ?\XF\Phrase
	{
		$url = trim($url);
		if ($url === '')
		{
			return null;
		}

		if (!preg_match('#^https?://#i', $url))
		{
			return \XF::phrase('bar_sb_remote_scheme_invalid');
		}

		$tmp = @tempnam(sys_get_temp_dir(), 'sbr');
		if ($tmp === false)
		{
			return null;
		}

		$reader = $app->http()->reader();
		$error = null;
		$response = $reader->getUntrusted($url, ['time' => 8, 'bytes' => 65536], $tmp, [], $error);

		try
		{
			if ($response === null)
			{
				if ($error)
				{
					return \XF::phrase('bar_sb_remote_unreachable', ['message' => $error]);
				}

				return \XF::phrase('bar_sb_remote_unreachable_generic');
			}

			$code = $response->getStatusCode();
			if ($code === 403 || $code === 401)
			{
				return \XF::phrase('bar_sb_remote_blocked');
			}
			if ($code < 200 || $code >= 300)
			{
				return \XF::phrase('bar_sb_remote_http', ['code' => $code]);
			}

			$ct = $response->getHeaderLine('Content-Type');
			$snippet = self::readFileHead($tmp, 16);
			$looksImage = self::sniffImage($snippet);
			$headerImage = (bool) preg_match('#\bimage/[\w.+-]+#i', $ct);

			if (!$headerImage && !$looksImage)
			{
				return \XF::phrase('bar_sb_remote_not_image');
			}
			if (!$headerImage && $looksImage)
			{
				return \XF::phrase('bar_sb_remote_bad_content_type');
			}

			return null;
		}
		finally
		{
			@unlink($tmp);
		}
	}

	private static function readFileHead(string $path, int $bytes): string
	{
		$h = @fopen($path, 'rb');
		if (!$h)
		{
			return '';
		}
		$data = fread($h, $bytes);
		fclose($h);

		return is_string($data) ? $data : '';
	}

	private static function sniffImage(string $head): bool
	{
		if ($head === '')
		{
			return false;
		}

		if (strncmp($head, "\xFF\xD8\xFF", 3) === 0)
		{
			return true;
		}
		if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) === 0)
		{
			return true;
		}
		if (strncmp($head, 'GIF8', 4) === 0)
		{
			return true;
		}
		if (strlen($head) >= 12
			&& strncmp($head, 'RIFF', 4) === 0
			&& strncmp(substr($head, 8, 4), 'WEBP', 4) === 0)
		{
			return true;
		}

		return false;
	}
}
