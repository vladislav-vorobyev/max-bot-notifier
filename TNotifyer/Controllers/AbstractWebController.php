<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Controllers;

use TNotifyer\Engine\Storage;

/**
 * 
 * Abstract Web controller.
 * 
 */
class AbstractWebController {

    /**
     * @var Response
     */
    protected $response;

    /**
     * @var Request
     */
    protected $request;
    
    /**
     * 
     * Constructor.
     * 
     */
    public function __construct()
    {
        $this->response = Storage::get('Response');
        $this->request = Storage::get('Request');
    }
}