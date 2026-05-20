<?php

namespace Tapatalk\Service;

use XF\Entity\User;
use XF\Service\User\Registration;

class MbqRegistration extends Registration
{
    /**
     * @var \XF\Entity\User
     */
    protected $user;
    protected $tp_user_state;

    protected $fieldMap = [
        'username' => 'username',
        'user_state' => 'user_state',
        'signature' => 'signature',
        'description' => 'description',
        'gravatar' => 'gravatar',
    ];

    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
    }

    public function setUserMapped(array $input)
    {
        foreach ($this->fieldMap AS $inputKey => $entityKey)
        {
            if (!isset($input[$inputKey]))
            {
                continue;
            }

//            $value = $input[$inputKey];
//            if (strpos($entityKey, '.'))
//            {
//                list($relation, $relationKey) = explode('.', $entityKey, 2);
//                $this->user->{$relation}->{$relationKey} = $value;
//            }
//            else
//            {
//                $this->user-> offsetSet($entityKey,$value);
//            }
        }
    }

    public function setTpUserState($user_state)
    {
        $this->tp_user_state = $user_state;
    }

    public function getTpUserState()
    {
        return $this->tp_user_state;
    }

    public function _save()
    {
        $user = $this->user;

        $user->save();

        $this->app->spam()->userChecker()->logSpamTrigger('user', $user->user_id);

        if ($this->logIp)
        {
            $ip = ($this->logIp === true ? $this->app->request()->getIp() : $this->logIp);
            $this->writeIpLog($ip);
        }

        $this->updateUserAchievements();

        if ($user->user_state != 'valid') {
            // handle send email
            if ($this->tp_user_state == 'valid') {
                $this->user->set('user_state', $this->tp_user_state, ['forceSet' => true, 'skipInvalid' => true]);
                $user->set('user_state', $this->tp_user_state, ['forceSet' => true, 'skipInvalid' => true]);
            }
        }
        $this->sendRegistrationContact();

        if ($this->avatarUrl)
        {
            $this->applyAvatarFromUrl($this->avatarUrl);
        }

        return $user;
    }


}