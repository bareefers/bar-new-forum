<?php

namespace XFMG\Exif;

use XFMG\Entity\MediaItem;

use function array_key_exists, intval, strval;

class Formatter implements \ArrayAccess
{
	/**
	 * @var MediaItem
	 */
	protected $mediaItem;

	protected $exifData;
	protected $valueCache = [];

	public function __construct(MediaItem $mediaItem, array $exifData)
	{
		$this->prepareExifData($exifData);
		$this->mediaItem = $mediaItem;
	}

	protected function prepareExifData(array $exifData)
	{
		$prepared = [];
		foreach ($exifData AS $data)
		{
			$prepared = array_merge($prepared, $data);
		}
		$this->exifData = $prepared;
	}

	public function offsetExists($offset): bool
	{
		return (isset($this->exifData[$offset]));
	}

	#[\ReturnTypeWillChange]
	public function offsetGet($offset)
	{
		if (!array_key_exists($offset, $this->valueCache))
		{
			$this->valueCache[$offset] = $this->getValue($offset);
		}

		return $this->valueCache[$offset];
	}

	protected function getValue($offset)
	{
		switch ($offset)
		{
			case 'device':
				if (!$this->Make || !$this->Model)
				{
					return null;
				}
				return $this->Make . ' ' . $this->Model;

			case 'aperture':
				$f = $this->divideValue($this->FNumber);
				if (!$f)
				{
					return null;
				}
				return \XF::escapeString('ƒ/' . $f);

			case 'focal':
				$fLength = $this->divideValue($this->FocalLength);
				if (!$fLength)
				{
					return null;
				}
				return \XF::escapeString(\XF::language()->numberFormat($fLength, 1) . ' mm');

			case 'exposure':
				$exposureTime = $this->ExposureTime;
				if (!$exposureTime)
				{
					return null;
				}

				if (preg_match('#1/(\d+)#', $exposureTime, $matches))
				{
					$output = "1/$matches[1]";
				}
				else
				{
					$output = $this->divideValue($exposureTime);
					if ($output < 1)
					{
						$output = $this->simplifyValue($exposureTime);
					}
				}

				return \XF::phrase('xfmg_x_seconds', ['count' => $output]);

			case 'iso':
				return intval($this->ISOSpeedRatings);

			case 'flash':
				$flash = $this->Flash;
				switch ($flash)
				{
					case 8:
					case 9:
					case 16:
					case 24:
					case 25:
						return \XF::phrase('xfmg_exif.flash_' . strval($flash));
				}

				return \XF::phrase('xfmg_exif.flash_' . strval($flash))->render('html', ['nameOnInvalid' => false]);

			case 'date_taken':
				$dateTime = $this->DateTimeOriginal;
				if (!$dateTime)
				{
					$dateTime = $this->DateTime;
				}

				if (!$dateTime)
				{
					return null;
				}

				try
				{
					$date = new \DateTime($dateTime);
				}
				catch (\Exception $e)
				{
					return null;
				}
				return \XF::language()->date($date, 'D, d F Y g:i A');

			case 'file_size':
				if (!$this->FileSize)
				{
					return null;
				}
				return \XF::language()->fileSizeFormat($this->FileSize);

			case 'dimensions':
				if ($this->Width && $this->Height)
				{
					return $this->Width . 'px x ' . $this->Height . 'px';
				}
				break;
		}

		return $this->offsetExists($offset) ? \XF::escapeString($this->exifData[$offset]) : null;
	}

	protected function divideValue($value)
	{
		preg_match('#(\d+)/(\d+)#', strval($value), $matches);
		if (!$matches || $matches[1] == 0 || $matches[2] == 0)
		{
			return null;
		}
		return strval($matches[1] / $matches[2]);
	}

	protected function simplifyValue($value)
	{
		preg_match('#(\d+)/(\d+)#', strval($value), $matches);
		if (!$matches || $matches[1] == 0 || $matches[2] == 0)
		{
			return null;
		}
		return implode('/', [1, round($matches[2] / $matches[1])]);
	}

	public function offsetSet($offset, $value): void
	{
		$this->exifData[$offset] = $value;
	}

	public function offsetUnset($offset): void
	{
		unset($this->exifData[$offset]);
	}

	public function __get($name)
	{
		return $this->offsetGet($name);
	}

	public function __set($name, $value)
	{
		$this->offsetSet($name, $value);
	}

	public function toArray()
	{
		return $this->exifData;
	}
}
