<?php

namespace XFMG\XF\Admin\Controller;

use XF\Entity\AbstractNode;
use XF\Entity\Node;
use XF\Mvc\FormAction;
use XF\Mvc\Reply\View;

class Forum extends XFCP_Forum
{
	protected function nodeAddEdit(Node $node)
	{
		$reply = parent::nodeAddEdit($node);

		if ($reply instanceof View)
		{
			$categoryTree = $this->repository('XFMG:Category')->createCategoryTree();
			$reply->setParam('xfmgCategoryTree', $categoryTree);
		}

		return $reply;
	}

	protected function saveTypeData(FormAction $form, Node $node, AbstractNode $data)
	{
		$result = parent::saveTypeData($form, $node, $data);

		/** @var \XF\Entity\Forum $data */
		$data->xfmg_media_mirror_category_id = $this->filter('xfmg_media_mirror_category_id', 'uint');

		return $result;
	}
}
