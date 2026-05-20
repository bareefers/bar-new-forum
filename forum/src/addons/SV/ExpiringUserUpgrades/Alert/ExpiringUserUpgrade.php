<?php

namespace SV\ExpiringUserUpgrades\Alert;

use SV\ExpiringUserUpgrades\XF\Repository\UserUpgrade;
use XF\Alert\AbstractHandler;
use XF\Entity\UserAlert;
use XF\Mvc\Entity\Entity;

/**
 * Class ExpiringUserUpgrade
 *
 * @package SV\ExpiringUserUpgrades
 */
class ExpiringUserUpgrade extends AbstractHandler
{
    /**
     * @param Entity $entity
     * @param \XF\Phrase|string|null $error
     * @return bool
     */
    public function canViewContent(Entity $entity, &$error = null): bool
    {
        return true;
    }

    /**
     * @param string      $action
     * @param UserAlert   $alert
     * @param Entity|null $content
     * @return array
     */
    public function getTemplateData($action, UserAlert $alert, Entity $content = null): array
    {
        $data = parent::getTemplateData($action, $alert, $content);

        /** @var UserUpgrade $userUpgradeRepo */
        $userUpgradeRepo = \XF::repository('XF:UserUpgrade');

        $options = \XF::options();
        $upgradeUrl = $userUpgradeRepo->getUpgradeUrl();

        return array_merge($data, [
            'boardUrl'    => $options->boardUrl,
            'boardTitle'  => $options->boardTitle,
            'url'         => $upgradeUrl,
            'upgrade_url' => $upgradeUrl,
            'upgradeUrl'  => $upgradeUrl
        ]);
    }

    /**
     * @param int|int[] $id
     * @return null|\XF\Mvc\Entity\ArrayCollection|Entity
     */
    public function getContent($id)
    {
        return \XF::app()->findByContentType('user', $id, $this->getEntityWith());
    }

    public function getContentType(): string
    {
        return 'exup';
    }

    public function getOptOutDisplayOrder(): int
    {
        return 60000;
    }
}