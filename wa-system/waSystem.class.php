<?php

/*
 * This file is part of Webasyst framework.
 *
 * Licensed under the terms of the GNU Lesser General Public License (LGPL).
 * http://www.webasyst.com/framework/license/
 *
 * @link http://www.webasyst.com/
 * @author Webasyst LLC
 * @copyright 2011 Webasyst LLC
 * @package wa-system
 */
class waSystem
{
    protected static $instances = array();
    protected static $current = 'wa-system';

    protected static $apps;
    protected static $factories_common = array();
    protected static $factories_config = array();

    protected static $models = array();

    protected $url;

    /**
     * @var SystemConfig|waAppConfig
     */
    protected $config;
    protected $factories = array();

    protected function __construct(waSystemConfig $config)
    {
        $this->config = $config;
        try {
            $this->loadFactories();
        } catch (Exception $e) {
            echo $e;
        }

    }

    public static function isLoaded()
    {
        return self::$instances !== array();
    }

    /**
     * @return SystemConfig|waAppConfig
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @return waSystem
     */
    public static function getInstance($name = null, waSystemConfig $config = null, $set_current = false)
    {
        if ($name === null) {
            if ($config && $config instanceof waAppConfig) {
                $name = $config->getName();
            } else {
                $name = self::$current;
            }
        }

        if (!isset(self::$instances[$name])) {
            if ($config === null && self::$current) {
                $system = self::$instances[self::$current];
                $config = SystemConfig::getAppConfig($name, $system->getEnv(), $system->config->getRootPath());
            }
            if ($config) {
                self::$instances[$name] = new self($config);
                if (!self::$instances[$name] instanceof waSystem) {
                    throw new waFactoryException(sprintf('Class "%s" is not of the type waSystem.', $class));
                }
            } else {
                throw new waException(sprintf('The "%s" system does not exist.', $name));
            }
        }
        if ($set_current) {
            self::setActive($name);
        } elseif (!self::$current || self::$current == 'wa-system') {
            self::$current = $name;
        }
        return self::$instances[$name];
    }

    public static function setActive($name)
    {
        if (isset(self::$instances[$name])) {
            self::$current = $name;
            $s = self::$instances[$name];
            $s->getConfig()->setLocale($s->getLocale());
        }
    }

    public function loadFactories()
    {
        if (self::$current == 'wa-system') {
            $file_path = $this->getConfig()->getPath('config', 'factories');
            if (file_exists($file_path)) {
                self::$factories_config = include($file_path);
            }
        }
        waLocale::init();
    }

    /**
     * @return waFrontController
     */
    public function getFrontController()
    {
        return $this->getFactory('front_controller', 'waFrontController', array());
    }

    /**
     * @return waSmarty3View
     */
    public function getView($options = array())
    {
        return $this->getFactory('view', 'waSmarty3View', $options, $this);
    }

    /**
     * @return waRouting
     */
    public function getRouting($app = false)
    {
        if ($app) {
            return $this->getFactory('routing', 'waAppRouting', array(), $this);
        } else {
            return $this->getCommonFactory('routing', 'waRouting', array(), self::getInstance('wa-system'));
        }
    }

    public function getFactory($name, $class, $options = array(), $first_param = false)
    {
        if ($config = $this->getConfig()->getFactory($name)) {
            if (is_array($config)) {
                $class = $config[0];
                $options = isset($config[1]) ? $config[1] : $options;
            } else {
                $class = $config;
            }
        }
        if (!isset($this->factories[$name])) {
            if ($first_param !== false) {
                $this->factories[$name] = new $class($first_param, $options);
            } else {
                $this->factories[$name] = new $class($options);
            }
        }
        return $this->factories[$name];
    }

    public function getCommonFactory($name, $class, $options = array(), $first_param = false)
    {
        if (!isset(self::$factories_common[$name])) {
            if (isset(self::$factories_config[$name])) {
                $config = self::$factories_config[$name];
                if (is_array($config) && isset($config[0])) {
                    $class = $config[0];
                    $options = isset($config[1]) ? $config[1] : $options;
                } else {
                    $class = $config;
                }
            }
            if ($first_param !== false) {
                self::$factories_common[$name] = new $class($first_param, $options);
            } else {
                self::$factories_common[$name] = new $class($options);
            }
        }
        return self::$factories_common[$name];
    }

    /**
     * @return waAuthUser|waUser|waContact
     */
    public function getUser()
    {
        return $this->getCommonFactory('auth_user', 'waAuthUser', array(), null);
    }

    /**
     * @return waAuth
     */
    public function getAuth($provider = null, $params = array())
    {
        if ($provider) {
            $file = $this->config->getPath('system').'/auth/'.$provider.'/'.$provider.'Auth.class.php';
            if (!file_exists($file)) {
                $file = $this->config->getPath('plugins').'/auth/'.$provider.'/'.$provider.'Auth.class.php';    
            }
            if (file_exists($file)) {
                require_once($file);
                $class = $provider.'Auth';
                return new $class($params);
            } else {
                throw new waException("Auth provider not found.");
            }
        } else {
            $options = array();
            if (isset(self::$factories_config['auth'])) {
                $config = self::$factories_config['auth'];
                if (is_array($config) && isset($config[0])) {
                    $class = $config[0];
                    $options = isset($config[1]) ? $config[1] : $options;
                } else {
                    $class = $config;
                }
            } else {
                $class = 'waAuth';
            }
            return $this->getFactory('auth', $class, $options);
        }
    }
    
    public function getAuthAdapters($domain = null)
    {
        if (!$domain) {
            $domain = $this->config->getDomain();
        }
        $config = $this->getConfig()->getConfigFile('config', 'auth');
        if (!isset($config[$domain])) {
            return array();
        }
        $result = array();
        foreach ($config[$domain] as $provider => $params) {
            if ($params) {
                $result[$provider] = $this->getAuth($provider, $params);
            }
        }
        return $result;
    }

    /**
     * @return waSessionStorage
     */
    public function getStorage()
    {
        return $this->getCommonFactory('storage', 'waSessionStorage');
    }


    /**
     * @return waRequest
     */
    public function getRequest()
    {
        return $this->getCommonFactory('request', 'waRequest', array(), $this);
    }

    /**
     * @return waResponse
     */
    public function getResponse()
    {
        return $this->getCommonFactory('response', 'waResponse');
    }

    /**
     * @return waDateTime
     */
    public function getDateTime()
    {
        return $this->getCommonFactory('datetime', 'waDateTime', array(), $this);
    }

    public function getEnv()
    {
        return $this->config->getEnviroment();
    }
    
    public function login()
    {
        $class_name = $this->getConfig()->getPrefix().'LoginAction';
        if (class_exists($class_name)) {
            $controller = $this->getFactory('default_controller', 'waDefaultViewController');
            $controller->setAction($class_name);
        } else {
            $controller = new waDefaultViewController();
            // load webasyst
            self::getInstance('webasyst');
            $controller->setAction('webasystLoginAction');
        }
        $controller->run();
    }

    public function dispatch()
    {
        try {
            if (preg_match('/^sitemap-?([a-z0-9_]+)?.xml$/i', $this->config->getRequestUrl(true), $m)) {
                $app_id = isset($m[1]) ? $m[1] : 'webasyst';
                if ($this->appExists($app_id)) {
                    $system = waSystem::getInstance($app_id);
                    $class = $app_id.'SitemapConfig';
                    if (class_exists($class)) {
                        $sitemap = new $class();
                        $sitemap->execute();
                    }
                }
                throw new waException("Page not found", 404);
            } elseif (!strncmp($this->config->getRequestUrl(true), 'oauth.php', 9)) {
                $webasyst_system = waSystem::getInstance('webasyst');
                $webasyst_system->getFrontController()->execute(null, 'login', 'OAuth', true);    
            } elseif ($this->getEnv() == 'backend' && !$this->getUser()->isAuth()) {
                $webasyst_system = waSystem::getInstance('webasyst');
                $webasyst_system->getFrontController()->execute(null, 'login', waRequest::get('action'), true);
            } elseif ($this->config instanceof waAppConfig) {
                if ($this->getEnv() == 'backend' && !$this->getUser()->getRights($this->getConfig()->getApplication(), 'backend')) {
                    header("Location: ".$this->getConfig()->getBackendUrl(true));
                    exit;
                }
                $this->getFrontController()->dispatch();
            } else {
                $app = null;
                $route = null;
                if ($this->getEnv() == 'frontend') {
                    // logout
                    if (waRequest::get('logout') !== null) {
                        waSystem::getInstance()->getAuth()->clearAuth();
                        $this->getResponse()->redirect($this->config->getRequestUrl(false, true));
                    }
                    $route = $this->getRouting()->dispatch();
                    if (!$route) {
                        $this->getResponse()->redirect($this->getConfig()->getBackendUrl(true), 302);
                    }
                    $app = waRequest::param('app');
                    $app_system = waSystem::getInstance($app);
                    if (waRequest::param('secure') && !$app_system->getUser()->isAuth()) {
                        $app_system->login();
                        return;
                    }
                } else {
                    $webasyst_system = waSystem::getInstance('webasyst');
                    $path = $this->getConfig()->getRequestUrl(true);
                    if (($i = strpos($path, '?')) !== false) {
                        $path = substr($path, 0, $i);
                    }
                    $url = explode("/", $path);
                    $app = isset($url[1]) && ($url[1] != 'index.php') ? $url[1] : 'webasyst';
                }
                if (!$app) {
                    $app = 'webasyst';
                }

                $app_system = waSystem::getInstance($app, null, true);
                if ($app != 'webasyst' && $this->getEnv() == 'backend' && !$this->getUser()->getRights($app_system->getConfig()->getApplication(), 'backend')) {
                    header("Location: ".$this->getConfig()->getBackendUrl(true));
                    exit;
                }
                $app_system->getFrontController()->dispatch($route);
            }
        } catch(waApiException $e) {
            print $e;
        } catch(waException $e) {
            print $e;
        } catch(Exception $e) {
            if (waSystemConfig::isDebug()) {
                print $e;
            } else {
                $e = new waException($e->getMessage(), $e->getCode());
                print $e;
            }
        }
    }

    public function dispatchCli($argv)
    {
        $params = array();
        $app = $argv[1];
        $class = $app.ucfirst($argv[2])."Cli";
        $argv = array_slice($argv, 3);
        while ($arg = array_shift($argv)) {
            if(mb_substr($arg, 0, 2) == '--') {
                $key = mb_substr($arg, 2);
            } else if(mb_substr($arg, 0, 1) == '-') {
                $key = mb_substr($arg, 1);
            } else {
                $params[] = $arg;
                continue;
            }
            $params[$key] = trim(array_shift($argv));
        }
        waRequest::setParam($params);
        // Load system
        waSystem::getInstance('webasyst');
        // Load app
        waSystem::getInstance($app, null, true);
        if (class_exists($class)) {
            $cli = new $class();
            $cli->run();
        } else {
            throw new waException("Class ".$class." not found", 404);
        } 
    }

    public function getLocale()
    {
        return $this->getUser()->getLocale();
    }

    public function setLocale($locale)
    {
        //$this->getUser()->setLocale($locale);
        $this->getConfig()->setLocale($locale);
    }

    public function getVersion($app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }

        $app_info = $this->getAppInfo($app_id);
        $version = isset($app_info['version']) ? $app_info['version'] : '0.0.1';
        if (isset($app_info['build']) && $app_info['build']) {
            $version .= '.'.$app_info['build'];
        }
        return $version;
    }

    public function getApp()
    {
        if ($this->config instanceof waAppConfig) {
            return self::$current;
            //return $this->getConfig()->getApplication();
        } else {
            return null;
        }
    }

    public function getAppInfo($app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getApp();
        }
        if ($this->appExists($app_id)) {
            return self::$apps[$app_id];
        }
        return null;
    }

    public function getAppPath($path = null, $app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }
        return waConfig::get(($app_id=='webasyst')?'wa_path_system':'wa_path_apps').'/'.$app_id.($path ? '/'.$path : '');
    }

    public function getAppCachePath($path = null, $app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }
        if ($path) {
            $path = preg_replace('!\.\.[/\\\]!','', $path);
        }
        $file = waConfig::get('wa_path_cache').'/apps/'.$app_id.($path ? '/'.$path : '');
        return waFiles::create($file);
    }

    public function getCachePath($path = null, $app_id = null)
    {
        return $this->getAppCachePath($path, $app_id);
    }

    public function getConfigPath($app_id = null)
    {
        $path = waConfig::get('wa_path_config');
        if ($app_id) {
            $path .= '/apps/'.$app_id;
        }
        return $path;
    }


    /**
     *
     * Return path to data directory of the current application
     *
     * @param string $path - relative path in data dir
     * @param bool $public - public or protected dir
     *
     * @return string
     */
    public function getDataPath($path = null, $public = false, $app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }
        if ($path) {
            $path = preg_replace('!\.\.[/\\\]!','', $path);
        }
        $file = waConfig::get('wa_path_data').'/'.($public ? 'public' : 'protected').'/'.$app_id.($path ? '/'.$path : '');
        return waFiles::create($file);
    }

    public function getDataUrl($path = null, $public = false, $app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }
        return $this->getRootUrl().'wa-data/'.($public ? 'public' : 'protected').'/'.$app_id.($path ? '/'.$path : '');
    }


    /**
     * Return path in temp directory of the current application
     *
     * @param string $path - relative path
     */
    public function getTempPath($path = null, $app_id = null)
    {
        if ($app_id === null) {
            $app_id = $this->getConfig()->getApplication();
        }
        if ($path) {
            $path = preg_replace('!\.\.[/\\\]!','', $path);
        }
        $dir = waConfig::get('wa_path_cache').'/temp/'.$app_id.($path ? '/'.$path : '');
        waFiles::create($dir);
        return $dir;
    }

    public function getApps($system = false)
    {
        if (self::$apps === null) {
            $locale = $this->getUser()->getLocale();
            $file = $this->config->getPath('cache', 'config/apps'.$locale);
            if (!file_exists($this->getConfig()->getPath('config', 'apps'))) {
                self::$apps = array();
                throw new waException('File wa-config/apps.php not found.', 600);
            }
            if (!file_exists($file) || filemtime($file) < filemtime($this->getConfig()->getPath('config', 'apps')) || waSystemConfig::isDebug()) {
                waFiles::create($this->getConfig()->getPath('cache').'/config');
                $all_apps = include($this->getConfig()->getPath('config', 'apps'));
                $all_apps['webasyst'] = true;
                self::$apps = array();
                foreach ($all_apps as $app => $enabled) {
                    if ($enabled) {
                        waLocale::loadByDomain($app);
                        $app_config = $this->getAppPath('lib/config/app.php', $app);
                        if (!file_exists($app_config)) {
                            if (false && SystemConfig::isDebug()) {
                                throw new waException("Config not found. Create config by path ".$app_config);
                            }
                            continue;
                        }
                        $app_info = include($app_config);
                        $build_file = $app_config = $this->getAppPath('lib/config/build.php', $app);
                        if (file_exists($build_file)) {
                            $app_info['build'] = include($build_file);
                        } else {
                            if (SystemConfig::isDebug()) {
                                $app_info['build'] = time();
                            } else {
                                $app_info['build'] = 0;
                            }
                        }
                        $app_info['id'] = $app;
                        $app_info['name'] = _wd($app, $app_info['name']);
                        if (isset($app_info['img'])) {
                            $app_info['img'] = 'wa-apps/'.$app.'/'.$app_info['img'];
                        } else {
                            $app_info['img'] = 'wa-apps/'.$app.'/img/'.$app.".png";
                        }
                        self::$apps[$app] = $app_info;
                    }
                }
                if (!file_exists($file) || filemtime($file) < filemtime($this->getConfig()->getPath('config', 'apps'))) {
                    waUtils::varExportToFile(self::$apps, $file);
                }
            } else {
                self::$apps = include($file);
                waLocale::loadByDomain('webasyst');
            }
        }
        if ($system) {
            return self::$apps;
        } else {
            $apps = self::$apps;
            unset($apps['webasyst']);
            return $apps;
        }
    }


    public function appExists($app_id)
    {
        $this->getApps();
        return $app_id === 'webasyst' || isset(self::$apps[$app_id]);
    }

    public function getUrl($absolute = false)
    {
        $url = $this->config->getRootUrl($absolute);
        if ($this->config->getEnviroment() == 'backend' && ($app = $this->getApp())) {
            $url .= $this->config->getBackendUrl().'/';
            if ($app !== 'webasyst') {
                $url .= $app.'/';
            }
        }
        return $url;
    }

    public function getAppUrl($app = null)
    {
        if ($app === null) {
            $app = $this->getApp();
        }
        $url = $this->config->getRootUrl();
        if ($this->getEnv() == 'backend') {
            if ($app == 'webasyst') {
                return $url.$this->getConfig()->getBackendUrl()."/";
            } else {
                return $url.$this->getConfig()->getBackendUrl()."/".$app."/";
            }
        } else {
            return $url.$this->getRouting(true)->getRootUrl();
        }
    }

    public function getAppStaticUrl($app = null)
    {
        if ($app === null) {
            $app = $this->getApp();
        }
        $url = $this->config->getRootUrl();
        return $url.'wa-apps/'.$app.'/';
    }

    public function getRootUrl($absolute = false, $script = false)
    {
        return $this->config->getRootUrl($absolute, $script);
    }

    public static function getSetting($name, $default = '', $app_id = null)
    {
        if (!isset(self::$models['app_settings'])) {
            self::$models['app_settings'] = new waAppSettingsModel();
        }
        return self::$models['app_settings']->get($app_id, $name, $default);
    }

    /** Active plugin for _wp(). Updated by wa()->event(). */
    protected static $activePlugin = array();

    public static function getActiveLocaleDomain()
    {
        if (self::$activePlugin) {
            return implode('_', end(self::$activePlugin));
        } else {
            return wa()->getConfig()->getApplication();
        }
    }

    public static function pushActivePlugin($plugin, $app = null)
    {
        if (!$app) {
            $app = wa()->getConfig()->getPrefix();
        }
        return array_push(self::$activePlugin, $plugin ? array($app, $plugin) : array($app));
    }

    public static function popActivePlugin()
    {
        return array_pop(self::$activePlugin);
    }

    /** Return all handlers bound to event $event generated by $app.
      * Currently only checks $app's own plugins, but this may be changed in future.
      * @param string $app application id that generated event
      * @param string $event event name
      * @return array list of arrays ['className', 'method', 'pluginId', 'appId'] */
    protected function getPlugins($app, $event)
    {
        //$system = self::getInstance($app);
        $plugins = $this->getConfig()->getPlugins();
        $result = array();
        foreach ($plugins as $plugin_id => $plugin) {
            foreach ($plugin['handlers'] as $handler_event => $handler_method) {
                if ($event == $handler_event) {
                    $class = $app.ucfirst($plugin_id).'Plugin';
                    $result[] = array($class, $handler_method, $plugin_id, $app);
                }
            }
        }
        return $result;
    }

    /**
     * Return waPlugin object
     *
     * @param string $plugin_id
     * @return waPlugin
     */
    public function getPlugin($plugin_id)
    {
        $app_id = $this->getConfig()->getApplication();
        $path = $this->getConfig()->getPluginPath($plugin_id).'/lib/config/plugin.php';
        if (file_exists($path)) {
            $class = $app_id.ucfirst($plugin_id).'Plugin';
            $plugin_info = include($path);
            $plugin_info['id'] = $plugin_id;
            if (isset($plugin_info['img'])) {
                $plugin_info['img'] = 'wa-apps/'.$app_id.'/plugins/'.$plugin_id.'/'.$plugin_info['img'];
            }
            return new $class($plugin_info);
        } else {
            throw new waException('Plugin '.$plugin_id.' not found');
        }
    }

    /** Trigger event with given $name from current active application.
      * @param string $name
      * @param $params passed to event handlers
      * @return array app_id or plugin_id => data returned from handler (unless null is returned) */
    public function event($name, &$params = null)
    {
        $result = array();
        if (is_array($name)) {
            $event_app_id = $name[0];
            $system = self::getInstance($event_app_id);
            $name = $name[1];
        } else {
            $event_app_id = $this->getConfig()->getApplication();
            $system = $this;
        }


        $prefix = wa($event_app_id)->getConfig()->getPrefix();

        //
        // Call handlers defined by applications
        //
        $apps = $this->getApps();
        $path = $this->getConfig()->getPath('apps');
        foreach ($apps as $app_id => $info) {
            $file_path = $path."/".$app_id."/lib/handlers/".$prefix.".".$name.".handler.php";
            if (file_exists($file_path)) {
//                $config = SystemConfig::getAppConfig($app_id, $this->config->getEnviroment(), $this->config->getRootPath());
                waSystem::getInstance($app_id);
                include_once($file_path);
                $class_name = $name;
                if (strpos($name, '.') !== false) {
                    $class_name = strtok($class_name, '.').ucfirst(strtok(''));
                }
                $class_name = $app_id.ucfirst($prefix).ucfirst($class_name)."Handler";
                $handler = new $class_name();
                try {
                    $r = $handler->execute($params);
                    if ($r !== null) {
                        $result[$app_id] = $r;
                    }
                } catch (Exception $e) {

                }
            }
        }
        //self::setActive($event_app_id);

        //
        // Call handlers defined by current application's plugins
        //
        $plugins = $system->getConfig()->getPlugins();
        foreach ($plugins as $plugin_id => $plugin) {
            foreach ($plugin['handlers'] as $handler_event => $handler_method) {
                if ($name == $handler_event) {
                    // Remember active plugin locale name for _wp() to work
                    self::pushActivePlugin($plugin_id, wa($event_app_id)->getConfig()->getPrefix());
                    try {
                        $class_name = $event_app_id.ucfirst($plugin_id).'Plugin';
                        $class = new $class_name($plugin);
                        // Load plugin locale if it exists
                        $locale_path = $this->getAppPath('plugins/'.$plugin_id.'/locale', $event_app_id);
                        if (is_dir($locale_path)) {
                            waLocale::load($this->getLocale(), $locale_path, self::getActiveLocaleDomain(), false);
                        }
                        if (null !== ( $r = $class->$handler_method($params))) {
                            $result[$plugin_id.'-plugin'] = $r;
                        }

                    } catch (Exception $e) {
                        waLog::log('Error: '.$e->getMessage());
                    }
                    self::popActivePlugin();
                }
            }
        }
        return $result;
    }
}

/**
 * Alias for waSystem::getInstance()
 * @return waSystem
 */
function wa($name = null)
{
    return waSystem::getInstance($name);
}