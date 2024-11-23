<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\Category> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\Category> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\Category|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\Category>
 */
class CategoryFinder extends Finder
{
}
