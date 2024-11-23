<?php

namespace XF\Finder;

use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Finder;

/**
 * @method AbstractCollection<\XF\Entity\ReactionContent> fetch(?int $limit = null, ?int $offset = null)
 * @method AbstractCollection<\XF\Entity\ReactionContent> fetchDeferred(?int $limit = null, ?int $offset = null)
 * @method \XF\Entity\ReactionContent|null fetchOne(?int $offset = null)
 * @extends Finder<\XF\Entity\ReactionContent>
 */
class ReactionContentFinder extends Finder
{
}