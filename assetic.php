<?php

if (!defined('BASEPATH'))
{
    exit('No direct script access allowed');
}

use Assetic\Asset\GlobAsset;
use Assetic\Filter\Yui;
use Assetic\AssetWriter;
use Assetic\AssetManager;

use Assetic\Factory\AssetFactory;
use Assetic\Factory\Worker\CacheBustingWorker;

/**
 * Class CI_Assetic
 *
 * Custom layout for assetic library (https://github.com/kriswallsmith/assetic)
 *
 * @author Kondratenko Alexander (Xander)
 */
class CI_Assetic
{
    /**
     * Codeigniter object
     * @var CI_Controller
     */
    var $CI;

    /**
     * Base config (js/ccs files with paths)
     * @var array
     */
    var $config = array();

    /**
     * Js collection
     * @var
     */
    var $js;

    /**
     * Css collection
     * @var
     */
    var $css;


    var $writer;
    var $am;
    var $factory;
    var $separator = '-';

    function __construct()
    {
        $this->CI =& get_instance();

        // Loads the assetic config (assetic.php under ./system/application/config/)
        $this->CI->load->config('assetic');
        $tmp_config =& get_config();

        if (count($tmp_config['assetic']) > 0)
        {
            $this->config = $tmp_config['assetic'];
            unset ($tmp_config);
        }
        else
        {
            $this->_error('assetic configuration error');
        }

        $this->CI->load->helper('url');

        $this->collections['js'] = array();
        $this->collections['css'] = array();

        $this->am_css = new AssetManager();
        $this->am_js = new AssetManager();

        $this->busting_worker = new CacheBustingWorker($this->separator);

        $this->factory = new AssetFactory(assets_server_path());
        $this->factory->setDefaultOutput(assets_server_path('static'));
        $this->factory->setDebug(true);

        // init collections
        $this->createFullCollections();
    }

    /**
     * Build final collection by js/css config and set yui compressors
     *
     * @author Kondratenko Alexander (Xander)
     */
    public function createFullCollections()
    {
        $this->collections['js'] = array();
        $this->collections['css'] = array();

        foreach ($this->config['js'] as $url => $group)
        {
            foreach ($group AS $key => $files)
            {
                if ($key == 'exclude')
                {
                    continue;
                }

                $_path_array = array();
                foreach ($files as $filename)
                {
                    $_path_array[] = $filename;
                }
                $this->am_js->set($key, new GlobAsset(
                    $_path_array,
                    array(
                        new Yui\JsCompressorFilter(APPPATH . 'third_party/yuicompressor-2.4.7/build/yuicompressor-2.4.7.jar'),
                    )
                ));
            }
        }

        foreach ($this->config['css'] as $url => $group)
        {
            foreach ($group AS $key => $files)
            {
                if ($key == 'exclude')
                {
                    continue;
                }

                $_path_array = array();
                foreach ($files as $filename)
                {
                    $_path_array[] = $filename;
                }
                $this->am_css->set($key, new GlobAsset(
                    $_path_array,
                    array(
                        new Yui\CssCompressorFilter(APPPATH . 'third_party/yuicompressor-2.4.7/build/yuicompressor-2.4.7.jar')
                    )
                ));
            }
        }

        // set paths & cache busting worker for css...
        foreach ($this->am_css->getNames() as $names)
        {
            $this->am_css->get($names)->setTargetPath($names . '.css');
            $this->busting_worker->process($this->am_css->get($names), $this->factory);
        }

        // ... and for js
        foreach ($this->am_js->getNames() as $names)
        {
            $this->am_js->get($names)->setTargetPath($names . '.js');
            $this->busting_worker->process($this->am_js->get($names), $this->factory);
        }
    }

    /**
     * Get static links of js for current url
     *
     * @author Kondratenko Alexander (Xander)
     */
    public function getStaticJs()
    {
        $stl_name = array();
        $current_url = current_url();

        foreach ($this->config['js'] as $url => $collection)
        {
            if (empty($url) || strpos($current_url, $url) === FALSE)
            {
                continue;
            }

            foreach ($collection as $filename => $files)
            {
                // if we have exclude section - check current url and skip current step
                if (isset($collection['exclude']))
                {
                    foreach ($collection['exclude'] as $excluded_urls)
                    {
                        if (strpos($current_url, $excluded_urls))
                        {
                            continue 2;
                        }
                    }
                }
                if ($filename == 'exclude')
                {
                    continue;
                }
                $stl_name[] = $filename;
            }
        }

        foreach ($stl_name as $name)
        {
            $static_files = glob(assets_server_path('static/') . '*.js');

            if (!empty($static_files))
            {
                foreach ($static_files as $cmpl)
                {
                    $cmpl = basename($cmpl);

                    if (trim(explode($this->separator, $cmpl)[0]) != trim($name))
                    {
                        continue;
                    }

                    if (file_exists($this->config['static']['dir'] . $cmpl))
                    {
                        echo '<script src="' . assets_server_to_web_path('static/' . $cmpl) . '"></script>' . "\n";
                    }
                    else
                    {
                        $this->simple_returner($name, 'js');
                    }
                }
            }
            else
            {
                $this->simple_returner($name, 'js');
            }
        }
    }

    /**
     * Get static links of css for current url
     *
     * @author Kondratenko Alexander (Xander)
     */
    public function getStaticCss()
    {
        $stl_name = array();
        $current_url = current_url();

        foreach ($this->config['css'] as $url => $collection)
        {
            if (empty($url) || strpos($current_url, $url) === FALSE)
            {
                continue;
            }

            foreach ($collection as $filename => $files)
            {
                // if we have exclude section - check current url and skip current step
                if (isset($collection['exclude']))
                {
                    foreach ($collection['exclude'] as $excluded_urls)
                    {
                        if (strpos($current_url, $excluded_urls))
                        {
                            continue 2;
                        }
                    }
                }
                if ($filename == 'exclude')
                {
                    continue;
                }
                $stl_name[] = $filename;
            }
        }

        foreach ($stl_name as $name)
        {
            $static_files = glob(assets_server_path('static/') . '*.css', GLOB_BRACE);

            if (!empty($static_files))
            {
                foreach ($static_files as $cmpl)
                {
                    $cmpl = basename($cmpl);

                    if (trim(explode($this->separator, $cmpl)[0]) != trim($name))
                    {
                        continue;
                    }

                    if (file_exists($this->config['static']['dir'] . $cmpl))
                    {
                        echo '<link rel="stylesheet" type="text/css" href="' . assets_server_to_web_path('static/' . $cmpl) . '" />' . "\n";
                    }
                    else
                    {
                        $this->simple_returner($name);
                    }
                }
            }
            else
            {
                $this->simple_returner($name);
            }
        }
    }

    /**
     * Simple return assets from Asset Manager
     *
     * @param        $name - Collection's name
     * @param string $type - css/js
     *
     * @return void
     *
     * @author Kondratenko Alexander (Xander)
     */
    private function simple_returner($name, $type = 'css')
    {
        // Simple returner
        $_type = 'am_' . $type;

        foreach ($this->$_type->get($name) as $f)
        {
            if ($type == 'css')
            {
                echo '<link rel="stylesheet" type="text/css" href="' . assets_server_to_web_path($f->getSourceRoot() . '/' . $f->getSourcePath()) . '" />' . "\n";
            }
            else
            {
                echo '<script src="' . assets_server_to_web_path($f->getSourceRoot() . '/' . $f->getSourcePath()) . '"></script>' . "\n";
            }
        }
    }

    /**
     * Set Writer for js
     *
     * @author Kondratenko Alexander (Xander)
     */
    public function writeStaticJsScripts()
    {
        $writer = new AssetWriter(assets_server_path('static'));
        $writer->writeManagerAssets($this->am_js);
    }

    /**
     * Set Writer for css
     *
     * @author Kondratenko Alexander (Xander)
     */
    public function writeStaticCssLinks()
    {
        $writer = new AssetWriter(assets_server_path('static'));
        $writer->writeManagerAssets($this->am_css);
    }
}
