<?php

class Waindigo_SearchAndExport_Listener_FileHealthCheck
{

    public static function fileHealthCheck(XenForo_ControllerAdmin_Abstract $controller, array &$hashes)
    {
        $hashes = array_merge($hashes,
            array(
                'library/Waindigo/SearchAndExport/ControllerAdmin/SearchExportProfile.php' => 'f2b0ffaf388cb1c71ae802855aaf711d',
                'library/Waindigo/SearchAndExport/DataWriter/SearchExportProfile.php' => 'df287418e5d6551199f43800ec20bad3',
                'library/Waindigo/SearchAndExport/Extend/Waindigo/UserSearch/Search/DataHandler/User.php' => '4d5c3e485fb71304fbae5e5892f81308',
                'library/Waindigo/SearchAndExport/Extend/XenForo/ControllerPublic/Search.php' => 'd7491f90675b3623873f8fd04a988276',
                'library/Waindigo/SearchAndExport/Extend/XenForo/Model/Search.php' => 'c20d9c624da0d4f9a9e1ee74e39875d8',
                'library/Waindigo/SearchAndExport/Install/Controller.php' => 'de791a8924580707d37ec210853d5d32',
                'library/Waindigo/SearchAndExport/Listener/LoadClass.php' => '9597e5263474743e449ed2f6b20aaaa1',
                'library/Waindigo/SearchAndExport/Model/SearchExportProfile.php' => 'd4e59cdce590eef8d37a7a8a1ae0968b',
                'library/Waindigo/SearchAndExport/Option/VisibleColumns.php' => '13c74dcd40a418cbb15fced12719ea55',
                'library/Waindigo/SearchAndExport/Route/PrefixAdmin/SearchExportProfiles.php' => '025dba4b4518c5f8b1288369a12ac301',
                'library/Waindigo/SearchAndExport/Search/ExportHandler/Abstract.php' => '86b438733396aa96871546eb032aaa14',
                'library/Waindigo/SearchAndExport/Search/ExportHandler/User.php' => '6a03bf04e427f3f9ff6f4d54b34419f5',
                'library/Waindigo/SearchAndExport/ViewPublic/Search/Export.php' => 'f508c4cdb4fab82775a53bad04e13bc8',
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
            ));
    } /* END fileHealthCheck */
}