<?php

namespace Tapatalk\Pub\Controller;

use XF\App;
use XF\Http\Request;
use \XF\Pub\Controller\AbstractController;

class Tapatalk extends AbstractController
{

    public static function getActivityDetails(array $activities)
    {
        /** @var \XF\Entity\SessionActivity $activities */
        $action = isset($activities['controller_action']) ? $activities['controller_action'] : '';
        $params = isset($activities['params']) ? $activities['params'] : '';
        $user_id = isset($activities['user_id']) ? $activities['user_id'] : '';

        $agentPhrase = isset($params['useragent']) && in_array($params['useragent'], array('tapatalk', 'byo')) ? $params['useragent'] : 'tapatalk';

        $app = \XF::app();
        $request = $app->request();

        switch($action)
        {
            case 'get_topic':
                $newControllerName = 'XF:Thread';
                break;

            case 'get_thread':
            case 'get_thread_by_post':
            case 'get_thread_by_unread':
                $newControllerName = 'XF:Thread';
                break;

            case 'get_user_info':
                $newControllerName = 'XF:Member';
                break;

            case 'search':
            case 'search_topic':
            case 'search_post':
                $newControllerName = 'XF:Search';
                break;

            case 'get_participated_topic':
            case 'get_unread_topic':
            case 'get_latest_topic':
                $newControllerName = 'XF:Thread';
                break;

            case 'get_subscribed_topic':
                $newControllerName = 'XF:Thread';
                break;

            case 'get_conversation':
            case 'get_conversations':
            case 'get_forum':
            case 'get_online_users':
                $newControllerName = 'XF:Online';
                break;
            default:
                $activityDettails = \XF::phrase('[On Tapatalk]');
        }
        if (isset($newControllerName) && $newControllerName) {
            $theController = $app->controller($newControllerName, $request);
            $activityDettails = $theController::getActivityDetails($activities);
        }
        return $activityDettails;
    }

}