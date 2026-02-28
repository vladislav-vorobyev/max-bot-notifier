<?php
/**
 * 
 * This file is part of MAX Notifyer project.
 * 
 */
namespace TNotifyer\Engine;

use TNotifyer\Engine\Storage;
use TNotifyer\Exceptions\InternalException;

/**
 * 
 * Output control.
 * 
 */
class Response {

    /**
     * @var bool output as html
     */
    public $is_html = true;

    /**
     * @var int response code
     */
    public $code = 200;

    /**
     * @var string output content buffer
     */
    public $content = '';


    /**
     * Put a data as json to output.
     * 
     * @param mixed data
     */
    public function json($data, $code = 200) {
        $this->code = $code;
        $this->content = json_encode($data);
        $this->is_html = false;
        return $this;
    }

    /**
     * Add a simple text to output
     * 
     * @param string text
     */
    public function text($text) {
        $this->content .= $text;
        return $this;
    }

    /**
     * Add an information to output
     * 
     * @param mixed any data
     */
    public function print($data) {
        if (is_object($data) || is_array($data))
            return $this->print_r($data);
        $this->content .= $data;
        return $this;
    }

    /**
     * Add any complex variable as structured information to output
     * 
     * @param mixed variable
     * @param string css value for line-limit (to do a limiting of text blocks)
     */
    public function print_r($data, $line_limit = '80vw') {
        // capture the output of print_r
        $out = print_r($data, true);

        // adding </span></span> after ')'
        // $out = str_replace(")\n", ")\n</span></span>", $out);
        $out = preg_replace('/^(\s*)\)\s*$/m', '\1)</span></span>', $out);

        // insert into something like '[element] => <newline> (' the <span class=a-box> actions and one more <span>
        $out = preg_replace_callback(
            '/([ \t]*)(\[[^\]]+\][ \t]*\=\>[ \t]*[a-z0-9 \t_]+)\n([ \t]*)\(/iU',
            function ($matches) {
                return "{$matches[1]}{$matches[2]}<span class=a-box><b class=toggle-a></b><b class=open-all></b><b class=close-all></b>\n<span>{$matches[3]}(";
            },
            $out
        );

        // print the transformed output
        $this->content .= "\n<pre class=print-r-tree style='--line-limit: {$line_limit};'>\n";
        $this->content .= $out;
        $this->content .= "\n</pre>\n";

        return $this;
    }

    /**
     * Add a rows as table to output
     * 
     * @param array rows of data
     * @param array keys to show (all by default)
     * @param array keys to show as json value ('data' by default)
     */
    public function table($rows, $keys = null, $json_keys = ['data']) {
        // check input
        if (!is_array($rows) || empty($rows[0]) || !is_array($rows[0]))
            throw new InternalException('Wrong rows to show as table');

        // setup keys to all as default
        if (empty($keys) || !is_array($keys)) $keys = array_keys($rows[0]);

        // print table header
        $this->content .= "\n<table class='db'>\n<thead>\n<tr>";
        foreach ($keys as $key) {
            $this->content .= "<th>{$key}</th>";
        }
        $this->content .= "</tr>\n</thead>";

        // print table body
        foreach ($rows as &$row) {
            $this->content .= "\n<tr>";
            foreach ($keys as $key) {
                $val = &$row[$key];
                $class = '';
                if (!isset($val)) {
                    $val = '<i class="undef">undef</i>';
                } elseif (in_array($key, $json_keys)) {
                    $class = 'json-val';
                    $data = @json_decode($val);
                    $val = $data? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) : $val;
                }
                $this->content .= "<td class='t-$key $class'>$val</td>";
            }
            $this->content .= "</tr>";
        }
        $this->content .= "\n</table>\n";

        return $this;
    }


    /**
     * 
     * Send the content to output.
     * 
     */
    public function render()
    {
        // http header
        if (!headers_sent()) {
            http_response_code($this->code);

            if (!$this->is_html)
                header('Content-Type: application/json'); // json mode
        }

        if (!$this->is_html || $this->code !== 200) { // json mode or non standard response

            // output content
            print($this->content);

        } else { // HTML mode

            // header
            $this->printHTMLHeader();

            // output content
            print($this->content);

            // footer
            $this->printHTMLFooter();
        }

        // clear the buffer
        $this->content = '';
    }

    /**
     * 
     * Output HTML header.
     * 
     */
    public function printHTMLHeader()
    {
        $app = Storage::get('App');
        $version = $app->var('version');
        $site_uri = $app->env('SRC_URI', '/');
        ?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
            <meta name="viewport" content="width=device-width,initial-scale=1" />
            <title>T-bot</title>
            <link rel="stylesheet" type="text/css" href="<?=$site_uri?>style.css?v=<?=$version?>" />
            <script src="<?=$site_uri?>js/jquery-3.2.1.min.js"></script>
            <script src="<?=$site_uri?>js/tools.js?v=<?=$version?>"></script>
        </head>
        <body>
        <?php
    }

    /**
     * 
     * Output HTML footer.
     * 
     */
    public function printHTMLFooter()
    {
        ?>
        </body>
        </html>
        <?php
    }
}
