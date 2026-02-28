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
 * Bot controller.
 * 
 */
class BotController extends AbstractWebController {

    /**
     * Webhook handler for MAX bot.
     * 
     * @return Response current response
     */
    public function webhook()
    {
        // response
        return $this->response->json(Storage::get('Bot')->webhook());
    }

    /**
     * Set a webhook for the MAX bot.
     * 
     * @return Response current response
     */
    public function setWebhook()
    {
        // response
        return $this->response->print(Storage::get('Bot')->setWebhook());
    }

    /**
     * Remove a webhook from the MAX bot.
     * 
     * @return Response current response
     */
    public function removeWebhook()
    {
        // response
        return $this->response->print(Storage::get('Bot')->removeWebhook());
    }

    /**
     * Get and check updates from MAX bot.
     * 
     * @return Response current response
     */
    public function checkUpdates()
    {
        // response
        return $this->response->print(Storage::get('Bot')->checkUpdates());
    }

    /**
     * Send a test message to main MAX bot chats.
     * 
     * @return Response current response
     */
    public function testMessage()
    {
        // response
        return $this->response->print([
            'Sending status' => Storage::get('Bot')->sendToMainChats('<b>Тестовое</b> <i>сообщение</i>', 'HTML')
        ]);
    }

    /**
     * Send a Bot day activity message to alarm MAX chat.
     * 
     * @return Response current response
     */
    public function sendTbotDayActivity()
    {
        // run
        Storage::get('Bot')->sendTbotDayActivity();

        // response
        return $this->response->text('Done.');
    }

    /**
     * Check a status of websites (by the list from DB).
     * 
     * @return Response current response
     */
    public function pingWebsites()
    {
        return $this->response->print(Storage::get('Bot')->pingWebsites());
    }

    /**
     * Show bot information.
     * 
     * @return Response current response
     */
    public function botInfo()
    {
        return $this->response->print(Storage::get('Bot')->info());
    }

    /**
     * Run Bot jobs.
     * 
     * @return Response current response
     */
    public function runJobs()
    {
        // run
        Storage::get('Bot')->runJobs();

        // response
        return $this->response->text('Done.');
    }
}