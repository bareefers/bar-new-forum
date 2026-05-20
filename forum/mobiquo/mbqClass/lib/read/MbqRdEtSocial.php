<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtSocial');

/**
 * Social read class
 */
Class MbqRdEtSocial extends MbqBaseRdEtSocial
{

    public function __construct()
    {
    }

    /**
     * get social objs
     *
     * @return  Array
     */
    public function getObjsMbqEtSocial($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();

        if (!isset($mbqOpt['case']) || $mbqOpt['case'] != 'alert') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
        }else{


            $visitor = $bridge::visitor();

            $oMbqDataPage = $mbqOpt['oMbqDataPage'];

            $start = $oMbqDataPage->startNum;
            $limit = $oMbqDataPage->numPerPage;

            $alertRepo = $bridge->getUserAlertRepo();

            $alertsFinder = $alertRepo->findAlertsForUser($visitor->user_id);
            // $alerts = $alertsFinder->limitByPage($page, $perPage)->fetch();
            $alerts = $alertsFinder->fetch();
            $alertRepo->addContentToAlerts($alerts);

            /** @var \XF\Mvc\Entity\ArrayCollection $alerts */
            $alerts = $alerts->filterViewable();

            $alert_format = array(
                'sub' => '%s replied to "%s"',
                'like' => '%s liked your post in thread "%s"',
                'thank' => '%s thanked your post in thread "%s"',
                'quote' => '%s quoted your post in thread "%s"',
                'tag' => '%s mentioned you in thread "%s"',
                'newtopic' => '%s started a new thread "%s"',
                'pm' => '%s sent you a message "%s"',
                'ann' => '%sNew Announcement "%s"',
            );
            $allow_action = array(
                'insert' => 'sub',
                'watch_reply' => 'sub',
                'quote' => 'quote',
                'tag' => 'tag',
                'like' => 'like',
                'sub' => 'sub',
                'insert_attachment' => 'sub',
            );

            $total_num = 0;
            $processedAlertNum = 0;

            /**
             * @var  $id
             * @var  \XF\Entity\UserAlert $alert
             */
            foreach ($alerts as $id => $alert) {
                if (!isset($allow_action[$alert['action']])) {
                    continue;
                }
                $mbqType = $allow_action[$alert['action']];

                if (!isset($alert_format[$mbqType])) {
                    continue;
                }
                $processedAlertNum++;
                if (($processedAlertNum < $start) || ($processedAlertNum - $start > $limit - 1)) {
                    $total_num++;
                    continue;
                }

                $notif = $this->initOMbqEtSocial($alert, $mbqOpt);
                if ($notif != null) {
                    $oMbqDataPage->datas[] = $notif;
                }
                $total_num++;
            }

            $alertRepo->markUserAlertsRead($visitor);

            //they do not return count, only num of pages so we need to play with it
            $oMbqDataPage->totalNum = $total_num;
            return $oMbqDataPage;
        }
    }

    /**
     * init one social by condition
     *
     * @param $var
     * @param $mbqOpt
     * @return MbqEtAlert|null
     */
    public function initOMbqEtSocial($var, $mbqOpt)
    {
        if (!isset($mbqOpt['case']) || $mbqOpt['case'] != 'alert') {
            MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
        }else{

            $bridge = Bridge::getInstance();

            /** @var \XF\Entity\UserAlert $alert */
            $alert = $var;
            if (!($alert instanceof \XF\Entity\UserAlert)) {
                return null;
            }

            $user = $alert->getRelation('User');
            if (!$user || !($user instanceof \XF\Entity\User)) {
                $user = $bridge->getUserRepo()->findUserById($alert['user_id']);
            }
            $allow_action = array(
                'insert' => 'sub',
                'watch_reply' => 'sub',
                'quote' => 'quote',
                'tag' => 'tag',
                'like' => 'like',
                'sub' => 'sub',
                'insert_attachment' => 'sub',
            );
            $alert_format = array(
                'sub' => '%s replied to "%s"',
                'like' => '%s liked your post in thread "%s"',
                'thank' => '%s thanked your post in thread "%s"',
                'quote' => '%s quoted your post in thread "%s"',
                'tag' => '%s mentioned you in thread "%s"',
                'newtopic' => '%s started a new thread "%s"',
                'pm' => '%s sent you a message "%s"',
                'ann' => '%sNew Announcement "%s"',
            );
            if (!isset($allow_action[$alert['action']])) {
                return null;
            }
            $mbqType = $allow_action[$alert['action']];

            if ($mbqType == 'sub') {
                if (isset($alert['content_type']) && $alert['content_type'] == 'post' && $alert['action'] == 'insert') {
                    $mbqType = 'newtopic';
                }
            }

            // $message = sprintf($alert_format[$mbqType], $user['username'], $this->basic_clean($alert->render()) );
            $message = $this->basic_clean($alert->render());

            /** @var MbqEtAlert $oMbqEtAlert */
            $oMbqEtAlert = MbqMain::$oClk->newObj('MbqEtAlert');

            $oMbqEtAlert->userId->setOriValue($user['user_id']);
            $oMbqEtAlert->username->setOriValue($user['username']);
            $oMbqEtAlert->iconUrl->setOriValue(TT_get_avatar($user));

            $oMbqEtAlert->message->setOriValue($message);
            $oMbqEtAlert->contentType->setOriValue($mbqType);
            if (isset($alert['content_type']) && $alert['content_type'] == 'post' && isset($alert['content_id'])) {
                $oMbqEtAlert->contentId->setOriValue($alert['content_id']);
            }
            $oMbqEtAlert->timestamp->setOriValue($alert['event_date']);

            return $oMbqEtAlert;

        }
    }

    function basic_clean($str)
    {
        $str = strip_tags($str);
        $str = trim($str);
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

}