<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFMG\Entity\CategoryField> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFMG\Entity\CategoryField> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFMG\Entity\CategoryField|null fetchOne(?int $offset = null)
 * @extends Finder<\XFMG\Entity\CategoryField>
 */
class CategoryField extends Finder
{
}
