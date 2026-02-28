<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Engine;

use TNotifyer\Database\DB;
use TNotifyer\Database\DBMySQLi;
use TNotifyer\Exceptions\InternalException;
use TNotifyer\Providers\Bot;
use TNotifyer\Providers\CURL;
use TNotifyer\Providers\Crypto;
use TNotifyer\Providers\OZONProvider;

/**
 * 
 * Dependency Injection module.
 * 
 */
class DI {
    
    /**
     * 
     * Run the initialization.
     * 
     */
    static public function start()
    {
        Storage::set('Request', new Request(App::env('ROOT_URI', '/')));
        Storage::set('Response', new Response());
        Storage::set('Router', new Router());
        Storage::set('App', new App());

        Storage::set('DBSimple', new DBMySQLi());
        if (!DB::init())
            throw new InternalException(DB::last_error_message());
    }
    
    /**
     * 
     * Run the optional initialization.
     * 
     * @param string name of the object to load
     * 
     */
    static public function load($obj_name)
    {
        switch($obj_name) {

            case 'Bot':
                return Storage::set('Bot', new Bot(App::env('BOT_INTERNAL_ID'), App::env('BOT_HOST_ID'), App::env('BOT_TOKEN'), App::env('ADMIN_CHAT_ID')));

            case 'CURL':
                return Storage::set('CURL', new CURL());

            case 'Crypto':
                return Storage::set('Crypto', new Crypto(App::env('CRYPTO_KEY'), App::env('CRYPTO_SALT')));

            case 'OZON':
                return Storage::set('OZON', new OZONProvider(...Storage::get('Bot')->getOZONToken()));

            default:
                throw new InternalException("Undefined '$obj_name' to load!");
        }
    }
}
