<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Exceptions;

/**
 * 
 * TNotifyer scope Exceptions.
 * 
 */
class TNotifyerException extends \Exception {

    /**
     * Constructor with a message that isn't optional
     */
    public function __construct($message, $code = 0, ?Throwable $previous = null) {
        // parent
        parent::__construct($message, $code, $previous);
    }

}