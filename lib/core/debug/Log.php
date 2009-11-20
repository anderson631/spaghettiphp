<?php
/**
 *  Short description.
 *
 *  @license   http://www.opensource.org/licenses/mit-license.php The MIT License
 *  @copyright Copyright 2008-2010, Spaghetti* Framework (http://spaghettiphp.org/)
 */

class Log extends Object {
    public static function write($message, $type = 'error') {
        $data = $type . '=' . $message;
        file_put_contents(SPAGHETTI_ROOT . '/tmp/log/error', $data);
    }
}