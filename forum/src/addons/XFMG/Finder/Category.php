<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFMG\Entity\Category> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFMG\Entity\Category> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFMG\Entity\Category|null fetchOne(?int $offset = null)
 * @extends Finder<\XFMG\Entity\Category>
 */
class Category extends Finder
{
}
