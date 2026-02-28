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
 * System controller.
 * 
 */
class SystemController extends AbstractWebController {

    /**
     * 
     * Not found handler.
     * 
     */
    public function notFound()
    {
        return $this->response->json([
            'error' => 'not found'
        ], 404);
    }

    /**
     * 
     * Crypto test action.
     * 
     */
    public function testCrypto()
    {
        return $this->response->print([
            'Crypto test result' => Storage::get('Crypto')->test()? 'true' : 'false'
        ]);
    }
}