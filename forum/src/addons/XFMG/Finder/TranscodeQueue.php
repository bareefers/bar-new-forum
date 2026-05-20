<?php

namespace XFMG\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XFMG\Entity\TranscodeQueue> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XFMG\Entity\TranscodeQueue> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XFMG\Entity\TranscodeQueue|null fetchOne(?int $offset = null)
 * @extends Finder<\XFMG\Entity\TranscodeQueue>
 */
class TranscodeQueue extends Finder
{
}
