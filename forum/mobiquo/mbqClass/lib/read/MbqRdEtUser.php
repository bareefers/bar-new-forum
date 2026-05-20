<?php

use Tapatalk\Bridge;
use XF\ControllerPlugin\LoginTfaResult;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseRdEtUser');

/**
 * user read class
 */
Class MbqRdEtUser extends MbqBaseRdEtUser
{
    protected static $bridge;

    public function __construct()
    {
        self::$bridge = Bridge::getInstance();
    }

    public function makeProperty(&$oMbqEtUser, $pName, $mbqOpt = array())
    {
        switch ($pName) {
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_PNAME . ':' . $pName . '.');
                break;
        }
    }

    /**
     * @param $login
     * @param $password
     * @param int $anonymous
     * @param $trustCode
     * @return bool|mixed|string
     */
    public function login($login, $password, $anonymous = 0, $trustCode)
    {

        if (!$login) { // username is null
            return 'The requested user could not be found.';
        }

        $loginService = self::$bridge->getUserLoginService($login);

        $error = '';
        /** @var \XF\Entity\User $user */
        $user = $loginService->validate($password, $error);

        if ($user) {
            return $this->doLogin($user, $trustCode);
        }

        $bridge = Bridge::getInstance();
        $stringFormatter = $bridge->stringFormatter();
        $tokens = [
            '{name}' => $login
        ];

        $errorPhraseName = TT_GetPhraseString($error->getName());
        $errorString = strtr($stringFormatter->replacePhrasePlaceholders($errorPhraseName), $tokens);

        if ($login) {
            $loginAttemptRepo = self::$bridge->getLoginAttemptRepo();
            $loginAttemptRepo->logFailedLogin($login, self::$bridge->_request->getIp());
        }

        //if($error_phrasename == 'requested_user_x_not_found')
        //{
        // }

        return $errorString;
    }

    public function loginTwoStep($twoStepCode, $trust, &$trustCode)
    {
        $bridge = Bridge::getInstance();

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $bridge->plugin('XF:Login');

        $remember = $trust;

        $redirect = $bridge->getDynamicRedirectIfNot($bridge->buildLink('login'));

        // fake data
        $request = $bridge->request();

        $providerTypes = [];  // totp, backup, email
        $user = $loginPlugin->getTfaLoginUser();
        if (!$user) {
            return "Session Expired, Please login again";

        }else{
            $userRepo = $bridge->getUserRepo();
            $userTfas = $userRepo->getTfasByUserId($user->user_id);
            if ($userTfas) {
                foreach ($userTfas as $userTfa) {
                    if ($userTfa->get('provider_id')) {
                        $providerTypes[] = $userTfa->get('provider_id'); // get provider type
                    }
                }
            }
        }
        $request->set('REQUEST_METHOD', 'post');
        $request->set('confirm', '1');
        $request->set('remember', $trust);
        $request->set('trust', $trust);
        $request->set('code', $twoStepCode);

        $bridge::visitor()->reset(); //clear

        $twoStepLoginResult = false;
        $twoStepLoginResultMsg = '';
        foreach ($providerTypes as $providerType) {
            $request->set('provider', $providerType);
            $result = $loginPlugin->runTfaCheck($redirect);
            /** @var LoginTfaResult $twoStepLoginResultMsg */
            $twoStepLoginResultMsg = $result;
            $twoStepLoginResult = $result->getResult();
            if ($twoStepLoginResult == LoginTfaResult::RESULT_SUCCESS) {
                break;
            }
        }

        switch ($twoStepLoginResult)
        {
            case LoginTfaResult::RESULT_SUCCESS:
                $user = $twoStepLoginResultMsg->getUser();
                $loginPlugin->completeLogin($user, $remember);

                $UserTfaTrusted = $bridge->getUserTfaTrustedRepo()->getTfaTrustedByUserId($user->user_id);
                if ($UserTfaTrusted) {
                    $trustCode = $UserTfaTrusted->get('trusted_key');
                }

                return $this->doFinalLogin($user['user_id']);

            default:
                $errorMsg = $bridge->errorToString($twoStepLoginResultMsg->getError());
                return $errorMsg ?: "Two step athentication failed";
        }
    }

    public function loginDirectly($oMbqEtUser, $trustCode)
    {
        return $this->doLogin($oMbqEtUser->userId->oriValue, $trustCode);
    }

    private function doLogin($userId, $trustCode = null)
    {
        $bridge = self::$bridge;
        $options = $bridge->options();

        $userRepo = $bridge->getUserRepo();
        if (\XF::config('enableTfa')) {
            if (is_object($userId)) {
                $user = $userId;
            } else {
                $user = $userRepo->findUserById($userId);
            }
            if ($user->is_banned) {
                if (isset($user->Ban['user_reason']) && $user->Ban['user_reason']) {
                    $message = \XF::phrase('you_have_been_banned_for_following_reason_x', ['reason' => $user->Ban['user_reason']]);
                } else {
                    $message = \XF::phrase('you_have_been_banned');
                }
                return $bridge->errorToString($message);
            }

            /** @var \XF\ControllerPlugin\Login $loginPlugin */
            $loginPlugin = $bridge->getControllerPluginLogin();
            if (!empty($trustCode)) {
                $bridge->setUserCookie('tfa_trust', $trustCode);
            }else{
                $trustCode = $loginPlugin->getCurrentTrustKey();
            }
            $tfaRepo = $bridge->getTfaRepo();

            if ($tfaRepo->isUserTfaConfirmationRequired($user, $trustCode)) {
                if ($options->TT_2fa_enabled ==1) {

                    try {
                        $redirect = $bridge->getDynamicRedirectIfNot($bridge->buildLink('login'));
                        $request = $bridge->request();
                        $providerType = [];  // totp, backup, email
                        $userRepo = $bridge->getUserRepo();
                        $userTfas = $userRepo->getTfasByUserId($user->user_id);
                        if ($userTfas) {
                            foreach ($userTfas as $userTfa) {
                                if ( $userTfa->get('provider_id')) {
                                    $providerType[] = $userTfa->get('provider_id');
                                } // get provider type
                            }
                        }
                        $loginPlugin->setTfaSessionCheck($user);
                        $providerEmail = 'email';
                        if (in_array($providerEmail, $providerType)) {
                            $request->set('provider', $providerEmail);
                            /** @var \XF\Service\User\Tfa $tfaService */
                            $tfaService = $bridge->service('XF:User\Tfa', $user);
                            $triggered = $tfaService->trigger($bridge->request(), $providerEmail);
                            if (isset($triggered['providerData'])) {
                                $code = isset($triggered['providerData']['code']) ?: '';
                            }
                        }
                    }catch (Exception $e){}

                    /** @var \XF\Session\Session $publicSession */
                    $publicSession = $bridge->getSession();
                    $publicSession->save();
                    $publicSession->applyToResponse($bridge->_response);

                    return "two-step-required";
                }
                // Bypass TT_2fa_enabled is 0
            }
        }

        return $this->doFinalLogin($userId);
    }

    private function doFinalLogin($userId)
    {
        $bridge = self::$bridge;

        if (is_object($userId)) {
            /** @var \XF\Entity\User $user */
            $user = $userId;
            $userId = $user->get('user_id');
        } else {
            $user = $bridge->getUserRepo()->findUserById($userId);
        }

        $visitor = $bridge::visitor();
        $ip='';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        $bridge->getLoginAttemptRepo()->clearLoginAttempts($visitor['username'], $ip);

        $loginPlugin = $bridge->getControllerPluginLogin();
        $loginPlugin->completeLogin($user, false);

        /** @var \XF\Session\Session $publicSession */
        $publicSession = $bridge->getSession();
        if ($publicSession['userId']) {
            $publicSession->changeUser($user);
            $publicSession->save();
            $publicSession->applyToResponse($bridge->_response);
        }

        $this->initOCurMbqEtUser($userId);
        return MbqMain::$oCurMbqEtUser != null;
    }

    public function initOCurMbqEtUser($userId)
    {
        MbqMain::$Cache->Reset();
        MbqMain::$oCurMbqEtUser = $this->initOMbqEtUser($userId, array('case' => 'byUserId', 'loggedUser' => true));
    }

    /**
     * get user objs
     *
     * @param  Mixed $var
     * @param  Array $mbqOpt
     * $mbqOpt['case'] = 'byUserIds' means get data by user ids.$var is the ids.
     * @mbqOpt['case'] = 'online' means get online user.
     * @return  Array
     */
    public function getObjsMbqEtUser($var, $mbqOpt)
    {
        $bridge = Bridge::getInstance();
        $return = null;
        switch ($mbqOpt['case']) {
            case 'byUserIds':
                $return = $this->_getObjsMbqEtUserByUserIds($var, $mbqOpt, $bridge);
                break;
            case 'searchByName':
                $return = $this->_getObjsMbqEtUserSearchByName($var, $mbqOpt, $bridge);
                break;
            case 'online':
                $return = $this->_getObjsMbqEtUserOnline($var, $mbqOpt, $bridge);
                break;
            case 'ignored':
                $return = $this->_getObjsMbqEtUserIgnored($var, $mbqOpt, $bridge);
                break;
            case 'recommended':
                $return = $this->_getObjsMbqEtUserRecommended($var, $mbqOpt, $bridge);
                break;
            default:
                MbqError::alert('', __METHOD__ . ',line:' . __LINE__ . '.' . MBQ_ERR_INFO_UNKNOWN_CASE);
                break;
        }

        return $return;
    }

    protected function _getObjsMbqEtUserIgnored($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $visitor = $bridge::visitor();

        if ($ignored = $visitor->Profile->ignored) {
            /** @var \XF\Mvc\Entity\ArrayCollection $ignoringUsers */
            $ignoringUsers = $bridge->getUserRepo()->getUsersByIds(array_keys($ignored));
        } else {
            $ignoringUsers = [];
        }

        if($ignoringUsers && (count($ignoringUsers) > 0))
        {
            $oMbqDataPage->totalNum = $ignoringUsers->count();
            $ignoringUsers = $ignoringUsers->toArray();
            $ignoredUserList = array_slice($ignoringUsers, $oMbqDataPage->startNum, $oMbqDataPage->numPerPage);

            foreach($ignoredUserList as $user) {
                if ($user instanceof \XF\Entity\User) {
                    $mbqUser = $this->initOMbqEtUser($user, array('case'=>'user_row'));
                }else {
                    $mbqUser = $this->initOMbqEtUser($user, array('case' => 'byUserId'));
                }
                if ($mbqUser) $oMbqDataPage->datas[] = $mbqUser;
            }
        }

        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtUserOnline($var, $mbqOpt, Bridge $bridge)
    {
        $visitor = $bridge::visitor();
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];

        if (!$visitor->canViewMemberList()) {
            return $oMbqDataPage;
        }

        if (empty($var->id)) {
            $perPage = $oMbqDataPage->numPerPage ? $oMbqDataPage->numPerPage : 200;
            $page = $oMbqDataPage->curPage ? $oMbqDataPage->curPage : 1;

            $activityRepo = $bridge->getSessionActivityRepo();
            $typeLimit = 'member';
            /** @var \XF\Finder\SessionActivity $finder */
            $finder = $activityRepo->findForOnlineList($typeLimit);
            $memberOnlineTotal = $finder->total();
            /** @var \XF\Mvc\Entity\ArrayCollection $onlineUsers */
            $onlineUsers = $finder->limitByPage($page, $perPage)->fetch();

            /**
             * @var  $id
             * @var  \XF\Repository\SessionActivity $onlineuser
             */
            foreach ($onlineUsers as $id => $onlineuser) {
                $mbqUser = $this->initOMbqEtUser($onlineuser['user_id'], array('case' => 'byUserId'));
                if ($mbqUser) $oMbqDataPage->datas[] = $mbqUser;
            }

            //scrolling page of online users will result duplicate because Xenforo itself do it the same.
            $oMbqDataPage->totalNum = $memberOnlineTotal;
        }
        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtUserSearchByName($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $total = 0;
        $q = ltrim($var);
        if ($q !== '' && utf8_strlen($q) >= 2) {

            /** @var \XF\Finder\User $userFinder */
            $userFinder = $bridge->finder('XF:User');

            $users = $userFinder
                ->where('username', 'like', $userFinder->escapeLike($q, '?%'))
                ->isValidUser(true);
            $total = $users->total();
            $userLists = $users->limitByPage($oMbqDataPage->curPage, $oMbqDataPage->numPerPage);

        } else {
            $userLists = [];
        }

        /** @var \XF\Entity\User $user */
        foreach ($userLists as $user) {
            $mbqUser = $this->initOMbqEtUser($user, array('case' => 'user_row'));
            if ($mbqUser) $oMbqDataPage->datas[] = $mbqUser;
        }
        $oMbqDataPage->totalNum = $total;

        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtUserRecommended($var, $mbqOpt, Bridge $bridge)
    {
        $oMbqDataPage = $mbqOpt['oMbqDataPage'];
        $oMbqDataPage->datas = array();
        $oMbqDataPage->totalNum = 0;
        return $oMbqDataPage;
    }

    protected function _getObjsMbqEtUserByUserIds($var, $mbqOpt, Bridge $bridge)
    {
        $result = array();
        foreach ($var as $userId) {
            $oMbqEtUser = $this->initOMbqEtUser($userId, array('case' => 'byUserId'));
            if (is_a($oMbqEtUser, 'MbqEtUser')) {
                $result[] = $this->initOMbqEtUser($userId, array('case' => 'byUserId'));
            }
        }
        return $result;
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @return MbqEtUser|mixed|null
     */
    public function initOMbqEtUser($var, $mbqOpt)
    {
        $bridge = self::$bridge;
        $return = null;
        switch ($mbqOpt['case']) {
            case 'user_row':
                $return = $this->_initOMbqEtUserByUserRow($var, $mbqOpt, $bridge);
                break;
            case 'byLoginName':
                $return = $this->_initOMbqEtUserByLoginName($var, $mbqOpt, $bridge);
                break;
            case 'byEmail':
                $return = $this->_initOMbqEtUserByEmail($var, $mbqOpt, $bridge);
                break;
            case 'byUserId':
                $return = $this->_initOMbqEtUserByUserId($var, $mbqOpt, $bridge);
                break;
        }

        return $return;
    }

    protected function _initOMbqEtUserByLoginName($var, $mbqOpt, Bridge $bridge)
    {
        $username = $var;
        $member = $bridge->getUserRepo()->getUserByNameOrEmail($username);
        return $this->initOMbqEtUser($member['user_id'], array('case' => 'byUserId'));
    }

    protected function _initOMbqEtUserByEmail($var, $mbqOpt, Bridge $bridge)
    {
        $email = $var;
        $member = $bridge->getUserRepo()->getUserByNameOrEmail($email);
        return $this->initOMbqEtUser($member['user_id'], array('case' => 'byUserId'));
    }

    /**
     * @param $var
     * @param $mbqOpt
     * @param Bridge $bridge
     * @return MbqEtUser
     */
    protected function _initOMbqEtUserByUserRow($var, $mbqOpt, Bridge $bridge)
    {

        if ($var == false) {
            return null;
        }
        $visitor = $bridge::visitor();
        $options = $bridge->options();
        $userRepo = $bridge->getUserRepo();

        /** @var \XF\Entity\User $member */
        $member = $var;
        $isCurrentLoggedUser = false;

        $memberId = $member['user_id'];
        if ($memberId == null) {
            return null;
        }

        $loggedMemberId = $visitor['user_id'];

        if ($memberId == $loggedMemberId || (isset($mbqOpt['loggedUser']) && $mbqOpt['loggedUser'])) {
            $isCurrentLoggedUser = true;
        }

        /** @var MbqEtUser $oMbqEtUser */
        $oMbqEtUser = MbqMain::$oClk->newObj('MbqEtUser');
        $oMbqEtUser->userId->setOriValue($memberId);
        $oMbqEtUser->loginName->setOriValue($member['username']);
        $oMbqEtUser->userName->setOriValue($member['username']);
        $oMbqEtUser->userEmail->setOriValue(strtolower($member['email']));

        $groups = array($visitor['user_group_id']);

        if ($visitor['secondary_group_ids']) {
            $secondary_groups = $visitor['secondary_group_ids'];
            if (!is_array($visitor['secondary_group_ids'])) {
                $secondary_groups = explode(",", $visitor['secondary_group_ids']);
            }
            foreach ($secondary_groups as $secondary_group_id) {
                $groups[] = $secondary_group_id;
            }
        }

        $oMbqEtUser->userGroupIds->setOriValue($groups);
        $oMbqEtUser->iconUrl->setOriValue(TT_get_avatar($member, "l"));
        $oMbqEtUser->postCount->setOriValue($member['message_count']);
        $oMbqEtUser->userType->setOriValue(TT_get_usertype_by_item($memberId));
        $oMbqEtUser->canBan->setOriValue($visitor->hasAdminPermission('ban'));
        $oMbqEtUser->isBan->setOriValue($member['is_banned'] == 1);
        $postCountdown = 0;
        if (!$visitor->hasPermission('general', 'bypassFloodCheck')) {
            $postCountdown = $options->floodCheckLength;
        }
        $oMbqEtUser->postCountdown->setOriValue($postCountdown);
        $oMbqEtUser->regTime->setOriValue($member['register_date']);
        $oMbqEtUser->lastActivityTime->setOriValue($member['last_activity']);

        $lastActivityOutput = '';
        if ($visitor->canViewLatestActivity()) {
            $lastActivityOutput = $userRepo->outputLastActivityToString($visitor);
        }

        $oMbqEtUser->currentAction->setOriValue($lastActivityOutput);

        if ($isCurrentLoggedUser) {
            $oMbqEtUser->canSearch->setOriValue($member->canSearch());

            $oMbqEtUser->isOnline->setOriValue(true);
            $oMbqEtUser->canPm->setOriValue(true);
            $oMbqEtUser->canSendPm->setOriValue($member->canStartConversation());
            $oMbqEtUser->canModerate->setOriValue($member['is_moderator']);
            $oMbqEtUser->canWhosonline->setOriValue($member->canViewMemberList());
            $oMbqEtUser->canProfile->setOriValue($member->hasPermission('general', 'viewProfile'));

            $maxFileSize = 0;
            if ($member->canUploadAvatar()) {
                $maxFileSize = $bridge->app()->uploadMaxFilesize;
            }
            if (isset($maxFileSize) && !empty($maxFileSize) && $maxFileSize == -1) {
                $avatarSizeMap = $bridge->container('avatarSizeMap');
                $maxFileSize = $avatarSizeMap['l'] * 1024;
            }

            $maxAttachmentFileSize = $options->attachmentMaxFileSize ? $options->attachmentMaxFileSize * 1024 : 1048576;
            $oMbqEtUser->canUploadAvatar->setOriValue($member->canUploadAvatar());
            $oMbqEtUser->maxAvatarSize->setOriValue($maxFileSize);
            $oMbqEtUser->maxAvatarWidth->setOriValue($maxFileSize);
            $oMbqEtUser->maxAvatarHeight->setOriValue($maxFileSize);
            $oMbqEtUser->maxAttachment->setOriValue($options->attachmentMaxPerMessage ? $options->attachmentMaxPerMessage : 10);
            $oMbqEtUser->maxAttachmentSize->setOriValue($maxAttachmentFileSize);
            $oMbqEtUser->maxPngSize->setOriValue($maxAttachmentFileSize);
            $oMbqEtUser->maxJpgSize->setOriValue($maxAttachmentFileSize);
            $oMbqEtUser->allowedExtensions->setOriValue(implode(',', preg_split("/\s+/", trim($options->attachmentExtensions))));

            $memberProfile = $bridge->getUserProfile($member);
            if (isset($memberProfile->ignored) && !empty($memberProfile->ignored)) {
                $unSerializeIgnoreUsers = $memberProfile->ignored;
                if (!is_array($memberProfile->ignored)) {
                    $unSerializeIgnoreUsers = unserialize($memberProfile->ignored);
                }
                if (!empty($unSerializeIgnoreUsers)) {
                    $oMbqEtUser->ignoredUids->setOriValue(implode(',', array_keys($unSerializeIgnoreUsers)));
                }
            }
            if (isset($member->Profile) && $member->Profile) {
                $oMbqEtUser->displayText->setOriValue($bridge->renderPostPreview($member->Profile->signature, $member['user_id']));
            }
            
            $oMbqEtUser->isIgnored->setOriValue(false);
        } else {
            $oMbqEtUser->isIgnored->setOriValue(MbqCM::checkIfUserIsIgnored($memberId));
            $oMbqEtUser->isOnline->setOriValue($member->isOnline());
        }

        $oMbqEtUser->mbqBind = $var;
        return $oMbqEtUser;
    }

    protected function _initOMbqEtUserByUserId($var, $mbqOpt, Bridge $bridge)
    {
        $userId = $var;
        if (empty($userId)) {
            return null;
        }
        if (MbqMain::$Cache->Exists('MbqEtUser', $userId)) {
            return MbqMain::$Cache->Get('MbqEtUser', $userId);
        }

        $userModel = $bridge->getUserRepo();

        $custom_fields_list = array();

        $member = null;
        $member = $userModel->findUserById($userId);
        if (!$member || !($member instanceof \XF\Entity\User)) {
            if ($bridge->error) {
                return $bridge->error;
            }
            return false;
        }
        $userProfile = $bridge->getUserProfile($member);
        if ($member['user_id'] == null) {
            return null;
        }

        //if (!$userProfileModel->canViewFullUserProfile($member, $errorPhraseKey))
        //{
        //    throw $bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
        //}

        if (isset($member['following']) && $member['following']) {
            $followingCount = substr_count($member['following'], ',') + 1;
        } else {
            $followingCount = 0;
        }
        $userFollowRepo = $bridge->getUserFollowRepo();
        $findFollowers = $userFollowRepo->findFollowersForProfile($member);
        $followersCount = $findFollowers->total();

        $birthday = $userProfile->getBirthday();
        $age = $userProfile->getAge();

        if (isset($member['custom_title']) && !empty($member['custom_title'])) {
            TT_addNameValue($bridge::XFPhraseOutRender('title'), $member['custom_title'], $custom_fields_list);
        }

        if (isset($userProfile['location']) && !empty($userProfile['location'])) {
            TT_addNameValue($bridge::XFPhraseOutRender('location'), $userProfile['location'], $custom_fields_list);
        }
        if (isset($userProfile['website']) && !empty($userProfile['website'])) {
            TT_addNameValue($bridge::XFPhraseOutRender('website'), $userProfile['website'], $custom_fields_list);
        }

        // xf2.0 disable. occupation, gender

        if (!empty($birthday)) {
            $userLanguage = $bridge->getLanguage($member);
            TT_addNameValue($bridge::XFPhraseOutRender('birthday'),
                $userLanguage->date($birthday['timeStamp'], $birthday['format']) .
                (isset($birthday['age']) && !empty($birthday['age']) ? (" (" . $bridge::XFPhraseOutRender('age') . ": " . $birthday['age'] . ")") : ""), $custom_fields_list);
        } else if (!empty($age)) {
            TT_addNameValue($bridge::XFPhraseOutRender('age'), $age, $custom_fields_list);
        }

        if (version_compare($bridge::xfVersion(), '1.0.4', '>')) {

            if ($member->canViewIdentities()) {
                $displayCustomFields = ['contact','personal']; // preferences
            }else{
                $displayCustomFields = ['personal'];
            }

            /** @var \XF\CustomField\Set $fieldSet */
            $fieldSet = $userProfile->custom_fields;
            foreach ($displayCustomFields as $fieldsGroup) {
                $fieldDefinition = $fieldSet->getDefinitionSet()
                    ->filterGroup($fieldsGroup);

                $userFieldRepo = $bridge->getUserFieldRepo();
                $fieldsRow = $userFieldRepo->getUserFieldValues($member->user_id);

                $fieldsDefinition = $fieldDefinition->getFieldDefinitions();

                /** @var \XF\CustomField\Definition $identity */
                foreach ($fieldsDefinition as $identity) {
                    // check view able permission
                    if (!$identity['viewable_profile']) {
                        continue;
                    }
                    if (!$identity->hasValue($identity['field_id'])) {
                        continue;
                    }
                    $fieldTitle = $identity['title'];
                    if ($fieldTitle instanceof \XF\Phrase) {
                        $titleName = $fieldTitle->render();
                    } else {
                        $titleName = $identity['field_id'];
                    }

                    $match_type = $identity['field_type'];
                    $theFieldVal = $fieldSet->getFieldValue($identity['field_id']);
                    $theFieldVal = $identity->getFormattedValue($theFieldVal);
                    if ($match_type == 'stars') {
                        $theFieldVal = (int)$theFieldVal;
                        if (empty($theFieldVal)) {
                            continue;
                        }
                        switch ($theFieldVal) {
                            case 1:
                                $theFieldVal = $bridge::XFPhraseOutRender('terrible');
                                break;
                            case 2:
                                $theFieldVal = $bridge::XFPhraseOutRender('poor');
                                break;
                            case 3:
                                $theFieldVal = $bridge::XFPhraseOutRender('average');
                                break;
                            case 4:
                                $theFieldVal = $bridge::XFPhraseOutRender('good');
                                break;
                            case 5:
                                $theFieldVal = $bridge::XFPhraseOutRender('excellent');
                                break;
                        }
                    }else{
                        if ($theFieldVal instanceof \XF\Phrase) {
                            $theFieldVal = $theFieldVal->render();
                        }
                    }

                    TT_addNameValue($titleName, $theFieldVal, $custom_fields_list);
                }
            }
        }
        TT_addNameValue($bridge::XFPhraseOutRender('followers'), $followersCount, $custom_fields_list);
        TT_addNameValue($bridge::XFPhraseOutRender('following'), $followingCount, $custom_fields_list);
        TT_addNameValue($bridge::XFPhraseOutRender('likes_received'),
            isset($member['reaction_score']) ? $member['reaction_score'] : $member['like_count'], $custom_fields_list);
        TT_addNameValue($bridge::XFPhraseOutRender('trophy_points'), $member['trophy_points'], $custom_fields_list);
        if ($member->canViewWarnings()) {
            TT_addNameValue($bridge::XFPhraseOutRender('warning_points'), $member['warning_points'], $custom_fields_list);
        }
        if (!$member->canViewFullProfile($error)) {
            $custom_fields_list = [];
        }

        $oMbqEtUser = $this->initOMbqEtUser($member, array('case' => 'user_row'));
        if ($oMbqEtUser && is_a($oMbqEtUser, 'MbqEtUser')) {
            $oMbqEtUser->followingCount->setOriValue($followingCount);
            $oMbqEtUser->follower->setOriValue($followersCount);
            $oMbqEtUser->customFieldsList->setOriValue($custom_fields_list);
            MbqMain::$Cache->Set('MbqEtUser', $userId, $oMbqEtUser);
        }
        return $oMbqEtUser;
    }

    /**
     * new register custom fields (only required field)
     *
     * @return array
     */
    public function getCustomRegisterFields()
    {
        $bridge = self::$bridge;
        $custom_register_fields = array();

        /** @var \XF\CustomField\Set $fieldSet */
        $fieldSet = $bridge::visitor()->Profile->custom_fields;

        $fieldDefinition = $fieldSet->getDefinitionSet()
            ->filterEditable($fieldSet, 'user')
            ->filter('registration');

        $options = $bridge->options();
        if ($options->registrationSetup['requireDob']) {
            $custom_register_fields[] = [
                'name' => TT_GetPhraseString('date_of_birth'),
                'description' => 'Required',
                'key' => 'birthday',
                'type' => 'input',
                'options' => '', 'base64',
                'format' => 'nnnn-nn-nn',
                'is_birthday' => 1,
            ];
        }
        if ($options->registrationSetup['requireLocation']) {
            $custom_register_fields[] = [
                'name' => TT_GetPhraseString('location'),
                'description' => 'Required',
                'key' => 'location',
                'type' => 'input',
                'options' => '',
                'format' => '',
            ];
        }

        $fields = $fieldDefinition->getFieldDefinitions();

        foreach ($fields as $key => $value) {
            if (!$value['required']) continue;

            $field_type = "";

            switch ($value['field_type']) {
                case 'textbox':
                    $field_type = 'input';
                    break;
                case 'textarea':
                    $field_type = 'textarea';
                    break;
                case 'select':
                    $field_type = 'drop';
                    break;
                case 'radio':
                    $field_type = 'radio';
                    break;
                case 'checkbox':
                case 'multiselect':
                    $field_type = 'cbox';
                    break;
                case 'stars':
                    $field_type = 'drop';
                    break;
                case 'bbcode':
                    if (isset($value['type_group']) && $value['type_group'] == 'rich_text') {
                        $field_type = 'textarea';
                    }
                    break;
            }

            $format = "";
            switch ($value['match_type']) {
                case 'none':
                    $format = "";
                    break;
                case 'number':
                    if ($value['max_length'] == 0) {
                        $format = 'nnnnnnnnnn';
                    } else {
                        for ($ix = 0; $ix < $value['max_length']; $ix++) {
                            $format .= 'n';
                        }
                    }
                    break;
            }

            $option = "";
            $field_choices = [];
            if (isset($value['field_choices'])) {

                /** @var \XF\CustomField\Definition $value */
                $field_choices = (is_array($value['field_choices'])) ? $value['field_choices'] : unserialize($value['field_choices']);
            }
            if (!$field_choices && isset($value['field_type']) && $value['field_type'] == 'stars' && $field_type == 'drop') {
                $field_choices = [
                    1 => \XF::phrase('Terrible'),
                    2 => \XF::phrase('Poor'),
                    3 => \XF::phrase('Average'),
                    4 => \XF::phrase('Good'),
                    5 => \XF::phrase('Excellent')
                ];
            }

            /** @var \XF\Phrase $text */
            foreach ($field_choices as $title => $text) {
                if ($text instanceof \XF\Phrase) {
                    $text = $text->render();
                }
                $option .= $title . '=' . $text . '|';
            }
            $option = substr($option, 0, strlen($option) - 1);

            $custom_register_fields[] = array(
                'name' => $value['title']->render(),
                'description' => $value['description']->render(),
                'key' => $value['field_id'],
                'type' => $field_type,
                'options' => $option,
                'format' => $format,
            );
        }

        return $custom_register_fields;
    }

    /**
     * forget_password this function should send the email password change to this user
     *
     * @param MbqEtUser $oMbqEtUser
     * @return bool|string
     */
    public function forgetPassword($oMbqEtUser)
    {
        $bridge = Bridge::getInstance();

        /** @var \XF\Entity\User $user */
        $user = $oMbqEtUser->mbqBind;
        if (!$user || !($user instanceof \XF\Entity\User)) {
            $user = $bridge->getUserRepo()->findUserById($oMbqEtUser->userId->oriValue);
        }
        if (!$user) {
            return TT_GetPhraseString('requested_member_not_found');
        }

        /** @var \XF\Service\User\PasswordReset $passwordConfirmation */
        $passwordConfirmation = $bridge->getUserPasswordResetService($user);
        if (!$passwordConfirmation->canTriggerConfirmation($error)) {
            return $bridge->errorToString($error);
        }

        $passwordConfirmation->triggerConfirmation();

        return true;
    }

    public function logout()
    {
        $bridge = Bridge::getInstance();

        // $bridge->assertValidCsrfToken($bridge->filter('t', 'str'));

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $bridge->plugin('XF:Login');
        $loginPlugin->logoutVisitor();

        return true;
    }


    public function getDisplayName($oMbqEtUser)
    {
        //return $oMbqEtUser->loginName->oriValue;
        return htmlspecialchars_decode($oMbqEtUser->loginName->oriValue);
    }


    /**
     * @param string $username
     * @return bool|null|\XF\Phrase
     */
    public function validateUsername($username)
    {
        $bridge = self::$bridge;

        /** @var \XF\Validator\Username $validator */
        $validator = $bridge->app()->validator('Username');
        $username = $validator->coerceValue($username);

        if (isset($bridge->options()->admin_edit) && $bridge->options()->admin_edit)  // dev
        {
            $validator->setOption('admin_edit', true);
        }
        $errorKey = '';
        if (!$validator->isValid($username, $errorKey)) {
            return false;//$validator->getPrintableErrorValue($errorKey)->render();
        }

        return true;
    }

    /**
     * @param string $password
     * @return bool|\XF\Phrase
     */
    public function validatePassword($password)
    {
        $bridge = Bridge::getInstance();
        $password = strval($password);
        if (!strlen($password)) {
            return $bridge::XFPhrase('please_enter_valid_password')->render();
        }

        if (trim($password) == '' || strlen($password) < 2) {
            return false;
        }
        return true;
    }

}