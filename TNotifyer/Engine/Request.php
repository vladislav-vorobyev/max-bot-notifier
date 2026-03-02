<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Engine;

use TNotifyer\Exceptions\NotFoundException;

/**
 * 
 * Incoming request control.
 * 
 */
class Request {

    /**
     * @var string root path of the site
     */
    public $root_path;

    /**
     * @var string root uri of the site
     */
    public $root_uri;

    /**
     * @var string get, post etc
     */
    public $request_method;

    /**
     * @var string current uri
     */
    public $uri;

    /**
     * @var string current path
     */
    public $path;

    /**
     * @var array request headers
     */
    public $headers;

    /**
     * @var array get params
     */
    public $params = [];

    /**
     * @var string post params or body
     */
    public $post;


    /**
     * 
     * Constructor.
     * 
     */
    public function __construct($root_path = '/')
    {
        // base path of current site
        $this->root_path = substr($root_path, -1) === '/'? $root_path : $root_path . '/';

        // request method
        $this->request_method = $_SERVER['REQUEST_METHOD'];

        // request uri and query
        $this->uri = $_SERVER['REQUEST_URI'];
        $parsed_url = parse_url($this->uri);
        if (isset($parsed_url['query']))
            parse_str($parsed_url['query'], $this->params);
        $this->path = $parsed_url['path'];

        // site root uri
        $scheme = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $host = $_SERVER['HTTP_HOST'];
        $this->root_uri = "$scheme://$host{$this->root_path}";

        // sub site root from path
        $rootlen = strlen($this->root_path);
        if ($rootlen > 1 && substr_compare($this->path, $this->root_path, 0, $rootlen) === 0)
            $this->path = substr($this->path, $rootlen - 1);

        // request headers
        $this->headers = $headers = getallheaders();
        
        // determine post body based on request header 'Content-Type'
        if (isset($headers['Content-Type'])) {
            $postData = file_get_contents('php://input');

            if (substr($headers['Content-Type'], 0, 16) == 'application/json') {
                $this->post = json_decode($postData, true);

            } elseif (substr($headers['Content-Type'], 0, 33) == 'application/x-www-form-urlencoded') {
                parse_str($postData, $this->post);

            } elseif (substr($headers['Content-Type'], 0, 19) == 'multipart/form-data') {
                parse_str($postData, $this->post);

            } else {
                $this->post = $_POST;
            }
            
        } else {
            $this->post = $_POST;
        }
    }
    
    /**
     * 
     * Get input parameter.
     * Throw exception if not found and no default value.
     * 
     * @param string name of parameter
     * @param mixed default value (optional)
     * 
     * @return string incoming parameter by name.
     */
    public function getParam($name, $default = null)
    {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        } elseif (!is_null($default)) {
            return $default;
        } else {
            throw new NotFoundException("Not found parameter '$name'");
        }
    }
    
    /**
     * 
     * Get input parameter as integer.
     * Throw exception if not found and no default value.
     * 
     * @param string name of parameter
     * @param mixed default value (optional)
     * 
     * @return integer incoming parameter by name.
     */
    public function getIntParam($name, $default = null)
    {
        return intval($this->getParam($name, $default));
    }
}
