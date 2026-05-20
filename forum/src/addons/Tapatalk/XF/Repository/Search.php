<?php

namespace Tapatalk\XF\Repository;

class Search extends XFCP_Search
{
    /**
     * @param $searchId
     * @return \XF\Entity\Search
     */
    public function getSearchById($searchId)
    {
        /** @var \XF\Entity\Search $search */
        $search = $this->finder('XF:Search')->whereId($searchId)->fetchOne();
        return $search;
    }


}