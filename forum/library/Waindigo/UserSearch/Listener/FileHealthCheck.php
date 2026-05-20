<?php

class Waindigo_UserSearch_Listener_FileHealthCheck
{

    public static function fileHealthCheck(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes = array_merge($hashes,
            array(
                'library/Waindigo/UserSearch/Extend/XenForo/ControllerPublic/Search.php' => 'a410accfeb0a46a17dd0cb0321f9848d',
                'library/Waindigo/UserSearch/Extend/XenForo/DataWriter/User.php' => '905cf8755b7f815ad0b4801976c59c1e',
                'library/Waindigo/UserSearch/Extend/XenForo/Model/User.php' => '69e6cc3dba1dbcf62f16e930ca364619',
                'library/Waindigo/UserSearch/Install/Controller.php' => '80dfc860ebed603b032288be894355a7',
                'library/Waindigo/UserSearch/Listener/LoadClass.php' => 'c820b2a9187a9079e1673bfc7a755c01',
                'library/Waindigo/UserSearch/Listener/TemplateCreate.php' => '1aca5d1781b296260919848f9f60ed7f',
                'library/Waindigo/UserSearch/Listener/TemplateHook.php' => '4fe5d766e67de52376dd1c419a713d77',
                'library/Waindigo/UserSearch/Option/UserGroupChooser.php' => 'b9b6fe3dfd43166b02e71ee89d6a8861',
                'library/Waindigo/UserSearch/Option/UserUpgradeChooser.php' => '37c942455fd076d8521c4172df605088',
                'library/Waindigo/UserSearch/Search/DataHandler/User.php' => 'b5438a59d437ceff34e561317fad43f4',
                'library/Waindigo/Install.php' => '00d8b93ea3458f18752c348a09a16c50',
                'library/Waindigo/Install/20150101.php' => '57a34ae9288bb314c0d357e8c0aa3fe0',
                'library/Waindigo/Deferred.php' => '4649953c0a44928b5e2d4a86e7d3f48a',
                'library/Waindigo/Deferred/20130725.php' => '699fb7a47bd443d53cb14f524321175a',
                'library/Waindigo/Listener/ControllerPreDispatch.php' => 'f51aeb4ef6c4acbce629188b04cd3643',
                'library/Waindigo/Listener/ControllerPreDispatch/20141226.php' => '1fcffd0dc3050b0bcb5b6e3b16f53019',
                'library/Waindigo/Listener/InitDependencies.php' => '5b755bcc0e553351c40871f4181ce5b0',
                'library/Waindigo/Listener/InitDependencies/20150101.php' => '21c224866b2ea0b90dee32a6658fef5d',
                'library/Waindigo/Listener/LoadClass.php' => 'bfdfe90f8d484d81b05889037a4fb091',
                'library/Waindigo/Listener/LoadClass/20150101.php' => '04b2dfaf2b319a5a3deb90db0018d1cc',
                'library/Waindigo/Listener/Template.php' => 'b52cba9c298d9702b4536146d3ac4312',
                'library/Waindigo/Listener/Template/20150101.php' => '120172c186efb3f25ce2ceeb7dbc8f05',
                'library/Waindigo/Listener/TemplateCreate.php' => 'db5c0d5eb8c65b1840dd437e5cca69d6',
                'library/Waindigo/Listener/TemplateCreate/20130522.php' => 'd382f6f3a2a4e8c06d665b6af1365808',
                'library/Waindigo/Listener/TemplateHook.php' => '37c6a882bfb9d790801c94051fe3eb0d',
                'library/Waindigo/Listener/TemplateHook/20141020.php' => '7cea585f0284789f639fd08dbc1679b6',
            ));
    } /* END fileHealthCheck */
}