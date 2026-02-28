<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Controllers;

use TNotifyer\Database\DB;
use TNotifyer\Engine\Storage;

/**
 * 
 * View controller.
 * 
 */
class ViewController extends AbstractWebController {

    /**
     * Show hello.
     * 
     * @return Response current response
     */
    public function hello()
    {
        return $this->response->text('Hello');
    }

    /**
     * Show app information.
     * 
     * @return Response current response
     */
    public function info()
    {
        return $this->response->print(Storage::get('App')->info());
    }

    /**
     * Show last log.
     * 
     * Request param: limit to show
     * 
     * @return Response current response
     */
    public function log()
    {
        // get rows
        $data = DB::get_last_log($this->request->getIntParam('limit', 10), -1);

        // show all columns
        return $this->response->table($data);
    }

    /**
     * Show last postings.
     * 
     * Request param: limit to show
     * 
     * @return Response current response
     */
    public function postings()
    {
        // get rows
        $data = DB::get_last_postings($this->request->getIntParam('limit', 10), -1);

        // show all columns
        return $this->response->table($data);
    }

    /**
     * Show last posting statuses.
     * 
     * Request param: limit to show
     * 
     * @return Response current response
     */
    public function statuses()
    {
        // get rows
        $data = DB::get_posting_status($this->request->getIntParam('limit', 50), -1);

        // show all columns
        return $this->response->table($data, null, ['message_id']);
    }

    /**
     * Show last updates.
     * 
     * Request param: limit to show
     * 
     * @return Response current response
     */
    public function updates()
    {
        // get rows
        $data = DB::get_last_updates($this->request->getIntParam('limit', 10), -1);

        // show all columns with 'value' as json value
        return $this->response->table($data, null, ['value']);
    }
}