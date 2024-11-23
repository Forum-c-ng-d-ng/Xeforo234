<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\UserAlert> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\UserAlert> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\UserAlert|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\UserAlert>
 */
class UserAlertFinder extends Finder
{
}