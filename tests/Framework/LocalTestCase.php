<?php
namespace TNotifyer\Framework;

use function count;
use function strlen;
use function substr;
use PHPUnit\Framework\TestCase;
use TNotifyer\Engine\Storage;

class LocalTestCase extends TestCase
{
    public const ANY_VALUE = '***';

    /**
     * Print string into output.
     * 
     * @param string text
     */
    public static function output($str)
    {
        fwrite(STDERR, $str);
    }

    /**
     * Print variable value into output.
     * 
     * @param mixed variable
     */
    public static function outputVar($var)
    {
        self::output(print_r($var, true));
    }

    /**
     * Print db history variables into output.
     */
    public static function outputDBHistory()
    {
        $db = Storage::get('DBSimple');
        self::output("\n[DB-History]:\n");
        foreach ($db->sql_history as $i => $sql) {
            self::output("  [$i] => $sql\n");
            foreach ($db->args_history[$i] ?? [] as $j => $v) {
                self::output("    [$j] => $v\n");
            }
            self::output("\n");
        }
    }

    /**
     * 
     * Compare each history step with stored DB history starting from last
     * 
     * @param array of steps like:
     * [
     *   @param string sql
     *   @param array args
     * ]
     */
    public function assertDBHistory($db_history)
    {
        $db = Storage::get('DBSimple');

        // calc last index of DB history
        $last_index = count($db->sql_history) - 1;
        if ($last_index == -1)
            $this->assertCount(1, $db->sql_history, '[assertDBHistory] History is empty!');

        foreach ($db_history as $i => $step) {
            // check step
            if (count($step) != 2)
                $this->assertCount(2, $step, '[assertDBHistory] Two parameters required for step!');

            [$sql, $args] = $step;

            // take sql and args from db history step by step backward
            $db_sql = $db->sql_history[$last_index - $i];
            $db_args = $db->args_history[$last_index - $i];

            // compare sql with same length part from db sql
            $this->assertEquals($sql, substr( $db_sql, 0, strlen($sql) ));

            // update to ANY_VALUE in args from db history
            foreach ($args as $k => $arg)
                if ($arg === self::ANY_VALUE) $db_args[$k] = self::ANY_VALUE;

            // compare args
            $this->assertEquals($args, $db_args);
        }
    }
}
