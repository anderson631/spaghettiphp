<?php

class Mapper {
    protected $prefixes = array();
    protected $routes = array();
    protected $base;
    protected $here = '/';
    protected $domain = 'http://localhost';
    protected $root;
    protected static $instance;

    public function __construct() {
        $this->base = dirname($_SERVER['PHP_SELF']);
        if(basename($this->base) == 'public'):
            $this->base = dirname($this->base);
            if($this->base == DIRECTORY_SEPARATOR || $this->base == '.'):
                $this->base = '/';
            endif;
        endif;

        if(array_key_exists('REQUEST_URI', $_SERVER) && array_key_exists('HTTP_HOST', $_SERVER)):
            $start = strlen($this->base);
            $this->here = self::normalize(substr($_SERVER['REQUEST_URI'], $start));
            $this->domain = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
        endif;
    }
    public static function instance() {
        if(!isset(self::$instance)):
            $c = __CLASS__;
            self::$instance = new $c;
        endif;
        return self::$instance;
    }
    public static function here() {
        $self = self::instance();
        return $self->here;
    }
    public static function base() {
        $self = self::instance();
        return $self->base;
    }
    public static function domain() {
        $self = self::instance();
        return $self->domain;
    }
    public static function normalize($url) {
        if(!preg_match('/^[a-z]+:/i', $url)):
            $url = '/' . $url;
            while(strpos($url, '//') !== false):
                $url = str_replace('//', '/', $url);
            endwhile;
            $url = rtrim($url, '/');
            if(empty($url)):
                $url = '/';
            endif;
        endif;

        return $url;
    }
    public static function root($controller) {
        $self = self::instance();
        $self->root = $controller;
        return true;
    }
    public static function getRoot() {
        $self = self::instance();
        return $self->root;
    }
    public static function prefix($prefix) {
        $self = self::instance();
        if(is_array($prefix)) $prefixes = $prefix;
        else $prefixes = func_get_args();
        foreach($prefixes as $prefix):
            $self->prefixes []= $prefix;
        endforeach;
        return true;
    }
    public static function unsetPrefix($prefix) {
        $self = self::instance();
        unset($self->prefixes[$prefix]);
        return true;
    }
    public static function getPrefixes() {
        $self = self::instance();
        return $self->prefixes;
    }
    public static function connect($url = null, $route = null) {
        if(is_array($url)):
            foreach($url as $key => $value):
                self::connect($key, $value);
            endforeach;
        elseif(!is_null($url)):
            $self = self::instance();
            $url = self::normalize($url);
            $self->routes[$url] = rtrim($route, '/');
        endif;
        return true;
    }
    public static function disconnect($url) {
        $self = self::instance();
        $url = rtrim($url, '/');
        unset($self->routes[$url]);
        return true;
    }
    public static function match($check, $url = null) {
        if(is_null($url)):
            $url = self::here();
        endif;
        $check = '%^' . str_replace(array(':any', ':fragment', ':num'), array('(.+)', '([^\/]+)', '([0-9]+)'), $check) . '/?$%';
        return preg_match($check, $url);
    }
    public static function getRoute($url) {
        $self = self::instance();
        foreach($self->routes as $map => $route):
            if(self::match($map, $url)):
                $map = '%^' . str_replace(array(':any', ':fragment', ':num'), array('(.+)', '([^\/]+)', '([0-9]+)'), $map) . '/?$%';
                $url = preg_replace($map, $route, $url);
                break;
            endif;
        endforeach;
        return self::normalize($url);
    }
    public static function parse($url = null) {
        $here = self::normalize(is_null($url) ? self::here() : $url);
        $url = self::getRoute($here);
        $prefixes = join('|', self::getPrefixes());
        
        $path = array();
        $parts = array('here', 'prefix', 'controller', 'action', 'extension', 'params', 'queryString');
        preg_match('/^\/(?:(' . $prefixes . ')(?:\/|(?!\w)))?(?:([a-z_-]*)\/?)?(?:([a-z_-]*)\/?)?(?:\.([\w]+))?(?:\/?([^?]+))?(?:\?(.*))?/i', $url, $reg);

        foreach($parts as $k => $key):
            $path[$key] = isset($reg[$k]) ? $reg[$k] : null;
        endforeach;
        
        $path['named'] = $path['params'] = array();
        if(isset($reg[5])):
            foreach(explode('/', $reg[5]) as $param):
                if(preg_match('/([^:]*):([^:]*)/', $param, $reg)):
                    $path['named'][$reg[1]] = urldecode($reg[2]);
                elseif($param != ''):
                    $path['params'] []= urldecode($param);
                endif;
            endforeach;
        endif;

        $path['here'] = $here;
        if(empty($path['controller'])) $path['controller'] = self::getRoot();
        if(empty($path['action'])) $path['action'] = 'index';
        if($filtered = self::filterAction($path['action'])):
            $path['prefix'] = $filtered['prefix'];
            $path['action'] = $filtered['action'];
        endif;
        if(!empty($path['prefix'])):
            $path['action'] = $path['prefix'] . '_' . $path['action'];
        endif;
        if(empty($path['id'])) $path['id'] = null;
        if(empty($path['extension'])) $path['extension'] = Config::read('App.defaultExtension');
        if(!empty($path['queryString'])):
            parse_str($path['queryString'], $queryString);
            $path['named'] = array_merge($path['named'], $queryString);
        endif;
        
        return $path;
    }
    public static function url($path, $full = false) {
        if(is_array($path)):
            $here = self::parse();
            $params = $here['named'];
            $path = array_merge(array(
                'prefix' => $here['prefix'],
                'controller' => $here['controller'],
                'action' => $here['action'],
                'params' => $here['params']
            ), $params, $path);
            $nonParams = array('prefix', 'controller', 'action', 'params');
            $url = '';
            foreach($path as $key => $value):
                if(!in_array($key, $nonParams)):
                    $url .= '/' . $key . ':' . $value;
                elseif(!is_null($value)):
                    if($key == 'action' && $filtered = self::filterAction($value)):
                        $value = $filtered['action'];
                    elseif($key == 'params'):
                        $value = join('/', $value);
                    endif;
                    $url .= '/' . $value;
                endif;
            endforeach;
        else:
            if(preg_match('/^[a-z]+:/', $path)):
                return $path;
            elseif(substr($path, 0, 1) == '/'):
                $url = $path;
            else:
                if(substr($path, 0, 1) != '#'):
                    $path = '/' . $path;
                endif;
                $url = self::here() . $path;
            endif;
        endif;
        $url = self::normalize(self::base() . $url);
        return $full ? self::domain() . $url : $url;
    }
    public static function filterAction($action) {
        if(strpos($action, '_') !== false):
            foreach(self::getPrefixes() as $prefix):
                if(strpos($action, $prefix) === 0):
                    return array(
                        'action' => substr($action, strlen($prefix) + 1),
                        'prefix' => $prefix
                    );
                endif;
            endforeach;
        endif;
        return false;
    }
}
