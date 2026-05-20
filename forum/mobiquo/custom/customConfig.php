<?php
use Tapatalk\Bridge;

defined('MBQ_IN_IT') or exit;

function mbqInitGetConfigValues($isTTServerCall = false)
{
    /**
     * user custom config,to replace some config of MbqMain::$oMbqConfig->cfg.
     * you can change any config if you need,please refer to MbqConfig.php for more details.
     */
    $bridge  = Bridge::getInstance();
    $visitor = $bridge->getVisitor();
    $options = $bridge->options();

    $addOnRepo = $bridge->getAddOnRepo();
    $tapatalk_addon = $addOnRepo->finder('XF:AddOn')->where([
        'addon_id' => 'Tapatalk'
    ])->fetchOne();

    $permissionEntryRepo = $bridge->getPermissionEntryRepo();
    $permissionSet = $bridge->getPermission();

    $permissions = $permissionEntryRepo->getGlobalUserGroupPermissionEntries(1); //guests
    $is_board_active = $options->boardActive;
    $guest_permission['permission_value'] = false;
    if (isset($permissions['general']) && isset($permissions['general']['view'])) {
        $guest_permission['permission_value'] = $permissions['general']['view']; // allow
    }

    MbqMain::$customConfig['base']['version'] = 'xf20_1.2.9';
    MbqMain::$customConfig['base']['api_level'] = 4;
    MbqMain::$customConfig['base']['json_support'] = function_exists("json_encode") && function_exists("json_decode") ? MbqBaseFdt::getFdt('MbqFdtConfig.base.json_support.range.yes') :  MbqBaseFdt::getFdt('MbqFdtConfig.base.json_support.range.no');
    MbqMain::$customConfig['base']['inbox_stat'] = MbqBaseFdt::getFdt('MbqFdtConfig.base.inbox_stat.range.support');

    $config_version =  trim(str_replace('xf20_', '', MbqMain::$customConfig['base']['version']));
    MbqMain::$customConfig['base']['hook_version'] = 'xf20_' . ($tapatalk_addon['version_string'] ?: '');
    $is_open = false;
    if($tapatalk_addon && isset($tapatalk_addon['version_string']) && trim($tapatalk_addon['version_string']) <= $config_version )
    {
        $is_open = true;
    }
    else
    {
        $result_text = "Tapatalk add-on version error. It may affect some app features in this forum. Please inform the forum admin to complete the installation.";
        MbqMain::$customConfig['base']['result_text'] = $result_text;
    }
    if(($is_board_active || $visitor->get('is_admin')) && $is_open && $tapatalk_addon['active'])
    {
        MbqMain::$customConfig['base']['is_open'] = MbqBaseFdt::getFdt('MbqFdtConfig.base.is_open.range.yes');
    }
    else
    {
        MbqMain::$customConfig['base']['is_open'] = MbqBaseFdt::getFdt('MbqFdtConfig.base.is_open.range.no');
        if(!$is_board_active)
        {
            MbqMain::$customConfig['base']['result_text'] = $options->boardInactiveMessage;
        }
    }
    MbqMain::$customConfig['base']['sys_version'] = $bridge::getXFVersionId();
    MbqMain::$customConfig['base']['announcement'] = MbqBaseFdt::getFdt('MbqFdtConfig.base.announcement.range.support');
    MbqMain::$customConfig['base']['push'] = 1;
    MbqMain::$customConfig['base']['push_type'] = 'conv,sub,quote,newtopic,tag,newsub,like';

    $oMbqRdCommon = MbqMain::$oClk->newObj('MbqRdCommon');
    if($isTTServerCall)
    {
        MbqMain::$customConfig['base']['release_timestamp'] = 1463490207;
        MbqMain::$customConfig['base']['push_slug'] = json_encode($oMbqRdCommon->getPushSlug());
        MbqMain::$customConfig['base']['smartbanner_info'] = json_encode($oMbqRdCommon->getSmartbannerInfo());

        $boardTotals  = $bridge->getDataRegistry()->get('boardTotals');
        if (!$boardTotals)
        {
            $boardTotals = $bridge->getCountersRepo()->rebuildForumStatisticsCache();
        }
        MbqMain::$customConfig['custom']['stats'] = array(
            'topic'    => $boardTotals['threads'],
            'messages' => $boardTotals['messages'],
            'user'     => $boardTotals['users'],
        );

    }
    if(isset($_SERVER['HTTP_X_TAPATALK_WEB']))
    {
        MbqMain::$customConfig['base']['smartbanner_info'] = json_encode($oMbqRdCommon->getSmartbannerInfo());
    }
    if(isset($options->tp_push_key) && !empty($options->tp_push_key))
    {
        MbqMain::$customConfig['base']['api_key'] = md5($options->tp_push_key);
    }
    else
    {
        MbqMain::$customConfig['base']['api_key'] = "";
    }
    MbqMain::$customConfig['base']['set_api_key'] = 1;
    MbqMain::$customConfig['base']['set_forum_info'] = 1;
    MbqMain::$customConfig['base']['user_subscription'] = 1;
    MbqMain::$customConfig['base']['push_content_check'] = 1;
    if (!isset($options->banner_control)){
        MbqMain::$customConfig['base']['banner_control'] = -1;
    } else{
        MbqMain::$customConfig['base']['banner_control'] = $options->banner_control;
    }
    MbqMain::$customConfig['base']['reset_push_slug'] = 1;
    MbqMain::$customConfig['base']['ads_disabled_group'] = isset($options->ads_disabled_for_group) && !empty($options->ads_disabled_for_group) && implode(',', $options->ads_disabled_for_group) != "0" ? implode(',', $options->ads_disabled_for_group) : "";

    MbqMain::$customConfig['subscribe']['module_enable'] = MbqBaseFdt::getFdt('MbqFdtConfig.subscribe.module_enable.range.enable');

    MbqMain::$customConfig['user']['user_id'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.user_id.range.support');

    MbqMain::$customConfig['user']['guest_okay'] = isset($guest_permission['permission_value']) && $guest_permission['permission_value'] == 'allow' ? MbqBaseFdt::getFdt('MbqFdtConfig.user.guest_okay.range.support') : MbqBaseFdt::getFdt('MbqFdtConfig.user.guest_okay.range.notSupport');
    MbqMain::$customConfig['user']['search_user'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.search_user.range.support');
    MbqMain::$customConfig['user']['ignore_user'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.ignore_user.range.support');
    MbqMain::$customConfig['user']['emoji_support'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.emoji_support.range.support');
    MbqMain::$customConfig['user']['advanced_online_users'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.advanced_online_users.range.support');
    MbqMain::$customConfig['user']['guest_whosonline'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.guest_whosonline.range.support');
    MbqMain::$customConfig['user']['two_step'] = $options->TT_2fa_enabled == 1 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.two_step.range.support') : MbqBaseFdt::getFdt('MbqFdtConfig.user.two_step.range.notSupport');
    MbqMain::$customConfig['user']['unban'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.unban.range.support');
    MbqMain::$customConfig['user']['ban_expires'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.ban_expires.range.support');
    MbqMain::$customConfig['user']['guest_group_id'] = $visitor::GROUP_GUEST;
    MbqMain::$customConfig['user']['get_ignored_users'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.get_ignored_users.range.support');


    MbqMain::$customConfig['user']['anonymous'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.anonymous.range.support');
    MbqMain::$customConfig['user']['login_with_email'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.login_with_email.range.support');
    MbqMain::$customConfig['user']['avatar'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.avatar.range.support');
    MbqMain::$customConfig['user']['upload_avatar'] = MbqBaseFdt::getFdt('MbqFdtConfig.user.upload_avatar.range.support');
    MbqMain::$customConfig['forum']['no_refresh_on_post'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.no_refresh_on_post.range.support');
    MbqMain::$customConfig['forum']['get_latest_topic'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_latest_topic.range.support');
    MbqMain::$customConfig['forum']['guest_search'] = $visitor->canSearch() ? MbqBaseFdt::getFdt('MbqFdtConfig.forum.guest_search.range.support') : MbqBaseFdt::getFdt('MbqFdtConfig.forum.guest_search.range.notSupport');
    MbqMain::$customConfig['forum']['mark_read'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mark_read.range.support');
    MbqMain::$customConfig['forum']['mark_topic_read'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mark_topic_read.range.support');
    MbqMain::$customConfig['forum']['report_post'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.report_post.range.support');
    MbqMain::$customConfig['forum']['goto_post'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.report_post.range.support');
    MbqMain::$customConfig['forum']['goto_unread'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.goto_unread.range.support');
    MbqMain::$customConfig['forum']['can_unread'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.can_unread.range.support');
    MbqMain::$customConfig['forum']['first_unread'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.first_unread.range.support');
    MbqMain::$customConfig['forum']['get_id_by_url'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_id_by_url.range.support');
    MbqMain::$customConfig['forum']['mark_forum'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mark_forum.range.support');
    MbqMain::$customConfig['forum']['mod_approve'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mod_approve.range.support');
    MbqMain::$customConfig['forum']['mod_report'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mod_report.range.support');
    MbqMain::$customConfig['forum']['mod_delete'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.mod_delete.range.notSupport');
    MbqMain::$customConfig['forum']['multi_quote'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.multi_quote.range.support');
    MbqMain::$customConfig['forum']['advanced_move'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advanced_move.range.support');
    MbqMain::$customConfig['forum']['get_participated_forum'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_participated_forum.range.support');
    MbqMain::$customConfig['forum']['advanced_delete'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advanced_delete.range.support');
    MbqMain::$customConfig['forum']['search_started_by'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.search_started_by.range.support');
    MbqMain::$customConfig['forum']['get_id_by_url'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_id_by_url.range.support');
    MbqMain::$customConfig['forum']['get_url_by_id'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_url_by_id.range.support');
    MbqMain::$customConfig['forum']['advance_subscribe_topic'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advance_subscribe_topic.range.support');
    MbqMain::$customConfig['forum']['advance_subscribe_forum'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advance_subscribe_forum.range.support');
    MbqMain::$customConfig['forum']['get_topic_by_ids'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.get_topic_by_ids.range.support');

    //MbqMain::$customConfig['forum']['min_search_length'] = (int)$config['fulltext_native_min_chars'];
    MbqMain::$customConfig['forum']['advanced_search'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advanced_search.range.support');
    MbqMain::$customConfig['forum']['subscribe_forum'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.subscribe_forum.range.support');
    MbqMain::$customConfig['forum']['subscribe_load'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.subscribe_load.range.support');
    MbqMain::$customConfig['forum']['alert'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.alert.range.support');

    MbqMain::$customConfig['pc']['module_enable'] = MbqBaseFdt::getFdt('MbqFdtConfig.pc.module_enable.range.enable');
    MbqMain::$customConfig['pc']['conversation'] = MbqBaseFdt::getFdt('MbqFdtConfig.pc.conversation.range.support');
    MbqMain::$customConfig['pm']['mark_pm_unread'] = MbqBaseFdt::getFdt('MbqFdtConfig.pm.mark_pm_unread.range.support');
    MbqMain::$customConfig['pm']['mark_pm_read'] = MbqBaseFdt::getFdt('MbqFdtConfig.pm.mark_pm_read.range.support');

    $mobiquo_config['sign_in'] = 1;
    $mobiquo_config['inappreg'] = 1;
    $mobiquo_config['sso_login'] = 1;
    $mobiquo_config['sso_signin'] = 1;
    $mobiquo_config['sso_register'] = 1;
    $mobiquo_config['native_register'] = 1;

    $register_setup = $options->registrationSetup;
    if(!isset($register_setup['enabled']) || empty($register_setup['enabled']))
    {
        $mobiquo_config['inappreg'] = 0;
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['sso_register'] = 0;
        $mobiquo_config['native_register'] = 0;
    }
    if (!function_exists('curl_init') && !@ini_get('allow_url_fopen'))
    {
        $mobiquo_config['inappreg'] = 0;
        $mobiquo_config['sso_login'] = 0;
        $mobiquo_config['sso_signin'] = 0;
    }
    if (isset($options->tapatalk_reg_type) && $options->tapatalk_reg_type != 0)
    {
        $mobiquo_config['inappreg'] = 0;
        $mobiquo_config['sso_signin'] = 0;
        $mobiquo_config['native_register'] = 0;
        $mobiquo_config['sso_register'] = 0;

    }
    if (isset($options->advanced_delete) && $options->advanced_delete != 1) {
        MbqMain::$customConfig['forum']['advanced_delete'] = MbqBaseFdt::getFdt('MbqFdtConfig.forum.advanced_delete.range.notSupport');
    }

    MbqMain::$customConfig['user']['sign_in'] = $mobiquo_config['sign_in'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.sign_in.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.sign_in.range.support');
    MbqMain::$customConfig['user']['inappreg'] = $mobiquo_config['inappreg'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.inappreg.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.inappreg.range.support');
    MbqMain::$customConfig['user']['sso_login'] = $mobiquo_config['sso_login'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_login.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_login.range.support');
    MbqMain::$customConfig['user']['sso_signin'] = $mobiquo_config['sso_signin'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_signin.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_signin.range.support');
    MbqMain::$customConfig['user']['sso_register'] = $mobiquo_config['sso_register'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_register.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.sso_register.range.support');
    MbqMain::$customConfig['user']['native_register'] = $mobiquo_config['native_register'] == 0 ? MbqBaseFdt::getFdt('MbqFdtConfig.user.native_register.range.notSupport') : MbqBaseFdt::getFdt('MbqFdtConfig.user.native_register.range.support');
}
