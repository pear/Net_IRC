<?php
ob_implicit_flush(true);
require_once '../IRC.php';

class MyClone_Bot extends Net_IRC_Event
{
    function &get_cloned()
    {
        global $net1, $net2;
        $server = $this->getOption('server');
        if ($server == $net1->getOption('server')) {
            $clone = &$net2;
        } else {
            $clone = &$net1;
        }
        return $clone;
    }

    function event_privmsg($origin, $orighost, $target, $params)
    {
        $clone = &$this->get_cloned();
        if ($target != $clone->getOption('nick')) {
            $clone->command("PRIVMSG $target :($origin) $params");
        }
    }

    function event_topic($origin, $orighost, $target, $params)
    {
        $clone = &$this->get_cloned();
        if ($origin != $clone->getOption('nick')) {
            $clone->command("TOPIC $target :$params (by $origin)");
            $clone->command("PRIVMSG $target :($origin) sets a new topic");
        }
    }

    function event_join($origin, $orighost, $target, $params)
    {
        $clone = &$this->get_cloned();
        if ($origin != $clone->getOption('nick')) {
            $clone->command("PRIVMSG $target :($origin) joins $target");
        }
    }

    function event_part($origin, $orighost, $target, $params)
    {
        $clone = &$this->get_cloned();
        $clone->command("PRIVMSG $target :($origin) parts from $target");
    }

    function event_kick($origin, $orighost, $target, $params)
    {
        list($channel, $kicked) = explode(' ', $target);
        if ($kicked == $this->getOption('nick')) {
            $this->command("JOIN $channel");
            $this->command("PRIVMSG $channel :Please $origin do not kick me, I'm doing a social service");
        }
    }

    function event_disconnect()
    {
        global $channel;
        do {
            sleep(2);
            $connected = $this->connect($this->options);
        } while (!$connected);
        $this->command("JOIN $channel");
    }

    function log($level, $mesg)
    {
        $server = $this->getOption('server');
        if (in_array($level, $this->log_types)) {
            echo "($server) $mesg\n";
        }
    }
}

$common = array(
    'port'      => 6667,
    'nick'      => 'Net_IRC',
    'realname'  => 'Net_IRC Bot',
    'identd'    => 'myident',
    'host'      => '10.10.11.2',
    'log_types' => array(0, 1, 2, 3)
);
$net1_opts = array_merge($common, array('server' => 'irc.west.gblx.net'));
//$net1_opts = array_merge($common, array('server' => 'localhost'));
$net2_opts = array_merge($common, array('server' => 'gibson.openprojects.net'));

$net1 = new MyClone_Bot;
$net2 = new MyClone_Bot;

if (!$net1->connect($net1_opts)) {
    die("Could not connect to: " . $net1_opts['server']);
}
if (!$net2->connect($net2_opts)) {
    die("Could not connect to: " . $net2_opts['server']);
}
$channel = '#pear';
$net1->command("JOIN $channel");
$net2->command("JOIN $channel");

while(true) {
    $net1->loopRead(null, null, true);
    usleep(250000);
    $net2->loopRead(null, null, true);
    usleep(250000);
}
?>