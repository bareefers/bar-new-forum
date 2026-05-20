<?php

namespace BAR\SponsorBanners\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

/**
 * COLUMNS
 * @property int banner_id
 * @property string title
 * @property string image_path
 * @property string remote_url
 * @property string target_url
 * @property string alt_text
 * @property int display_order
 * @property bool is_active
 * @property int created_date
 * @property int source_width
 * @property int source_height
 * @property int display_width
 * @property int display_height
 * @property-read string $public_image_src
 */
class Banner extends Entity
{
	public function getImageUrl(): string
	{
		return $this->getPublicImageSrc();
	}

	public function getPublicImageSrc(): string
	{
		$remote = trim($this->remote_url);
		if ($remote !== '')
		{
			return $remote;
		}

		$path = trim($this->image_path);
		if ($path === '')
		{
			return '';
		}

		return \XF::app()->request()->getFullBasePath() . '/' . ltrim($path, '/');
	}

	protected function _preSave()
	{
		parent::_preSave();

		$this->remote_url = trim($this->remote_url);
		$this->image_path = trim($this->image_path);

		$this->display_width = max(0, min(2000, (int) $this->display_width));
		$this->display_height = max(0, min(2000, (int) $this->display_height));
	}

	public static function getStructure(Structure $structure): Structure
	{
		$structure->table = 'xf_bar_sponsor_banner';
		$structure->shortName = 'BAR\\SponsorBanners:Banner';
		$structure->primaryKey = 'banner_id';
		$structure->getters['public_image_src'] = true;

		$structure->columns = [
			'banner_id' => ['type' => self::UINT, 'autoIncrement' => true],
			'title' => ['type' => self::STR, 'required' => true, 'maxLength' => 100],
			'image_path' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'remote_url' => ['type' => self::STR, 'default' => '', 'maxLength' => 512],
			'target_url' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'alt_text' => ['type' => self::STR, 'default' => '', 'maxLength' => 255],
			'display_order' => ['type' => self::INT, 'default' => 10],
			'is_active' => ['type' => self::BOOL, 'default' => true],
			'created_date' => ['type' => self::UINT, 'default' => \XF::$time],
			'source_width' => ['type' => self::UINT, 'default' => 0],
			'source_height' => ['type' => self::UINT, 'default' => 0],
			'display_width' => ['type' => self::UINT, 'default' => 0],
			'display_height' => ['type' => self::UINT, 'default' => 0],
		];

		return $structure;
	}
}
