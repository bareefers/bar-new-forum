<?php

namespace Tapatalk\XF\Repository;

class ThreadPrefix extends XFCP_ThreadPrefix
{

    /**
     * @param $id
     * @return mixed|string
     */
    public function getPrefixTitlePhraseName($id)
    {
        return \XF::phrase('thread_prefix.' . $id)->render();
    }

}