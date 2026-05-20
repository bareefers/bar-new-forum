<?php

namespace Tapatalk\Listener;

class TemplatePostRender
{
    public static function template_post_render(\XF\Template\Templater $templater, $type, $template, &$output)
    {
        $app = \XF::app();
        $option = $app->options();

        $originOutput = $output;
        if ($template == 'conversation_view' || $template == 'thread_view')
        {

            $output = preg_replace('/\[emoji(\d+)\]/i', '<img src="//emoji.tapatalk-cdn.com/emoji\1.png" />', $output);
            $output = preg_replace('/https?:\/\/cloud\.tapatalk\.com/i',  '//cloud.tapatalk.com', $output);
            $output = preg_replace('/https?:\/\/uploads\.tapatalk-cdn\.com/i',  '//uploads.tapatalk-cdn.com', $output);
            $output = preg_replace('/https?:\/\/images\.tapatalk-cdn\.com/i', '//images.tapatalk-cdn.com', $output);
            if(empty($output)) {
                $output = $originOutput;
            }
            try {
                $TT_bannerControlData = isset($option->tapatalk_banner_control) ? $option->tapatalk_banner_control : array('banner_enable' => -1);
                if (!$TT_bannerControlData) {
                    return;
                }
                if (!is_array($TT_bannerControlData) && $TT_bannerControlData) {
                    $TT_bannerControlData = unserialize($TT_bannerControlData);
                }

                if ((isset($TT_bannerControlData['file_redirect']) && $TT_bannerControlData['file_redirect'] != 0) || (isset($TT_bannerControlData['image_redirect']) && $TT_bannerControlData['image_redirect'] != 0)) {
                    $tapatalk_dir_name = isset($option->tp_directory) ? $option->tp_directory : '';
                    if (empty($tapatalk_dir_name)) {
                        $tapatalk_dir_name = 'mobiquo';
                    }
                    $boardUrl = self::getBoardUrl();
                    $tapatalkPluginUrl = rtrim($boardUrl, '/') . '/' . $tapatalk_dir_name;

                    $TT_fid = isset($TT_bannerControlData['forum_id']) ? $TT_bannerControlData['forum_id'] : '';
                    $showAndroidIcon = true;
                    $showIOSIcon = true;
                    $androidAppUrl = 'https://play.google.com/store/apps/details?id=com.quoord.tapatalkpro.activity';
                    $iosAppUrl = 'https://itunes.apple.com/us/app/tapatalk-discussions-chat/id307880732?mt=8';
                    $isByo = false;

                    if (isset($TT_bannerControlData['byo_info']) && !empty($TT_bannerControlData['byo_info'])) {
                        if (isset($TT_bannerControlData['byo_info']['app_android_id']) && !empty($TT_bannerControlData['byo_info']['app_android_id'])) {
                            $isByo = true;
                            $androidAppUrl = "https://tapatalk.com/m/?id=6&app_android_id=" . $TT_bannerControlData['byo_info']['app_android_id'] . "&referer=" . tttpr_get_board_url();
                        } else {
                            $showAndroidIcon = false;
                        }

                        if (isset($TT_bannerControlData['byo_info']['app_ios_id']) && !empty($TT_bannerControlData['byo_info']['app_ios_id'])) {
                            $isByo = true;
                            $iosAppUrl = "https://tapatalk.com/m/?id=6&app_ios_id=" . $TT_bannerControlData['byo_info']['app_ios_id'] . "&referer=" . tttpr_get_board_url();
                        } else {
                            $showIOSIcon = false;
                        }
                    }

                    $TT_EmbebedScript = "<script>
                    var TTE_fid = $TT_fid;
                    var TTE_isByo = " . ($isByo ? "1" : "0") . ";
                    var TTE_showAndroidIcon = " . ($showAndroidIcon ? "1" : "0") . ";
                    var TTE_showIOSIcon = " . ($showIOSIcon ? "1" : "0") . ";
                    var TTE_androidAppUrl = " . json_encode($androidAppUrl) . ";
                    var TTE_iosAppUrl = " . json_encode($iosAppUrl) . ";
                    var TTE_tapatalkPluginUrl = " . json_encode($tapatalkPluginUrl) . ";
                    </script>
                    <script src='//media.tapatalk-cdn.com/tapatalk-js/embebed-xenforo.js?s=" . time() . "'></script>
                    ";

                    $output.= $TT_EmbebedScript;
                }
            }catch (\Exception $e){}

        }else if ($template == 'online_list')
        {

            $memberListItems = preg_split('/<li class="primaryContent memberListItem">/', $output);
            if (!empty($memberListItems)) {
                $tapatalk_dir_name = isset($option->tp_directory) ? $option->tp_directory : '';
                if (empty($tapatalk_dir_name)) {
                    $tapatalk_dir_name = 'mobiquo';
                }
                foreach ($memberListItems as $key => $memberItem) {

                    if (preg_match('/\[On Tapatalk\]/', $memberItem)) {
                        $memberItem = preg_replace('/\[On Tapatalk\]/', '', $memberItem);
                        $memberItem = preg_replace('/<div class="extra">/', '<div class="extra">
                        <img src="' . $tapatalk_dir_name . '/forum_icons/tapatalk-online.png">', $memberItem);

                    } else if (preg_match('/\[On BYO\]/', $memberItem)) {

                        $memberItem = preg_replace('/\[On BYO\]/', '', $memberItem);
                        $memberItem = preg_replace('/<div class="extra">/', '<div class="extra">
                        <img src="' . $tapatalk_dir_name . '/forum_icons/tapatalk-online.png">', $memberItem);
                    }
                    if ($key == 0) {
                        $output = $memberItem;
                    } else {
                        $output .= '<li class="primaryContent memberListItem">' . $memberItem;
                    }
                }
            }

        }
    }

    protected static function getBoardUrl()
    {
        $app = \XF::app();
        $request = $app->request();
        $boardUrl = $request->getFullBasePath();
        if ($boardUrl) {
            return $boardUrl;
        }
        $homePageUrl = $app->container('homePageUrl');
        // $pather = $app->container('request.pather');

        if ($homePageUrl) {
            return $homePageUrl;
        }
        return $app->options()->boardUrl;
    }


}