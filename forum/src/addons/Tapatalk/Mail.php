<?php
class Tapatalk_Mail extends XFCP_Tapatalk_Mail{
    public function wrapMailContainer($subject, $bodyText, $bodyHtml)
    {
        $result = parent::wrapMailContainer($subject, $bodyText, $bodyHtml);

        $options = XenForo_Application::get('options');

        $app_ios_id = $options->tp_app_ios_id;
        $app_android_id = $options->tp_android_url;
        if($app_ios_id && $app_ios_id != -1 || $app_android_id && $app_android_id != -1){
            return $result;
        }

        if (!isset($options->deep_link_in_sub_emails) || empty($options->deep_link_in_sub_emails)){
            return $result;
        } else if ($options->deep_link_in_sub_emails == 1  && isset($this->_params['receiver']['user_id'])){
            $tapatalkUser_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');
            $tapatalk_user = $tapatalkUser_model->getTapatalkUserById($this->_params['receiver']['user_id']);
            if (empty($tapatalk_user)){
                return $result;
            }
        }

        $api_key = $options->tp_push_key;
        $board_url = $options->boardUrl;
        $bodyText = $result['bodyText'];
        $bodyHtml = $result['bodyHtml'];

        switch ($this->_emailTitle){
            case 'watched_thread_reply_messagetext':
            case 'watched_forum_thread_messagetext':
            case 'watched_thread_reply':
            case 'watched_forum_thread':
                $view = new XenForo_Phrase('view_this_thread');
                $view->setLanguageId($this->_languageId);
                break;
            default:
                $view = '';
        }

        $reg = '/(<a[^>]+?href=[\'"])([^\'"]+)([\'"][^>]+>\s*)(' . $view . ')(\s*<\/a>)/';
        if (preg_match($reg, $bodyHtml, $match)){
            $text = $match[0];
            
            $tapatalk_settings = array();
            $tapatalk_fid = 0;
            $mail_last_check = 0;
            if (isset($options->tapatalk_settings) && !empty($options->tapatalk_settings)){
                $tapatalk_settings = @unserialize($options->tapatalk_settings);
                if (isset($tapatalk_settings['tapatalk_fid'])){
                    $tapatalk_fid = $tapatalk_settings['tapatalk_fid'];
                }
                if (isset($tapatalk_settings['mail_last_check'])){
                    $mail_last_check = $tapatalk_settings['mail_last_check'];
                }
            }

            $validTime = 30 * 60 * 60 * 24;
            if (time() - $mail_last_check > $validTime){
                $forum_root = dirname(dirname(dirname(__FILE__)));
                $tapatalk_dir_name = XenForo_Application::get('options')->tp_directory;
                if (file_exists($forum_root.'/'.$tapatalk_dir_name.'/lib/classTTConnection.php')){
                    include_once($forum_root.'/'.$tapatalk_dir_name.'/lib/classTTConnection.php');
                }
                if (class_exists('classTTConnection')){
                    $connection = new classTTConnection();
                    $url = 'http://tapatalk.com/get_forum_info.php';
                    $data = array(
                        "key" => md5($api_key),
                        "url" => $board_url,
                        "extra" => 1,
                    );

                    $response = $connection->getContentFromSever($url, $data, 'get', true);
                    if (!empty($response)){
                        $info_array = $this->handle_forum_info($response);
                        $forum_info = self::handle_forum_info($response);
                        if (isset($info_array['fid'])){
                            $tapatalk_fid = $info_array['fid'];
                            $tapatalk_settings['tapatalk_fid'] = $tapatalk_fid;
                        }
                    }
                }
                $tapatalk_settings['mail_last_check'] = time();
                $optionModel = XenForo_Model::create('XenForo_Model_Option');
                $optionModel->updateOptions(array('tapatalk_settings' => serialize($tapatalk_settings)));
            }

            if (!empty($tapatalk_fid) && isset($this->_params['thread']['thread_id'])){
                $tid = $this->_params['thread']['thread_id'];

                $deep_link = "{$board_url}?location=topic&fid={$tapatalk_fid}&tid={$tid}&channel=xf_sys_mail&type=xf";
                if (isset($this->_params['reply']['post_id']) && !empty($this->_params['reply']['post_id'])){
                    $pid = $this->_params['reply']['post_id'];
                    $deep_link = "{$board_url}?location=post&fid={$tapatalk_fid}&tid={$tid}&pid={$pid}&channel=xf_sys_mail&type=xf";
                }
                $scheme = urlencode($deep_link);
                $app_location_url = "http://tapatalk.com/m?fid={$tapatalk_fid}&openinapp={$scheme}";

                $languageModel = XenForo_Model::create('XenForo_Model_Language');
                $language = $languageModel->getLanguageById($this->_languageId);
                $languageType = $language['language_code'];
                $languageType = preg_replace('/-/', '_', $languageType);

                $view_on_tapatalk = new XenForo_Phrase("view_on_tapatalk_$languageType");
                if ($view_on_tapatalk == "view_on_tapatalk_$languageType"){
                    $view_on_tapatalk = new XenForo_Phrase("view_on_tapatalk");
                }

                $text = '<table style="min-width:175px"><tr><td>' . $text .'</td><td>'. preg_replace($reg, "$1$app_location_url$3$view_on_tapatalk$5", $text) . '</td></tr></table>';
                $result['bodyHtml'] = preg_replace($reg, $text, $bodyHtml);
            }
        }
        return $result;
    }

    public function handle_forum_info($forum_info){
        $result = array();
        if (empty($forum_info)){
            return $result;
        }
        $infos = preg_split('/\s*?\n\s*?/', $forum_info);
        foreach ($infos as $info){
            $value = preg_split('/\s*:\s*/', $info, 2);
            $result[trim($value[0])] = isset($value[1]) ? $value[1] : '';
        }
        return $result;
    }
}