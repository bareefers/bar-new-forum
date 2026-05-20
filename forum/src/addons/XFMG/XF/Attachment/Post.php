<?php

namespace XFMG\XF\Attachment;

use XF\Entity\Attachment;
use XF\FileWrapper;
use XF\Mvc\Entity\Entity;
use XFMG\Service\Media\MirrorManager;

class Post extends XFCP_Post
{
	public function onNewAttachment(Attachment $attachment, FileWrapper $file)
	{
		parent::onNewAttachment($attachment, $file);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentInserted($attachment, $file);
	}

	public function onAssociation(Attachment $attachment, ?Entity $container = null)
	{
		parent::onAssociation($attachment, $container);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentAssociated($attachment);
	}

	public function onAttachmentDelete(Attachment $attachment, ?Entity $container = null)
	{
		parent::onAttachmentDelete($attachment, $container);

		/** @var \XFMG\XF\Entity\Attachment $attachment */

		/** @var MirrorManager $mirrorManager */
		$mirrorManager = \XF::service('XFMG:Media\MirrorManager');
		$mirrorManager->attachmentDeleted($attachment);
	}
}
