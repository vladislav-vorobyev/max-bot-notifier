<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Engine;

/**
 * 
 * An object to determine an executor for incoming request.
 * 
 */
class Router {

    /**
     * @var array A collection of route-matching rules to iterate through.
     */
    protected $map = [];

    /**
     * 
     * Constructor.
     * 
     */
    public function __construct()
    {
        // fill default 404 route
        $this->map['/404'] = ['GET' => ['SystemController', 'notFound']];
    }

    /**
     * 
     * Set a route.
     * 
     * @param string request method
     * @param string site uri
     * @param string controller and method names
     */
    public function setRoute($method, $path, $params)
    {
        if (!isset($this->map[$path])) $this->map[$path] = [];
        $this->map[$path][$method] = $params;
    }

    /**
     * 
     * Set an array of routes.
     * 
     * @param array routes data
     */
    public function setRoutes($routes)
    {
        foreach ($routes as $route) {
            $this->setRoute(...$route);
        }
    }

    /**
     * 
     * Determine an executor for method and path.
     * 
     * @param string method (GET,POST,...)
     * @param string path
     * 
     * @return array ['class_name','method_name'].
     */
    public function getExecutor($method, $path)
    {
        // Determine a route from map
        if (empty($this->map[$path])) {
            $path = '/404';
        }
        if (empty($this->map[$path][$method])) {
            $route = $this->map['/404']['GET'];
        } else {
            $route = $this->map[$path][$method];
        }

        // Return determined route
        return $route;
    }

    /**
     * 
     * Determine an executor for incumming request.
     * 
     * @return array ['class_name','method_name'].
     */
    public function getCurrent()
    {
        // Get current request method and path
        $request = Storage::get('Request');
        $method = $request->request_method;
        $path = $request->path;

        // Determine and return a route
        return $this->getExecutor($method, $path);
    }
}
