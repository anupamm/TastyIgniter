<?php
/**
 * TastyIgniter
 *
 * An open source online ordering, reservation and management system for restaurants.
 *
 * @package Igniter
 * @author Samuel Adepoyigi
 * @copyright (c) 2013 - 2016. Samuel Adepoyigi
 * @copyright (c) 2016 - 2017. TastyIgniter Dev Team
 * @link https://tastyigniter.com
 * @license http://opensource.org/licenses/MIT The MIT License
 * @since File available since Release 1.0
 */
defined('BASEPATH') or exit('No direct script access allowed');

/* load the HMVC_Loader class */
require IGNITEPATH.'third_party/MX/Loader.php';

/**
 * TastyIgniter Loader Class
 *
 * @category       Libraries
 * @package        Igniter\Core\TI_Loader.php
 * @link           http://docs.tastyigniter.com
 */
class TI_Loader extends MX_Loader
{

    protected $_ci_view_paths = [VIEWPATH => TRUE, THEMEPATH => TRUE];

    protected $_ci_library_paths = [IGNITEPATH, BASEPATH, TI_APPPATH];

    protected $_ci_model_paths = [IGNITEPATH, TI_APPPATH];

    protected $_ci_helper_paths = [IGNITEPATH, BASEPATH];

    protected $_db_config_loaded = FALSE;

    /**
     * Remove later
     *
     * @param      $class
     * @param null $params
     * @param null $object_name
     */
    protected function _ci_load_class($class, $params = null, $object_name = null)
    {
        return $this->_ci_load_library($class, $params, $object_name);
    }

    public function component($module, $params = null)
    {
        return $this->module($module, $params);
    }

    public function controller($module, $params = null)
    {
        return $this->module($module, $params);
    }

    // --------------------------------------------------------------------

    /**
     * Overrides parent method to replace APPPATH
     * {@inheritDocs}
     */
    public function plugin($plugin)
    {
        if (is_array($plugin)) return $this->plugins($plugin);

        if (isset($this->_ci_plugins[$plugin]))
            return $this;

        list($path, $_plugin) = Modules::find($plugin.'_pi', $this->_module, 'plugins/');

        if ($path === FALSE && !is_file($_plugin = TI_APPPATH.'plugins/'.$_plugin.EXT)) {
            show_error("Unable to locate the plugin file: {$_plugin}");
        }

        Modules::load_file($_plugin, $path);
        $this->_ci_plugins[$plugin] = TRUE;

        return $this;
    }

    // --------------------------------------------------------------------

    /** Load a view **/
    public function view($view, $vars = [], $return = FALSE)
    {
        $theme_paths = [
            $this->config->item(APPDIR, 'default_themes'), $this->config->item(APPDIR.'_parent', 'default_themes'),
        ];

        foreach (array_filter($theme_paths) as $theme_path) {
            $theme_path = rtrim($theme_path, '/');

            foreach (['/', '/layouts/', '/partials/'] as $folder) {
                $t_view = (pathinfo($view, PATHINFO_EXTENSION)) ? $view : $view.EXT;

                if (file_exists(THEMEPATH.$theme_path.$folder.$t_view)) {
                    $path = THEMEPATH.$theme_path.$folder;
                    $this->_ci_view_paths = [$path => TRUE] + $this->_ci_view_paths;
                    break;
                }
            }
        }

        if (empty($path)) {
            $base = (APPDIR === ADMINDIR) ? 'views/' : 'components/views/';
            list($path, $_view) = Modules::find($view, $this->_module, $base);

            if ($path != FALSE) {
                $this->_ci_view_paths = [$path => TRUE] + $this->_ci_view_paths;
                $view = $_view;
            }
        }

        return $this->_ci_load(['_ci_view' => $view, '_ci_vars' => $this->_ci_object_to_array($vars), '_ci_return' => FALSE]);
    }

    // --------------------------------------------------------------------

    /** Load the database drivers **/
    public function database($params = '', $return = FALSE, $query_builder = null)
    {
        if ($return === FALSE && $query_builder === null &&
            isset(CI::$APP->db) && is_object(CI::$APP->db) && !empty(CI::$APP->db->conn_id)
        ) {
            return FALSE;
        }

        require_once BASEPATH.'database/DB'.EXT;

        if ($return === TRUE) return DB($params, $query_builder);

        CI::$APP->db = $db = DB($params, $query_builder);

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Model Loader
     *
     * Loads and instantiates models.
     *
     * @param    string $model Model name
     * @param    string $name An optional object name to assign to
     * @param    bool $db_conn An optional database connection configuration to initialize
     *
     * @return    object
     */
    public function model($model, $name = '', $db_conn = FALSE)
    {

        if (is_array($model)) return $this->models($model);

        ($_alias = $name) OR $_alias = basename($model);

        if (in_array($_alias, $this->_ci_models, TRUE))
            return $this;

        /* check module */
        list($path, $_model) = Modules::find(strtolower($model), $this->_module, 'models/');

        if ($path == FALSE) {
            /* check application & packages */
            return $this->_ci_model($model, $name, $db_conn);
        } else {
            class_exists('CI_Model', FALSE) OR load_class('Model', 'core');

            if ($db_conn !== FALSE && !class_exists('CI_DB', FALSE)) {
                if ($db_conn === TRUE) $db_conn = '';
                $this->database($db_conn, FALSE, TRUE);
            }

            Modules::load_file($_model, $path);

            $model = ucfirst($_model);
            CI::$APP->$_alias = new $model();

            $this->_ci_models[] = $_alias;
        }

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * Overrides parent method to replace APPPATH
     * {@inheritDocs}
     */
    public function remove_package_path($path = '')
    {
        $config =& $this->_ci_get_component('config');

        if ($path === '') {
            array_shift($this->_ci_library_paths);
            array_shift($this->_ci_model_paths);
            array_shift($this->_ci_helper_paths);
            array_shift($this->_ci_view_paths);
            array_pop($config->_config_paths);
        } else {
            $path = rtrim($path, '/').'/';
            foreach (['_ci_library_paths', '_ci_model_paths', '_ci_helper_paths'] as $var) {
                if (($key = array_search($path, $this->{$var})) !== FALSE) {
                    unset($this->{$var}[$key]);
                }
            }

            if (isset($this->_ci_view_paths[$path.'views/'])) {
                unset($this->_ci_view_paths[$path.'views/']);
            }

            if (($key = array_search($path, $config->_config_paths)) !== FALSE) {
                unset($config->_config_paths[$key]);
            }
        }

        // make sure the application default paths are still in the array
        $this->_ci_library_paths = array_unique(array_merge($this->_ci_library_paths, [TI_APPPATH, IGNITEPATH]));
        $this->_ci_helper_paths = array_unique(array_merge($this->_ci_helper_paths, [TI_APPPATH, IGNITEPATH]));
        $this->_ci_model_paths = array_unique(array_merge($this->_ci_model_paths, [TI_APPPATH]));
        $this->_ci_view_paths = array_merge($this->_ci_view_paths, [TI_APPPATH.'views/' => TRUE]);
        $config->_config_paths = array_unique(array_merge($config->_config_paths, [TI_APPPATH]));

        return $this;
    }

    // --------------------------------------------------------------------

    /**
     * CI Autoloader
     *
     * Loads component listed in the config/autoload.php file.
     *
     * @used-by CI_Loader::initialize()
     * @return  void
     */
    protected function _ci_autoloader()
    {
        if (file_exists(ROOTPATH.'config/autoload.php')) {
            include(ROOTPATH.'config/autoload.php');
        }

        if (file_exists(ROOTPATH.'config/'.ENVIRONMENT.'/autoload.php')) {
            include(ROOTPATH.'config/'.ENVIRONMENT.'/autoload.php');
        }

        if (!isset($autoload)) {
            return;
        }

        // Autoload packages
        if (isset($autoload['packages'])) {
            foreach ($autoload['packages'] as $package_path) {
                $this->add_package_path($package_path);
            }
        }

        // Load any custom config file
        if (count($autoload['config']) > 0) {
            foreach ($autoload['config'] as $val) {
                $this->config($val);
            }
        }

        // Autoload helpers and languages
        foreach (['helper', 'language'] as $type) {
            if (isset($autoload[$type]) && count($autoload[$type]) > 0) {
                $this->$type($autoload[$type]);
            }
        }

        // Autoload drivers
        if (isset($autoload['drivers'])) {
            $this->driver($autoload['drivers']);
        }

        // Load libraries
        if (isset($autoload['libraries']) && count($autoload['libraries']) > 0) {
            // Load the database driver.
            if (in_array('database', $autoload['libraries'])) {
                $this->database();
                $autoload['libraries'] = array_diff($autoload['libraries'], ['database']);
            }

            // Load all other libraries
            $this->library($autoload['libraries']);
        }

        // Autoload models
        if (isset($autoload['model'])) {
            $this->model($autoload['model']);
        }
    }

    /**
     * @TODO: clean up method maybe use spl_autoload to load base Model
     *
     * @param $model
     * @param $name
     * @param $db_conn
     *
     * @return $this
     */
    public function _ci_model($model, $name = '', $db_conn = FALSE)
    {
        if (empty($model)) {
            return $this;
        } elseif (is_array($model)) {
            foreach ($model as $key => $value) {
                is_int($key) ? $this->model($value, '', $db_conn) : $this->model($key, $value, $db_conn);
            }

            return $this;
        }

        $path = '';

        // Is the model in a sub-folder? If so, parse out the filename and path.
        if (($last_slash = strrpos($model, '/')) !== FALSE) {
            // The path is in front of the last slash
            $path = substr($model, 0, ++$last_slash);

            // And the model name behind it
            $model = substr($model, $last_slash);
        }

        if (empty($name)) {
            $name = $model;
        }

        if (in_array($name, $this->_ci_models, TRUE)) {
            return $this;
        }

        $CI =& get_instance();
        if (isset($CI->$name)) {
            throw new RuntimeException('The model name you are loading is the name of a resource that is already being used: '.$name);
        }

        if ($db_conn !== FALSE && !class_exists('CI_DB', FALSE)) {
            if ($db_conn === TRUE) {
                $db_conn = '';
            }

            $this->database($db_conn, FALSE, TRUE);
        }

        // Note: All of the code under this condition used to be just:
        //
        //       load_class('Model', 'core');
        //
        //       However, load_class() instantiates classes
        //       to cache them for later use and that prevents
        //       MY_Model from being an abstract class and is
        //       sub-optimal otherwise anyway.
        if (!class_exists('CI_Model', FALSE)) {
            $app_path = IGNITEPATH.'core'.DIRECTORY_SEPARATOR;
            if (file_exists($app_path.'Model.php')) {
                require_once($app_path.'Model.php');
                if (!class_exists('CI_Model', FALSE)) {
                    throw new RuntimeException($app_path."Model.php exists, but doesn't declare class CI_Model");
                }
            } elseif (!class_exists('CI_Model', FALSE)) {
                require_once(BASEPATH.'core'.DIRECTORY_SEPARATOR.'Model.php');
            }

            $class = config_item('subclass_prefix').'Model';
            if (file_exists($app_path.$class.'.php')) {
                require_once($app_path.$class.'.php');
                if (!class_exists($class, FALSE)) {
                    throw new RuntimeException($app_path.$class.".php exists, but doesn't declare class ".$class);
                }
            }
        }

        if (class_exists('Illuminate\Database\Eloquent\Model', FALSE)) {
            if (!class_exists('Igniter\Database\Model', FALSE)) {
                require_once(IGNITEPATH.'database'.DIRECTORY_SEPARATOR.'Model.php');
            }
        }

        $model = ucfirst($model);
        if (!class_exists($model, FALSE)) {
            foreach ($this->_ci_model_paths as $mod_path) {
                if (!file_exists($mod_path.'models/'.$path.$model.'.php')) {
                    continue;
                }

                require_once($mod_path.'models/'.$path.$model.'.php');
                if (!class_exists($model, FALSE)) {
                    throw new RuntimeException($mod_path."models/".$path.$model.".php exists, but doesn't declare class ".$model);
                }

                break;
            }

            if (!class_exists($model, FALSE)) {
                throw new RuntimeException('Unable to locate the model you have specified: '.$model);
            }
        } elseif (!is_subclass_of($model, 'CI_Model')) {
            dd("Class ".$model." already exists and doesn't extend CI_Model");  // @TODO: for debugging, remove later
            throw new RuntimeException("Class ".$model." already exists and doesn't extend CI_Model");
        }

        $this->_ci_models[] = $name;
        $CI->$name = new $model();

        return $this;
    }
}

/* End of file TI_Loader.php */
/* Location: ./system/tastyigniter/core/TI_Loader.php */