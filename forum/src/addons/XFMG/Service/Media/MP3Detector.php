<?php

namespace XFMG\Service\Media;

use XF\App;
use XF\PrintableException;
use XF\Service\AbstractService;

class MP3Detector extends AbstractService
{
	protected $path;

	public function __construct(App $app, $filePath)
	{
		parent::__construct($app);
		$this->path = $filePath;
	}

	protected function verifyFile(&$error = null)
	{
		if (!file_exists($this->path) || !is_readable($this->path))
		{
			$error = 'MP3 file does not exist or cannot be read.';
			return false;
		}

		return true;
	}

	public function isValidMP3()
	{
		if (!$this->verifyFile($error))
		{
			throw new PrintableException($error);
		}

		$fp = @fopen($this->path, 'rb');
		if (!$fp)
		{
			return false;
		}

		// Fetch the first bytes of the file, overfetching and trimming is necessary as
		// some files have padding. Supports MP3s with or without an ID3v2 container.
		$preamble = strtoupper(bin2hex(ltrim(fread($fp, 256000))));
		fclose($fp);

		$first8 = substr($preamble, 0, 8);

		return (
			strpos($first8, '494433') === 0 // indicates an ID3v2 container
			|| strpos($first8, 'FFFB') === 0
			|| strpos($first8, 'FFF3') === 0
			|| strpos($first8, 'FFF2') === 0
		);
	}
}
