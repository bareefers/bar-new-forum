<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

MbqMain::$oClk->includeClass('MbqBaseWrEtUser');

/**
 * user write class
 */
Class MbqWrEtUser extends MbqBaseWrEtUser
{

    public function __construct()
    {
    }

    /**
     * @param $username
     * @param $password
     * @param $email
     * @param $verified
     * @param $custom_register_fields
     * @param $profile
     * @param $errors
     * @return MbqEtUser|mixed|null
     */
    public function registerUser($username, $password, $email, $verified, $custom_register_fields, $profile, &$errors)
    {
        $bridge = Bridge::getInstance();
        $userRepo = $bridge->getUserRepo();

        $regParams = [
            'username' => $username,
            'email' => $email,
            'password' => $password,
        ];
        $this->_setRegistrationInput($bridge, $regParams, $custom_register_fields, $profile);

        $input = $this->_getRegistrationInput($bridge->_request);

//        $existingUser = $userRepo->validUserExistUsernameOrEmail($input['email'], $input['username']);
//        if ($existingUser) {
//            $errors[] = 'User exist';
//            return false;
//        }

        $registration = $this->_setupRegistration($input, $bridge);
        $registration->checkForSpam();

        if (!$registration->validate($xfErrors)) {
            if (is_array($xfErrors)) {
                foreach ($xfErrors as $titleK => $errorLang) {
                    if ($errorLang instanceof \XF\Phrase) {
                        $errors[] = $titleK . ' ' . $errorLang->render();
                    }
                }
            }
            //$errors[] = $bridge->errorToString($xfErrors);
            return false;
        }

        $xf_options = $bridge->options();
        //user state
        $xf_reg_option = $xf_options->registrationSetup;
        $email_confirmation = $xf_reg_option['emailConfirmation'];
        $moderation = $xf_reg_option['moderation'];
        if (empty($email_confirmation) && empty($moderation)) {
            $user_state = 'valid';
        } else if (!empty($email_confirmation) && empty($moderation)) {
            $user_state = $verified ? 'valid' : 'email_confirm';
        } else if (empty($email_confirmation) && !empty($moderation)) {

            $user_state = ($verified && isset($xf_options->auto_approval_tp_user) && $xf_options->auto_approval_tp_user) ? 'valid' : 'moderated';
        } else {
            $user_state = $verified ? ((isset($xf_options->auto_approval_tp_user) && $xf_options->auto_approval_tp_user == true) ? 'valid' : 'moderated') : 'email_confirm';
        }
        $userGroupModel = $bridge->getUserGroupRepo();
        $userGroups = $userGroupModel->findUserGroupsForList()->fetch();
        if ($userGroups) {
            $userGroups = $userGroups->toArray();
        }else{
            $userGroups = [];
        }

        $tapatalk_reg_ug = $xf_options->tapatalk_reg_ug;
        if (!array_key_exists($tapatalk_reg_ug, $userGroups)) {
            $tapatalk_reg_ug = 0;
        }
        //indicate if it can use gravatar
        $gravatar = false;
        if (isset($profile['avatar_url']) && !empty($profile['avatar_url'])) {
            if (preg_match('/gravatar\.com\/avatar/', $profile['avatar_url'])) {
                if ($xf_options->gravatarEnable) {
                    $gravatar = true;
                }
            }
        }


        if (isset($custom_register_fields['tapatalk_avatar_url']) && !$custom_register_fields['tapatalk_avatar_url'] && isset($profile['avatar'])) {
            $custom_register_fields['tapatalk_avatar_url'] = $profile['avatar'];
        }

        if ($user_state == 'valid') {
            $registration->skipEmailConfirmation(true);
        }

        $preUser = $registration->getUser();
        $pre_user_state = $preUser->user_state;

        if ($pre_user_state != 'valid' && $user_state == 'valid') {
            // handle send confirm email
            $preUser->set('user_state', $user_state, ['forceSet' => true, 'skipInvalid' => true]);
        }

        $user = $registration->save();

        if (!$user || !($user instanceof \XF\Entity\User)) {
            $errors[] = 'create user fail';
            return false;
        }else{

            if (isset($profile['signature']) && !empty($profile['signature'])) {
                $user->set('signature', $profile['signature']);
            }
            if (isset($profile['description']) && !empty($profile['description'])) {
                $user->set('description', $profile['description']);
            }

            if ($user->user_group_id != $tapatalk_reg_ug) {
                $user->user_group_id = $tapatalk_reg_ug;
            }

            $user->save(false, false);

            if ($user_state == 'valid') {
                if ($user->isAwaitingEmailConfirmation()) {
                    /** @var \XF\Service\User\EmailConfirmation $emailConfirmation */
                    $emailConfirmation = $bridge->service('XF:User\EmailConfirmation', $user);
                    $emailConfirmation->emailConfirmed();
                }
            }

            $this->_finalizeRegistration($user);
        }

        /** @var MbqRdEtUser $oMbqRdEtUser */
        $oMbqRdEtUser = MbqMain::$oClk->newObj('MbqRdEtUser');

        return $oMbqRdEtUser->initOMbqEtUser($user, array('case' => 'user_row'));
    }

    /**
     * @param $oMbqEtUser
     * @param $newPassword
     * @return bool
     */
    public function updatePasswordDirectly($oMbqEtUser, $newPassword)
    {
        $bridge = Bridge::getInstance();
        $visitor = $oMbqEtUser->mbqBind;

        if (!$newPassword) {
            return $bridge->responseError('password cannot be empty');
        }

        if (!$visitor || !($visitor instanceof \XF\Entity\User)) {
            return false;
        }

        $passwordChange = $bridge->getUserPasswordChangeService($visitor, $newPassword);
        if (!$passwordChange->isValid($error)) {
            return $bridge->errorToString($error);
        }

        $passwordChange->setInvalidateRememberKeys(false); // about to handle this
        $passwordChange->save();

        return true;
    }

    /**
     * @param $bridge
     * @param $params
     * @param $customFields
     * @param $profile
     */
    protected function _setRegistrationInput(Bridge $bridge, $params, $customFields, $profile)
    {
        $request = $bridge->_request;
        $requireDob = $bridge->options()->registrationSetup['requireDob'];

        $hashedFields = [
            'username',
            'email',
            'password',
            'timezone'
        ];
        foreach ($hashedFields as $field) {
            if (isset($params[$field])) {
                $request->set($field, $params[$field]);
            }
        }
        if (isset($customFields['location'])) {
            $request->set('location', $customFields['location']);
            unset($customFields['location']);
        } elseif (isset($profile['location']) && !empty($profile['location'])) {
            $request->set('location', $profile['location']);
        }

        if (isset($customFields['birthday'])) {

            if (!empty($customFields['birthday'])) {
                $birthday = preg_split('/-/', $customFields['birthday']);
            }
            unset($customFields['birthday']);
        }
        $request->set('dob_day', isset($birthday[2]) && $birthday[2] >= 0 && $birthday[2] <= 31 ? $birthday[2] : ($requireDob ? '01' : '0'));
        $request->set('dob_month', isset($birthday[1]) && $birthday[2] >= 0 && $birthday[2] <= 12 ? $birthday[1] : ($requireDob ? '01' : '0'));
        $request->set('dob_year', isset($birthday[0]) ? $birthday[0] : ($requireDob ? '1971' : '0'));

        if (!$request->get('timezone')) {
            $options = $bridge->options();
            $request->set('timezone', (!isset($options->guestTimeZone) || empty($options->guestTimeZone)) ? 'Europe/London' : $options->guestTimeZone);
        }

        $request->set('custom_fields', $customFields);
    }

    /**
     * @param \XF\Http\Request $request
     * @return array|mixed|string
     */
    protected function _getRegistrationInput($request)
    {
        /** @var \XF\Http\Request $request */
        $input = $request->filter([
            'username' => 'str',
            'password' => 'str',
            'email' => 'str',
            'timezone' => 'str',
            'location' => 'str',
            'dob_day' => 'uint',
            'dob_month' => 'uint',
            'dob_year' => 'uint',
            'custom_fields' => 'array',
        ]);

        // $providerData ...


        return $input;
    }

    protected function _finalizeRegistration(\XF\Entity\User $user)
    {
        $bridge = Bridge::getInstance();
        $bridge->getSession()->changeUser($user);
        \XF::setVisitor($user);

        /** @var \XF\ControllerPlugin\Login $loginPlugin */
        $loginPlugin = $bridge->plugin('XF:Login');
        $loginPlugin->createVisitorRememberKey();
    }


    protected function _error($errors)
    {
        if (!is_array($errors)) {
            $errors = [$errors];
        }

        $errorArray = [];
        foreach ($errors as $key => $value) {
            if ($value instanceof \XF\Phrase) {
                $errorArray[] = $value->render();
            } else {
                $errorArray[] = $value;
            }
        }

        return implode(',', $errorArray);  // dev
    }

    /**
     * @param array $input
     * @param Bridge $bridge
     * @return \XF\Service\User\Registration
     */
    protected function _setupRegistration(array $input, Bridge $bridge)
    {
        $registration = $bridge->getUserRegistrationService();
        $registration->setFromInput($input);

        return $registration;
    }

    /**
     * update password
     *
     * @param $oldPassword
     * @param $newPassword
     * @return bool|void
     */
    public function updatePassword($oldPassword, $newPassword)
    {

        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!isset($visitor['username']) || empty($visitor['username'])) {
            return $bridge->responseError('You are not logged in.');
        }


        $auth = $visitor->Auth->getAuthenticationHandler();
        if (!$auth)
        {
            return $bridge->responseError('no Permission');
        }

        $bridge->_request->set('old_password', $oldPassword);
        $bridge->_request->set('password', $newPassword);

        $input = $bridge->_request->filter([
            'old_password' => 'str',
            'password' => 'str'
        ]);

        // dev XF:Tfa, two-step
        if (!$input['password']) {
            $bridge->responseError('password cannot be empty');
        }else{

            $passwordChange = $this->_setupPasswordChange($input);
            if ($passwordChange instanceof \XF\Phrase)
            {
                return $bridge->responseError($passwordChange->render());
            }

            if (!$passwordChange->isValid($error))
            {
                return $bridge->responseError($error);
            }

            $passwordChange->setInvalidateRememberKeys(false); // about to handle this
            $passwordChange->save();

            $bridge->plugin('XF:Login')->handleVisitorPasswordChange();
        }

        return true;
    }

    /**
     * @param array $input
     * @return \XF\Phrase|\XF\Service\User\PasswordChange
     */
    protected function _setupPasswordChange($input)
    {
        $bridge = Bridge::getInstance();

        $visitor = \XF::visitor();

        if (!$visitor->authenticate($input['old_password']))
        {
            return $bridge::XFPhrase('your_existing_password_is_not_correct');
        }

        return $bridge->getUserPasswordChangeService($visitor, $input['password']);
    }

    /**
     * @param $password
     * @param $email
     * @param $resultMessage
     * @return bool|string|void
     */
    public function updateEmail($password, $email, &$resultMessage)
    {

        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (empty($password) || empty($email))
            return 'Password/Email could not be empty!';

        $auth = $visitor->Auth->getAuthenticationHandler();
        if (!$auth) {
            return $bridge->responseError('You have no permissions to perform this action.');
        }

        if (!$auth->hasPassword()) {
            unset($email);
        }

        //
        /** @var \XF\Service\User\EmailChange $emailChange */
        $emailChange = $bridge->getUserEmailChangeService($visitor, $email);

        if (!$visitor->authenticate($password)){
            return TT_GetPhraseString('your_existing_password_is_not_correct');

        } else if (!$emailChange->isValid($changeError)) {
            return $bridge->errorToString($changeError);

        } else if (!$emailChange->canChangeEmail($error)) {
            if (!$error) {
                $error = $bridge::XFPhrase('your_email_may_not_be_changed_at_this_time');
            }
            return $bridge->errorToString($error);
        }

        $emailChange->save();
        $resultMessage = TT_GetPhraseString('your_account_must_be_reconfirmed');

        return true;
    }

    /**
     * upload avatar
     */
    public function uploadAvatar()
    {

        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        if (!$visitor->canUploadAvatar()) {
            return TT_GetPhraseString('do_not_have_permission');
        }

        /** @var \XF\Service\User\Avatar $avatarService */
        $avatarService = $bridge->service('XF:User\Avatar', $visitor);

        $upload = $bridge->request()->getFile('upload', false, false);
        if (!$upload) {
            return false;
        } else {

            if (!$avatarService->setImageFromUpload($upload)) {
                return $bridge->errorToString($avatarService->getError());
            }

            if (!$avatarService->updateAvatar()) {
                return $bridge->errorToString(\XF::phrase('new_avatar_could_not_be_processed'));
            }
            if (!$visitor->avatar_date) {
                return $bridge->errorToString(\XF::phrase('new_avatar_could_not_be_processed'));
            }
        }
        return true;
    }

    /**
     * m_ban_user
     * here,this function is just the same to m_mark_as_spam,so params mode and reason willn't be used.
     *
     * @param MbqEtUser $oMbqEtUser
     * @param $mode
     * @param $reason
     * @param $expires
     * @return bool|mixed|string
     */
    public function mBanUser($oMbqEtUser, $mode, $reason, $expires)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();

        $userId = $oMbqEtUser->userId->oriValue;
        $user = $oMbqEtUser->mbqBind;
        if (!$user || !($user instanceof \XF\Entity\User)) {
            $user = $bridge->getUserRepo()->findUserById($userId);
        }
        if (!$user) {
            return 'no find user';
        }
        if (!$user->canBan($error)) {
            return $bridge->noPermissionToString($error);
        }

        $request->set('user_reason', $reason);
        if ($expires) {
            $request->set('ban_length', 'temporary');
            $endDate = $expires;
            if (is_numeric($expires)) {
                $endDate = date('Y-m-d H:i:s', $expires);
            }
            $request->set('end_date', $endDate);
        }else{
            $request->set('ban_length', 'permanent');
        }
        $errorReturn = '';
        $form = $this->_userBanSaveProcess($user, $errorReturn);
        if ($errorReturn) {
            return $bridge->errorToString($errorReturn);
        }
        $form->run();

        // dev
//        $options = array(
//            'action_threads' => $mode == 2 ? 1 : 0,
//            'delete_messages' => $mode == 2 ? 1 : 0,
//            'email_user' => 0,
//            'email' => '',
//        );
//        $spamCleanerModel = $bridge->getSpamCleanerModel();
//        if (!$log = $spamCleanerModel->cleanUp($user, $options, $log, $errorKey)) {
//            return get_error($errorKey);
//        }

        return true;
    }

    /**
     * @param \XF\Entity\User $user
     * @param $errorReturn
     * @return \XF\Mvc\FormAction
     */
    protected function _userBanSaveProcess(\XF\Entity\User $user, &$errorReturn)
    {
        $bridge = Bridge::getInstance();
        $request = $bridge->request();
        $form = $bridge->formAction();

        $input = $request->filter([
            'ban_length' => 'str',
            'end_date' => 'datetime',
            'user_reason' => 'str'
        ]);

        if ($input['ban_length'] == 'permanent')
        {
            $input['end_date'] = 0;
        }

        $banningRepo = $bridge->getBanningRepo();
        if (!$banningRepo->banUser($user, $input['end_date'], $input['user_reason'], $errorReturn))
        {
            $form->logError($errorReturn);
        }

        return $form;
    }

    /**
     * m_unban_user
     * here,this function just unflag as spammer.
     *
     * @param MbqEtUser $oMbqEtUser
     * @return bool|string
     */
    public function mUnBanUser($oMbqEtUser)
    {
        $bridge = Bridge::getInstance();

        $userId = $oMbqEtUser->userId->oriValue;
        $user = $oMbqEtUser->mbqBind;
        if (!$user || !($user instanceof \XF\Entity\User)) {
            $user = $bridge->getUserRepo()->findUserById($userId);
        }
        if (!$user) {
            return 'no find user';
        }
        if (!$user->is_banned)
        {
            return true;
        }

        if (!$user->canBan($error))
        {
            return $bridge->noPermissionToString($error);
        }

        $user->Ban->delete();

        return true;
    }

    /**
     * @param MbqEtUser $oMbqEtUser
     * @param int $mode . 0 = unIgnore, 1 = ignore
     * @return bool|mixed|string
     */
    public function ignoreUser($oMbqEtUser, $mode)
    {
        $bridge = Bridge::getInstance();
        $visitor = $bridge::visitor();

        /** @var \XF\Entity\User $user */
        $user = $oMbqEtUser->mbqBind;
        if (!$user || !($user instanceof \XF\Entity\User)) {
            return false;
        }

        $wasIgnoring = $visitor->isIgnoring($user);
        $ignoreService = $bridge->getUserIgnoreService($user);

        if (isset($mode) && $mode == 0) {
            if (!$wasIgnoring){
                return true;
            }
            // unIgnore
            $userIgnored = $ignoreService->unignore();

        } else if (isset($mode) && $mode) {
            // ignore
            if (!$wasIgnoring && !$visitor->canIgnoreUser($user, $error)) {
                return $bridge->errorToString($error);
            }
            if ($wasIgnoring) {
                // exist ignore
                return true;
            }
            $userIgnored = $ignoreService->ignore();
        }

        if ($userIgnored->hasErrors())
        {
            return $bridge->errorToString($userIgnored->getErrors());
        }

        return true;
    }
}