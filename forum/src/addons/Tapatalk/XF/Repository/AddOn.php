<?php

namespace Tapatalk\XF\Repository;

class AddOn extends XFCP_AddOn
{

    /**
     * @param $name
     * @return null|\XF\Mvc\Entity\Entity
     */
    public function getAddOnById($name)
    {
        return $this->finder('XF:AddOn')->where([
            'addon_id' => $name
        ])->fetchOne();
    }

    /**
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getAllAddOns()
    {
        return $this->findAddOnsForList()->fetch();
    }

}