<?php
ob_implicit_flush(true);
require_once '../IRC.php';

class My_Net_IRC_Client extends Net_IRC_Event
{
    function event_motd()
    {
        $buffer = $this->getBuffer();
        echo "MOTD:\n";
        foreach ($buffer as $output) {
            print trim($output[2]) . "\n";
        }
        echo "/MOTD\n";
    }

    function event_notice($origin, $orighost, $target, $params)
    {
        echo "notice: $origin, $orighost, $target, $params!\n";
    }

    function event_whois()
    {
        $buffer = $this->getBuffer();
        echo "WHOIS:\n";
        foreach ($buffer as $output) {
            print trim($output[2]) . "\n";
        }
        echo "/WHOIS\n";
    }

    function event_join($origin, $orighost, $target, $params)
    {
        echo "$origin, $orighost, $target, $params\n";
        echo "$origin has joined $target\n";
    }

    function event_names()
    {
        $buffer = $this->getBuffer();
        echo "NAMES:\n";
        foreach ($buffer as $output) {
            print trim($output[2]) . "\n";
        }
        echo "/NAMES\n";
    }

    function event_privmsg($origin, $orighost, $target, $params)
    {
        if ($target == $this->getOption('nick')) {
            echo "[$origin($orighost)] $params\n";
        } else {
            echo "<$target $origin> $params\n";
        }
    }

    function event_kick($origin, $orighost, $target, $params)
    {
        list($channel, $kicked) = explode(' ', $target);
        if ($kicked == $this->getOption('nick')) {
            $this->command("JOIN $channel");
        } else {
            echo "$origin kicked $kicked\n";
        }
    }

    function event_part($origin, $orighost, $target, $params)
    {
        echo "$origin left channel $target\n";
    }

    function event_disconnect()
    {
        do {
            sleep(2);
            $connected = $this->connect($this->options);
        } while (!$connected);
    }

}

function readlinel()
{
    static $fd;
    if (!is_resource($fd)) {
        $fd = fopen('php://stdin', 'r');
    }
    return fgets($fd, 1024);
}

$options = array(
    'server'    => 'localhost',
    //'server'    => 'irc.west.gblx.net',
    //'server'    => '10.10.11.150',
    //'server'    => 'irc.openproyects.com',
    'port'      => 6667,
    //'port'      => 12000,
    'nick'      => 'netirccli',
    'realname'  => 'Tomas V.V.Cox',
    'identd'    => 'myident',
    'host'      => '10.10.11.2',
    //'loglevel'  => 3
);
$irc = new My_Net_IRC_Client;

if (!$irc->connect($options)) {
    die('could not connect');
}
$irc->command('JOIN #pear');

$pid = pcntl_fork();
if ($pid == -1) {
    die("could not fork");
} elseif ($pid) {
    while (true) {
        $line = readlinel();
        if (trim($line)) {
            if (!ereg('^[A-Z]', $line)) {
                $irc->command("PRIVMSG #pear :$line");
            } else {
                $irc->command($line);
            }
        }
    }
} else {
    $irc->loopRead();
}
?>