<?php

namespace Tapatalk\XF\Repository;


class Option extends XFCP_Option
{
    /**
     * @param $optionId
     * @return \XF\Entity\Option
     */
    public function getOptionById($optionId)
    {
        /** @var \XF\Entity\Option $option */
        $option = $this->finder('XF:Option')->whereId($optionId)->fetchOne();
        return $option;
    }


}