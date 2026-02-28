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
 * A main object to run the application.
 * 
 */
class App {

    /**
     * Application variables
     */
    const VARIABLES = [
        'version' => '1.5.0',
        'name' => 'MAX Notifyer',
    ];

    /**
     * 
     * Constructor.
     * 
     */
    public function __construct()
    {
    }

    /**
     * 
     * To run the application.
     * 
     */
    public function run()
    {
        // Catch all Exceptions and pass into error response
        try {
            // Get this application router and determine current route
            $current_route = Storage::get('Router')->getCurrent();

            // Execute the route
            $response = $this->execute($current_route);

            // Output the response
            $response->render();

        } catch (\Exception $e) {
            Storage::set('Response', new Response())->json(
                ['error' => $e->getMessage()],
                400
            )->render();
        }
    }

    /**
     * 
     * To execute the route.
     * 
     * @param array route = ['controller','method']
     * 
     * @return Response route response
     */
    public static function execute($route)
    {
        // controller
        if (empty($route[0]))
            throw new NotFoundException('Not found controller name');
        $controller_name = 'TNotifyer\\Controllers\\' . $route[0];
        if (!class_exists($controller_name))
            throw new NotFoundException("Not found '{$controller_name}' class");
        
        // method
        if (empty($route[1]))
            throw new NotFoundException('Not found controller method name');
        $method = $route[1];
        if (!method_exists($controller_name, $method))
            throw new NotFoundException("Not found '{$controller_name}->{$method}()' method");

        // Execute the controller method
        $controller = new $controller_name;
        $response = $controller->{$method}();

        // return the response
        return $response;
    }

    /**
     * 
     * Get application variable value.
     * 
     * @param string variable name
     * @param mixed default value
     * 
     * @return string value
     */
    public static function var($name, $default = '')
    {
        return self::VARIABLES[$name] ?? $default;
    }

    /**
     * 
     * Get environment variable value.
     * 
     * @param string variable name
     * @param mixed default value
     * 
     * @return string value
     */
    public static function env($name, $default = '')
    {
        return $_SERVER[$name] ?? $_SERVER['REDIRECT_'.$name] ?? $default;
    }

    /**
     * 
     * Get information about the application.
     * 
     * @return array data
     */
    public static function info()
    {
        return [
            'name' => self::var('name'),
            'version' => self::var('version'),
            'SRC_URI' => self::env('SRC_URI', '/'),
            'ROOT_URI' => self::env('ROOT_URI'),
            'BOT_INTERNAL_ID' => self::env('BOT_INTERNAL_ID'),
            'BOT_HOST_ID' => self::env('BOT_HOST_ID'),
        ];
    }
}
