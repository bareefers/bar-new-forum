<?php

class Waindigo_Listener_InitDependencies
{

    public static $copyrightYear = '2015';

    /**
     * Standard approach to caching other model objects for the lifetime of the
     * model.
     *
     * @var array
     */
    protected $_modelCache = array();

    /**
     *
     * @var XenForo_Dependencies_Abstract
     */
    protected static $_dependencies = null;

    protected static $_data = array();

    protected static $_runOnce = false;

    const JUST_INSTALLED_SIMPLE_CACHE_KEY = 'waindigo_justInstalled';

    const JUST_UNINSTALLED_SIMPLE_CACHE_KEY = 'waindigo_justUninstalled';

    const COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY = 'waindigo_copyrightModification';

    /**
     *
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param array $data
     */
    public function __construct(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        if (is_null(self::$_dependencies))
            self::$_dependencies = $dependencies;
        if (empty(self::$_data))
            self::$_data = $data;
    } /* END __construct */

    /**
     * Called when the dependency manager loads its default data.
     * This event is fired on virtually every page and is the first thing you
     * can plug into.
     *
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param array $data
     */
    public static function initDependencies(XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        if (function_exists('get_called_class')) {
            $class = get_called_class();
        } else {
            $class = get_class();
        }
        if (function_exists('get_called_class')) {
            $className = get_called_class();
        } else {
            $className = get_class();
        }

        self::createAndRun($className, $dependencies, $data);
    } /* END initDependencies */

    public function run()
    {
        if (!self::$_runOnce) {
            $this->_runOnce();
        }

        $this->_run();
    } /* END run */

    /**
     * Method designed to be overridden by child classes to add run behaviours.
     */
    protected function _run()
    {
    } /* END _run */

    protected function _runOnce()
    {
        $this->_checkJustInstalled();

        $this->_rebuildLoadClassHintsCache();

        $this->_checkCopyrightModification();

        $cpdListeners = XenForo_CodeEvent::getEventListeners('controller_pre_dispatch');
        if ($cpdListeners) {
            $this->_getLibraryListenerFileVersion('ControllerPreDispatch');
        }

        self::$_runOnce = true;
    } /* END _runOnce */

    /**
     * Gets the specified model object from the cache.
     * If it does not exist, it will be instantiated.
     *
     * @param string $class Name of the class to load
     *
     * @return XenForo_Model
     */
    public function getModelFromCache($class)
    {
        if (!isset($this->_modelCache[$class])) {
            $this->_modelCache[$class] = XenForo_Model::create($class);
        }

        return $this->_modelCache[$class];
    } /* END getModelFromCache */

    /**
     *
     * @param array $helperCallbacks
     */
    public function addHelperCallbacks(array $helperCallbacks)
    {
        XenForo_Template_Helper_Core::$helperCallbacks = array_merge(XenForo_Template_Helper_Core::$helperCallbacks,
            $helperCallbacks);
    } /* END addHelperCallbacks */

    /**
     *
     * @param array $cacheRebuilders
     */
    public function addCacheRebuilders(array $cacheRebuilders)
    {
        if (self::$_dependencies instanceof XenForo_Dependencies_Admin) {
            XenForo_CacheRebuilder_Abstract::$builders = array_merge(XenForo_CacheRebuilder_Abstract::$builders,
                $cacheRebuilders);
        }
    } /* END addCacheRebuilders */

    protected function _checkJustInstalled()
    {
        $justInstalled = XenForo_Application::getSimpleCacheData(self::JUST_INSTALLED_SIMPLE_CACHE_KEY);

        if ($justInstalled) {
            $db = XenForo_Application::get('db');

            foreach ($justInstalled as $addOnId) {
                if (method_exists('Waindigo_Install', 'postInstall')) {
                    if (Waindigo_Install::postInstall(array(
                        'addon_id' => $addOnId
                    )) === false) {
                        return false;
                    }
                }
                if (XenForo_Application::$versionId < 1020000) {
                    $db->delete('xf_code_event_listener',
                        'addon_id = ' . $db->quote($addOnId) . ' AND event_id = \'load_class\'');
                    $db->update('xf_code_event_listener',
                        array(
                            'active' => 1
                        ), 'addon_id = ' . $db->quote($addOnId) . ' AND event_id LIKE \'load_class_%\'');
                    $db->update('xf_code_event_listener',
                        array(
                            'active' => 1
                        ), 'addon_id = ' . $db->quote($addOnId) . ' AND event_id LIKE \'template_%\'');
                }
            }

            if (XenForo_Application::$versionId < 1020000) {
                /* @var $codeEventModel XenForo_Model_CodeEvent */
                $codeEventModel = $this->getModelFromCache('XenForo_Model_CodeEvent');

                $codeEventModel->rebuildEventListenerCache();
            }

            XenForo_Application::setSimpleCacheData(self::JUST_INSTALLED_SIMPLE_CACHE_KEY, array());
        }
    } /* END _checkJustInstalled */

    protected function _checkJustUninstalled()
    {
        $justUninstalled = XenForo_Application::getSimpleCacheData(self::JUST_UNINSTALLED_SIMPLE_CACHE_KEY);

        if ($justUninstalled) {
            $db = XenForo_Application::get('db');

            foreach ($justUninstalled as $addOnId) {
                if (method_exists('Waindigo_Install', 'postUninstall')) {
                    if (Waindigo_Install::postUninstall(array(
                        'addon_id' => $addOnId
                    )) === false) {
                        return false;
                    }
                }
            }

            XenForo_Application::setSimpleCacheData(self::JUST_UNINSTALLED_SIMPLE_CACHE_KEY, array());
        }
    } /* END _checkJustUninstalled */

    protected function _rebuildLoadClassHintsCache()
    {
        if (XenForo_Application::$versionId < 1020000) {
            return;
        }

        $newLoadClassHints = array(
            'XenForo_ControllerPublic_Misc' => array()
        );

        XenForo_Application::get('options')->set('waindigo_loadClassHints', $newLoadClassHints);

        XenForo_CodeEvent::addListener('load_class', 'Waindigo_Listener_LoadClass', 'XenForo_ControllerPublic_Misc');
    } /* END _rebuildLoadClassHintsCache */

    protected function _checkCopyrightModification()
    {
        $copyrightModification = XenForo_Application::getSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY);

        if ($copyrightModification && $copyrightModification < XenForo_Application::$time - 7 * 24 * 60 * 60) {
            XenForo_Application::get('db')->beginTransaction();
            XenForo_Application::setSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY, 0);

            $styles = $this->getModelFromCache('XenForo_Model_Style')->getAllStyles();
            $styleIds = array_merge(array(
                0
            ), array_keys($styles));
            foreach ($styleIds as $styleId) {
                $this->getModelFromCache('XenForo_Model_Template')->compileNamedTemplateInStyleTree('footer', $styleId);
            }
            XenForo_Application::get('db')->commit();
        }
    } /* END _checkCopyrightModification */

    /**
     *
     * @param array $matches
     * @return string
     */
    public static function copyrightNotice(array $matches)
    {
        $copyrightModification = XenForo_Application::getSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY);

        if ($copyrightModification < XenForo_Application::$time) {
            XenForo_Application::setSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY,
                XenForo_Application::$time);
        }

        return $matches[0] .
             '
            <xen:if is="(strpos({$controllerName}, \'Waindigo\') === 0 || ({$xenOptions.waindigo_loadClassHints} && array_key_exists({$controllerName}, {$xenOptions.waindigo_loadClassHints}))) && !{$waindigoCopyrightShown}">' .
             '<xen:set var="$waindigoCopyrightShown">1</xen:set><br/>' .
             '<xen:if is="{$xenAddOns.Waindigo_InstallUpgrade} >= 1402580817">' .
             '<xen:include template="waindigo_copyright_notice_installupgrade" />' . '<xen:else />' .
             '<div id="waindigoCopyrightNotice"><a href="https://waindigo.org" class="concealed">' .
             'XenForo add-ons by Waindigo&trade;</a> <span>&copy;' . self::getCopyrightYear() .
             ' <a href="https://waindigo.org" class="concealed">' . 'Waindigo Ltd</a>.</span></div>' . '</xen:if>' .
             '</xen:if>';
    } /* END copyrightNotice */

    /**
     *
     * @param array $matches
     * @return string
     */
    public static function removeCopyrightNotice(array $matches)
    {
        $copyrightModification = XenForo_Application::getSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY);

        if ($copyrightModification < XenForo_Application::$time) {
            XenForo_Application::setSimpleCacheData(self::COPYRIGHT_MODIFICATION_SIMPLE_CACHE_KEY,
                XenForo_Application::$time);
        }

        return $matches[0];
    } /* END removeCopyrightNotice */

    /**
     *
     * @param string $filename
     * @param boolean $autoload
     * @return number
     */
    protected function _getLibraryListenerFileVersion($filename, $autoload = true)
    {
        $rootDir = XenForo_Autoloader::getInstance()->getRootDir();

        $version = 0;
        $handle = opendir($rootDir . '/Waindigo/Listener/' . $filename);
        if ($handle) {
            while (false !== ($entry = readdir($handle))) {
                if (intval($entry) > $version) {
                    $version = intval($entry);
                }
            }
            if ($autoload) {
                require_once $rootDir . '/Waindigo/Listener/' . $filename . '/' . $version . '.php';
            }
        }

        return $version;
    } /* END _getLibraryListenerFileVersion */

    public static function getCopyrightYear()
    {
        return self::$copyrightYear;
    } /* END getCopyrightYear */

    /**
     * Factory method to get the named init dependencies listener.
     * The class must exist or be autoloadable or an exception will be thrown.
     *
     * @param string $className Class to load
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param array $data
     *
     * @return Waindigo_Listener_InitDependencies
     */
    public static function create($className, XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        $createClass = XenForo_Application::resolveDynamicClass($className, 'listener_waindigo');
        if (!$createClass) {
            throw new XenForo_Exception("Invalid listener '$className' specified");
        }

        return new $createClass($dependencies, $data);
    } /* END create */

    /**
     *
     * @param string $className Class to load
     * @param XenForo_Dependencies_Abstract $dependencies
     * @param array $data
     */
    public static function createAndRun($className, XenForo_Dependencies_Abstract $dependencies, array $data)
    {
        $createClass = self::create($className, $dependencies, $data);

        if (XenForo_Application::debugMode()) {
            $createClass->run();
        }
        try {
            $createClass->run();
        } catch (Exception $e) {
            // do nothing
        }
    } /* END createAndRun */
}