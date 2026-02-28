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
 * OZON controller.
 * 
 */
class OZONController extends AbstractWebController {

    /**
     * Get info handler.
     * 
     * @return Response current response
     */
    public function info()
    {
        // response
        return $this->response->print([
            'Seller Info' => Storage::get('OZON')->getInfo(),
            'Roles' => Storage::get('OZON')->getRoles()
        ]);
    }

    /**
     * Do check handler.
     * 
     * @return Response current response
     */
    public function doCheck()
    {
        // get request optional parameter
        $period = $this->request->getParam('period', '');

        // make an action
        Storage::get('OZON')->doCheck($period);

        // response
        return $this->response->text('Check done.');
    }

    /**
     * Do check status handler.
     * 
     * @return Response current response
     */
    public function doCheckStatus()
    {
        // get request optional parameter
        $period = $this->request->getParam('period', '');

        // make an action
        Storage::get('OZON')->doCheckStatus($period);

        // response
        return $this->response->text('Check done.');
    }

    /**
     * Get FBS list test handler.
     * 
     * @return Response current response
     */
    public function makeFBSListTest()
    {
        // get request optional parameter
        $period = $this->request->getParam('period', '7 days');

        // response
        return $this->response->print(Storage::get('OZON')->makeFBSListTest($period));
    }

    /**
     * Get cancelled status FBS list test handler.
     * 
     * @return Response current response
     */
    public function makeCancelledFBSListTest()
    {
        // get request optional parameter
        $period = $this->request->getParam('period', '7 days');

        // response
        return $this->response->print(Storage::get('OZON')->makeCancelledFBSListTest($period));
    }

    /**
     * Get Unfulfilled FBS list test handler.
     * 
     * @return Response current response
     */
    public function makeUnfulfilledFBSListTest()
    {
        // get request optional parameter
        $period = $this->request->getParam('period', '7 days');

        // response
        return $this->response->print(Storage::get('OZON')->makeUnfulfilledFBSListTest($period));
    }

    /**
     * Get posting handler.
     * 
     * @return Response current response
     */
    public function getPosting()
    {
        // get request parameter
        $posting_number = $this->request->getParam('num');

        // response
        return $this->response->print(Storage::get('OZON')->getPosting($posting_number));
    }
}