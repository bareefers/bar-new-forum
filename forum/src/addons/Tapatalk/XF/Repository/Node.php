<?php

namespace Tapatalk\XF\Repository;


class Node extends XFCP_Node
{

    /**
     * @param $nodeId
     * @return \XF\Entity\Node
     */
    public function findNodeById($nodeId)
    {
        /** @var \XF\Entity\Node $node */
        $node = $this->finder('XF:Node')->whereId($nodeId)->fetchOne();
        return $node;
    }

    /**
     * @param array $nodeIds
     * @return null|\XF\Mvc\Entity\ArrayCollection
     */
    public function getNodesByIds(array $nodeIds)
    {
        if (!$nodeIds || !is_array($nodeIds))
            return null;

        return $this->finder('XF:Node')->whereIds($nodeIds)->fetch();
    }

    /**
     * @return \XF\Mvc\Entity\AbstractCollection|\XF\Mvc\Entity\ArrayCollection
     */
    public function getNodeListAndForum()
    {
        return $this->getNodeList();

//        $nodes = $this->findNodesForList()
//            ->with('Forum')
//            ->pluckFrom('Forum', 'node_id')
//            ->fetch();
//
//        $this->loadNodeTypeDataForNodes($nodes);
//        return $this->filterViewable($nodes);
    }


}