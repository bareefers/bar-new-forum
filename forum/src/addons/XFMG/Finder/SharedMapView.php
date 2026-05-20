<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFMG\Entity\SharedMapView> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFMG\Entity\SharedMapView> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFMG\Entity\SharedMapView|null fetchOne(?int $offset = null)
 * @extends Finder<\XFMG\Entity\SharedMapView>
 */
class SharedMapView extends Finder
{
}
