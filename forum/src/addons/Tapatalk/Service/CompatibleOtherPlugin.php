<?php

namespace Tapatalk\Service;

class CompatibleOtherPlugin
{

    /**
     * @return bool|null|\XF\Mvc\Entity\Entity
     */
    public static function getAndyForumPassword()
    {
        $app = \XF::app();
        $andyForumPassword = $app->finder('XF:AddOn')->where([
            'addon_id' => 'Andy/ForumPassword'
        ])->fetchOne();
        if ($andyForumPassword) {
            if (!$andyForumPassword->get('active')) {
                return false;
            }
            return $andyForumPassword;
        }else{
            return false;
        }
    }

    /**
     * @param null $nodeId
     * @return bool
     */
    public static function forumIsProtected($nodeId = null)
    {
        if ($nodeId === null) return false;
        $andyForumPassword = self::getAndyForumPassword();
        if ($andyForumPassword) {
            // get options
            $options = \XF::options();
            $forumId = isset($options->forumPasswordForumId) ? $options->forumPasswordForumId : '';
            $forumId = rtrim($forumId, ',');
            $forumIdArray = explode(',', $forumId);
            // check if current nodeId is password protected
            if (in_array($nodeId, $forumIdArray))
            {
                $password = $options->forumPasswordPassword;
                $password = rtrim($password, ',');
                $passwordArray = explode(',', $password);
                $key = array_search($nodeId, $forumIdArray);
                if (!isset($passwordArray[$key]) || !$passwordArray[$key]) {
                    return false;
                }
                if ($andyForumPassword->get('version_id') <=11) {
                    $var = 'password' . $nodeId;
                    // get cookie
                    $cookiePassword = @$_COOKIE[$var];
                    if ($cookiePassword == $passwordArray[$key]) {
                        return false;
                    }
                }elseif ($andyForumPassword->get('version_id') >= 12) {
                    // version 1.2
                    $visitor = \XF::visitor();
                    if (!$visitor->user_id) {
                        return true;
                    }
                    $userId = $visitor->user_id;
                    try {
                        // get savedPassword
                        $db = \XF::db();
                        $savedPassword = $db->fetchOne("
                            SELECT password
                            FROM xf_andy_forum_password
                            WHERE user_id = ?
                            ", $userId);
                        if ($savedPassword == $passwordArray[$key]) {
                            return false;
                        }
                    } catch (\Exception $e) {}
                }
                return true;
            }
        }
        return false;
    }

    /**
     * @param $nodeId
     * @param $passwordEntered
     * @return bool|string|null
     */
    public static function loginForum($nodeId, $passwordEntered)
    {
        $andyForumPassword = self::getAndyForumPassword();
        if ($andyForumPassword) {
            // get options
            $options = \XF::options();
            $forumId = isset($options->forumPasswordForumId) ? $options->forumPasswordForumId : '';
            $forumId = rtrim($forumId, ',');
            $forumIdArray = explode(',', $forumId);

            $password = isset($options->forumPasswordPassword) ? $options->forumPasswordPassword : '';
            $password = rtrim($password, ',');
            $passwordArray = explode(',', $password);
            $key = array_search($nodeId, $forumIdArray);
            if (!isset($passwordArray[$key]) || $passwordArray[$key] == '') {
                return true;
            }
            if ($andyForumPassword->get('version_id') <= 11) {
                if ($passwordEntered == $passwordArray[$key]) {
                    $cookieName = 'password' . $nodeId;
                    setcookie($cookieName, $passwordEntered, time() + 3600 * 24 * 365 * 1, '/');
                    return true;
                }else{
                    return false;
                }
            } elseif ($andyForumPassword->get('version_id') >= 12) {
                $visitor = \XF::visitor();
                $userId = $visitor->user_id;
                if ($userId > 0) {
                    if ($passwordEntered == $passwordArray[$key]) {
                        try {
                            $db = \XF::db();
                            $db->query("
                            DELETE FROM xf_andy_forum_password
                            WHERE user_id = ?
                        ", $userId);
                            // insert row
                            $db->query("
                            INSERT INTO xf_andy_forum_password
                                (user_id, password)
                            VALUES 
                                (?,?)
                        ", array($userId, $passwordEntered));
                        } catch (\Exception $e) {
                        }
                        return true;
                    }else{
                        return false;
                    }
                } else {
                    if ($passwordEntered == $passwordArray[$key]) {
                        return true;
                    }else{
                        return \XF::phrase('login_required')->render();
                    }
                    // wait app upgrade to handle
                    //return \XF::phrase('login_required')->render();
                }
            }
        }
        return null;
    }

    public static function canAclGetTopic($oMbqEtForum)
    {
        if (!$oMbqEtForum || !is_a($oMbqEtForum, 'MbqEtForum')) {
            return;
        }
        $nodeId = $oMbqEtForum->forumId->oriValue;
        return self::checkPassForumPasswordByNodeId($nodeId);
    }

    /**
     * @param \MbqEtForumTopic $oMbqEtForumTopic
     * @return bool|void|\XF\Phrase
     */
    public static function canAclGetThread($oMbqEtForumTopic)
    {
        if (!$oMbqEtForumTopic || !is_a($oMbqEtForumTopic, 'MbqEtForumTopic')) {
            return;
        }
        $nodeId = $oMbqEtForumTopic->forumId->oriValue;
        return self::checkPassForumPasswordByNodeId($nodeId);
    }

    /**
     * @param $nodeId
     * @return bool|\XF\Phrase
     */
    protected static function checkPassForumPasswordByNodeId($nodeId)
    {
        $andyForumPassword = self::getAndyForumPassword();
        if ($andyForumPassword) {
            // get options
            $options = \XF::options();
            $forumId = isset($options->forumPasswordForumId) ? $options->forumPasswordForumId : '';
            $forumId = rtrim($forumId, ',');
            $forumIdArray = explode(',', $forumId);

            $password = isset($options->forumPasswordPassword) ? $options->forumPasswordPassword : '';
            $password = rtrim($password, ',');
            $passwordArray = explode(',', $password);
            $key = array_search($nodeId, $forumIdArray);
            if ($key === false) {
                return true;
            }
            if (!isset($passwordArray[$key]) || $passwordArray[$key] == '') {
                return true;
            }
            if ($andyForumPassword->get('version_id') <= 11) {
                $var = 'password' . $nodeId;
                // get cookie
                $cookiePassword = @$_COOKIE[$var];
                if ($cookiePassword == $passwordArray[$key]) {
                    return true;
                }else{
                    return false;
                }
            } elseif ($andyForumPassword->get('version_id') >= 12) {
                $visitor = \XF::visitor();
                $userId = $visitor->user_id;
                if ($userId > 0) {
                    try {
                        $db = \XF::db();
                        // get savedPassword
                        $savedPassword = $db->fetchOne("
                        SELECT password
                        FROM xf_andy_forum_password
                        WHERE user_id = ?
                        ", $userId);
                        if ($savedPassword == $passwordArray[$key]) {
                            return true;
                        }else{
                            return false;
                        }
                    }catch (\Exception $e){
                        return false;
                    }
                } else {
                    return \XF::phrase('login_required');
                }
            }
        }else{
           return true;
        }
    }



}