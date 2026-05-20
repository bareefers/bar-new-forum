<?php
$rootDir = XenForo_Autoloader::getInstance()->getRootDir();

$version = 0;
if ($handle = opendir($rootDir . '/Waindigo/Listener/TemplateCreate')) {
    while (false !== ($entry = readdir($handle))) {
        if (intval($entry) > $version) {
            $version = intval($entry);
        }
    }
}

require_once $rootDir . '/Waindigo/Listener/TemplateCreate/' . $version . '.php';