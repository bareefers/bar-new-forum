<?php

defined('IN_MOBIQUO') or exit;

function sync_user_func(){
    $code = trim($_POST['code']);
    $start = (isset($_POST['start']) && is_numeric($_POST['start']) && intval($_POST['start']) > 0) ? intval($_POST['start']) : 0;
    $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && intval($_POST['limit']) > 0) ? intval($_POST['limit']) : 1000;
    $format = trim($_POST['format']);

    $connection = new classFileManagement();
    $response = $connection->actionVerification($code,'sync_user');

    if($response === true)
    {
        try {
            $options = XenForo_Application::get('options');
            $api_key = $options->tp_push_key;

            // Get users...
            $users = array();

            $userModel = XenForo_Model::create('XenForo_Model_User');

            $sql = "SELECT user.user_id, user.username, user.email as user_email, user_option.receive_admin_email as allow_email, language.title as language
                FROM xf_user AS user, xf_user_option AS user_option, xf_language AS language
                WHERE user.user_id = user_option.user_id and user.language_id = language.language_id and user.is_banned = 0 and user.user_state = 'valid' and user.user_id > ? ORDER BY user.user_id ASC LIMIT ?";

            $result = XenForo_Application::getDb()->query($sql, array($start, $limit), Zend_Db::FETCH_ASSOC);
            while ($row = $result->fetch())
            {
                $user['uid'] = $row['user_id'];
                $user['username'] = $row['username'];
                $user['encrypt_email'] = base64_encode(encrypt($row['user_email'],$api_key));
                $user['allow_email'] = $row['allow_email'];
                $user['language'] = !empty($row['language']) ? $row['language'] : $context['user']['language'];
                $users[] = $user;
            }

            $data = array(
            'result' => true,
            'users' => $users,
            );
        }catch (Exception $e){
            $data = array(
                'result' => false,
                'result_text' => $e->getMessage(),
            );
        }
    }
    else
    {
        $data = array(
            'result' => false,
            'result_text' => $response,
        );
    }
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}