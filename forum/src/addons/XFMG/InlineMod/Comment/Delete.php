<?php

namespace XFMG\InlineMod\Comment;

use XF\Http\Request;
use XF\InlineMod\AbstractAction;
use XF\Mvc\Controller;
use XF\Mvc\Entity\AbstractCollection;
use XF\Mvc\Entity\Entity;
use XFMG\InlineMod\AlertSendableTrait;
use XFMG\Service\Comment\Deleter;

use function count;

/**
 * @extends AbstractAction<\XFMG\Entity\Comment>
 */
class Delete extends AbstractAction
{
	use AlertSendableTrait;

	public function getTitle()
	{
		return \XF::phrase('xfmg_delete_comments...');
	}

	protected function canApplyToEntity(Entity $entity, array $options, &$error = null)
	{
		return $entity->canDelete($options['type'], $error);
	}

	protected function applyToEntity(Entity $entity, array $options)
	{
		/** @var Deleter $deleter */
		$deleter = $this->app()->service('XFMG:Comment\Deleter', $entity);

		if ($options['alert'] && $entity->canSendModeratorActionAlert())
		{
			$deleter->setSendAlert(true, $options['alert_reason']);
		}

		$deleter->delete($options['type'], $options['reason']);
	}

	public function getBaseOptions()
	{
		return [
			'type' => 'soft',
			'reason' => '',
			'alert' => false,
			'alert_reason' => '',
		];
	}

	public function renderForm(AbstractCollection $entities, Controller $controller)
	{
		$viewParams = [
			'comments' => $entities,
			'total' => count($entities),
			'canHardDelete' => $this->canApply($entities, ['type' => 'hard']),
			'canSendAlert' => $this->canSendAlert($entities),
		];
		return $controller->view('XFMG:Public:InlineMod\Comment\Delete', 'xfmg_inline_mod_comment_delete', $viewParams);
	}

	public function getFormOptions(AbstractCollection $entities, Request $request)
	{
		return [
			'type' => $request->filter('hard_delete', 'bool') ? 'hard' : 'soft',
			'reason' => $request->filter('reason', 'str'),
			'alert' => $request->filter('author_alert', 'bool'),
			'alert_reason' => $request->filter('author_alert_reason', 'str'),
		];
	}
}
