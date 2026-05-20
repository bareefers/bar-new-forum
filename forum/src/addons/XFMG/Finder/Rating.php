<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFMG\Entity\Rating> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFMG\Entity\Rating> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFMG\Entity\Rating|null fetchOne(?int $offset = null)
 * @extends Finder<\XFMG\Entity\Rating>
 */
class Rating extends Finder
{
}
