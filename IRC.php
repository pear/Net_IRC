<?php
// /* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 2002 The PHP Group                                     |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Tomas V.V.Cox <cox@idecnet.com>                              |
// |                                                                      |
// +----------------------------------------------------------------------+
//
// $Id$

/**
* Class for handling the client side of the IRC protocol (RFC 1459)
*
* @author Tomas V.V.Cox <cox@idecnet.com>
*/
class Net_IRC
{
    var $options   = array();
    /**
    * logging error types
    * 0 fatal
    * 1 warning
    * 2 notice
    * 3 informative
    * 4 debug
    * 5 debug++
    * @var array $log_types
    * @see Net_IRC::logTypes
    */
    var $log_types = array(0, 1, 2, 3, 4);
    var $buffer    = array();
    var $stats     = array();

    function connect($options)
    {
        // XXX Check options
        if (isset($options['log_types'])) {
            $this->logTypes($options['log_types']);
        }
        $this->log(3, "connecting to {$options['server']}:{$options['port']}");
        $sd = fsockopen($options['server'], $options['port'],
                        $errno, $errstr, 5);
        if (!$sd) {
            $this->log(0, "could not connect $errstr ($errno)");
            return false;
        }
        $this->log(3, "connected");
        $this->initStats();
        $this->socket = $sd;
        $this->command('USER '.
                       $options['identd'] . ' '.
                       $options['host']   . ' '.
                       $options['server'] . ' '.
                       ':' . $options['realname']);
        $this->command('NICK ' . $options['nick']);
        $this->loopRead('MOTD', false);
        $this->callback('CONNECT', false);
        socket_set_blocking($sd, false);
        $this->options = $options;
        return true;
    }

    function disconnect()
    {
        $this->command('QUIT');
        fclose($this->socket);
        $this->socket = null;
    }

    /**
    * @param int    $code  Search the value for a code
    * @param string $value Search the code for a value
    */
    function getEvent($code = null, $handler = null)
    {
        static $events;
        if (empty($events)) {
            $events = array(
                376 => 'MOTD',
                422 => 'MOTD',
                366 => 'NAMES',
                318 => 'WHOIS'
            );
        }
        if ($code) {
            return isset($events[$code]) ? $events[$code] : $code;
        }
        foreach ($events as $k => $e) {
            if ($handler == $e) {
                return $events[$k];
            }
        }
        return false;
    }

    function command($command)
    {
        $this->write(trim($command));
    }

    function write($command)
    {
        if (feof($this->socket)) {
            $this->log(0, 'Write Disconnected');
            $this->callback('DISCONNECT', false);
            return false;
        }

        if ($command && !fputs($this->socket, $command . "\r\n")) {
            $this->log(1, "could not write to socket");
            return false;
        } else {
            $this->log(4, "<- $command");
        }
        return true;
    }

    // XXX rename $once -> $block
    function read($once = false)
    {
        if (feof($this->socket)) {
            $this->log(0, 'Read Disconnected');
            $this->callback('DISCONNECT', false);
            return false;
        }
        do {
            $receive = rtrim(fgets($this->socket, 1024));
            // XXX Only update stats for example each 2 seconds
            $this->updateStats();
            if (!$receive && $once) {
                break;
            }
            if (!$receive && !$once) {
                usleep(500000); // Half second is enough interactive
                continue;
            }
            if ($receive) {
                $this->log(4, "-> $receive");
                // XXX Return direclty the parsed response
                $result = $this->parseResponse($receive);
                $this->updateStats($result[0], $result[1]);
                if ($result[0] == 'PING') {
                    $this->callback('ping', $result[1]);
                    $receive = false;
                }
                if ($result[0] == 'ERROR') {
                    $this->callback('error', $result[1]);
                }
            }
        } while (!$receive);
        return $receive;
    }

    // XXX Clean-up the params & rename $once -> $block
    function loopRead($only_this_event = null, $continue = true, $once = false)
    {
        while ($response = $this->read($once)) {
            $result = $this->parseResponse($response);
            $event = $result[0];
            if (is_numeric($event)) {
                $event = $this->getEvent($event);
            }
            if (!$only_this_event || ($only_this_event == $event)) {
                $this->callback($event, $result[1]);
                if (!$continue) {
                    break;
                }
            }
        }
    }

    function parseResponse($response)
    {
        /*
         <message>  ::= [':' <prefix> <SPACE> ] <command> <params> <crlf>
                          $origin!$orighost $command $target $params
        */
        $message = explode(' ', $response, 2);
        if ($message[0]{0} == ':') {
            // parse prefix
            $prefix = substr($message[0], 1);
            if (strpos($prefix, '!') !== false) {
                list($origin, $orighost) = explode('!', $prefix, 2);
            } else {
                $origin   = $prefix;
                $orighost = null;
            }
            list($command, $rest) = explode(' ', $message[1], 2);
            // foo :bar
            if (strpos($rest, ' :') !== false) {
                list($target, $params) = explode(' :', $rest, 2);
            // :bar
            } elseif ($rest{0} == ':') {
                $target = substr($rest, 1);
                $params = null;
            // foo
            } else {
                $target = $rest;
                $params = null;
            }
        } else {
            $origin   = null;
            $orighost = null;
            $command  = $message[0];
            $target   = null;
            $params   = substr($message[1], 1);
        }
        return array($command, array($origin, $orighost, $target, $params));
    }

    function callback($command, $params = array())
    {
        $method = "event_$command";
        if (method_exists($this, $method)) {
            $this->log(5, "Calling callback $method");
            return call_user_func_array(array(&$this, $method), $params);
        }
        if ($params) {
            $this->log(4, "Method $method not provided, calling fallback");
            return call_user_func_array(array(&$this, 'fallback'), $params);
        }
    }

    // XXX Introduce stats levels (none, normal, full)
    function updateStats($event = null, $args = array())
    {
        if ($event) {
            $this->stats['rx_idle'] = 0;

            if (isset($this->stats['events'][$event])) {
                $item = &$this->stats['events'][$event];
                $this->log(5, "Updating event $event");
                $this->updateEventStats($item, $args);
            } else {
                $this->stats['events'][$event] = array();
                $this->stats['events'][$event]['times']    = 1;
                $this->stats['events'][$event]['interval'] = 1;
                $this->stats['events'][$event]['last'] = time();
            }
        } else {
            $this->stats['rx_idle'] += time() - $this->stats['last_updated'];
            $this->log(6, "Updating idle time to: " . $this->stats['rx_idle']);
        }
        $this->stats['last_updated'] = time();
    }

    // XXX This should be enhanced to be able to track the different
    //     kinds of flood attacks (maybe in a different class)
    function updateEventStats(&$event, $args = array())
    {
        $event['times'] += 1;
        // XXX make a configurable param
        $int = 60;
        if ((time() - $event['last']) < $int) {
            $event['interval'] += 1;
        } else {
            $event['interval'] = 0;
        }
        $this->log(5, "event interval: " . $event['interval']);
        array_pop($args);
        foreach ($args as $k => $v) {
            if ($v) {
                $event[$k][$v] = isset($event[$k][$v]) ? $event[$k][$v] + 1 : 1;
                // To avoid stats flooding we only track the last 30 different ones
                if (count($event[$k]) > 30) {
                    $this->log(5, "Dropping key ($k) param");
                    array_shift($event[$k]);
                }
            }
        }
        $event['last'] = time();
    }

    function initStats()
    {
        $this->stats['rx_idle'] = 0;
        $this->stats['last_updated'] = time();
        $this->stats['events']  = array();
        $this->stats['started'] = time();
    }

    function getStats($label = null)
    {
        $this->stats['running'] = time() - $this->stats['started'];
        if ($label) {
            return isset($this->stats[$label]) ? $this->stats[$label] : false;
        }
        return $this->stats;
    }

    function log($level, $message)
    {
        if (in_array($level, $this->log_types)) {
           print date('H:i:s') . " " . trim($message) . "\n"; flush();
        }
    }

    /**
    * Sets which type of messages will be logged
    * @param mixed $codes int one code or array multiple codes
    */
    function logTypes($codes = array())
    {
        settype($codes, 'array');
        $this->log_types = $codes;
    }

    function getOption($option)
    {
        return isset($this->options[$option]) ? $this->options[$option] : null;
    }

    /**
    * Method to feed the clas with external information we want to
    * access from inside (for avoiding the "global" uglyness)
    *
    */
    function setExtra($extra)
    {
        $this->extra = $extra;
    }

    function getExtra($label = null)
    {
        if ($label) {
            return isset($this->extra[$label]) ? $this->extra[$label] : null;
        }
        return $this->extra;
    }

}

/**
* Basic class for handling the callbacks (events). This class should
* be extended by the user
*/
class Net_IRC_Event extends Net_IRC
{
    function event_error($origin, $orighost, $target, $params)
    {
        $this->log(0, "Error ocurred ($origin, $orighost, $target, $params)");
        // XXX add error handling
    }

    function event_ping($origin, $orighost, $target, $params)
    {
        $this->command("PONG :$params");
    }

    function fallback($origin, $orighost, $target, $params)
    {
        $this->buffer[] = array($origin, $target, $params);
        // Only store the last 25 lines
        if (count($this->buffer) > 25) {
            array_shift($this->buffer);
        }
    }

    function &getBuffer()
    {
        $buff = $this->buffer;
        $this->buffer = array();
        return $buff;
    }
}
?>