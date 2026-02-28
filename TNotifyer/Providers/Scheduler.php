<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Providers;

use \DateTime;
use \DateInterval;

/**
 * Scheduler
 *
 * Description: Provides a schedule time calculation.
 * Format: <minutes> | <hours>:<minutes> where each part = * /n | 1,2,3...
 *
 * Schedule string examples:
 * * - each 1 minute
 * * /5 - each 5 minutes
 * 5,25,45 - list of minutes
 * 12:00 - at this time
 * 10,20:05 - at 10:05 and 20:05
 *
 */
class Scheduler {

    /**
     * @var array scheduled minutes
     */
	protected $minutes;

    /**
     * @var array scheduled hours
     */
	protected $hours;


    /**
     * 
     * Constructor
     * 
     * @param string schedule string
     */
	function __construct(string $schedule) {
        // parse the schedule
		$_schedule = array_reverse(explode(':', $schedule));

        // prepare available minutes in schedule
        $this->minutes = self::_parse_part($_schedule[0], 0, 59);

        // prepare available hours in schedule
        $this->hours = self::_parse_part($_schedule[1] ?? '*', 0, 23);
	}

    /**
     * Parsing one part of schedule
     * 
     * @param string schedule part
     * @param int range start value
     * @param int range end value
     */
    protected static function _parse_part($part, $r_start, $r_end) {
        if ('*' == $part) $part = '*/1';
        if ('*/' == substr($part, 0, 2))
            return range($r_start, $r_end, intval(substr($part, 2)));
        else
            return array_map(function($v){ return intval($v); }, explode(',', $part));
    }

    /**
     * Find next value in array
     * 
     * @param array list to look
     * @param mixed value to check
     * @param mixed default value to return if not found
     */
    protected static function array_next($arr, $val, $default = null) {
        foreach ($arr as $_val)
            if ($_val > $val) {
                return $_val;
            }
        return $default;
    }

    /**
     * 
     * Get next scheduled datetime after selected
     * 
     * @param DateTime selected time
     */
	public function next(DateTime $now) {
		// current hour and minute
		$hour = intval($now->format('H'));
		$minute = intval($now->format('i'));

        $next_minute = null;
        $next_hour = null;
        $is_next_day = false;

        if (in_array($hour, $this->hours)) {
            // current hour exists in the list then try to find next minute
            $next_minute = self::array_next($this->minutes, $minute);
        }

        if (!is_null($next_minute)) {
            // next minute has been found then use current hour as next
            $next_hour = $hour;
        } else {
            // next minute is not found then use first minute and calc next hour
            $next_minute = $this->minutes[0];
        }

        if (is_null($next_hour)) {
            // find next hour
            $next_hour = self::array_next($this->hours, $hour);

            if (is_null($next_hour)) {
                // next hour is not found then use first hour and calc next day
                $next_hour = $this->hours[0];
                $is_next_day = true;
            }
        }

        // calc next time
        $next_time = clone $now;
        $next_time->setTime($next_hour, $next_minute);
        if ($is_next_day) $next_time->add( DateInterval::createFromDateString('1 day') );

        return $next_time;
	}
}
