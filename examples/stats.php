<?php
include '../IRC.php';
$irc = new Net_IRC_Event;

$options = array(
    'server'    => 'localhost',
    //'server'    => 'irc.west.gblx.net',
    //'server'    => '10.10.11.150',
    //'server'    => 'irc.openproyects.com',
    'port'      => 6667,
    //'port'      => 12000,
    'nick'      => 'net_irc',
    'realname'  => 'Tomas V.V.Cox',
    'identd'    => 'myident',
    'host'      => '10.10.11.2',
    'log_types'  => array(4, 5)
);

$irc->connect($options);
$irc->command("JOIN #pear");
$i = 0;
while (true) {
    $irc->readEvent();
    if ($i++ > 12) {
        print_r($irc->getStats());
        $i = 0;
    }
    usleep(500000);
}

?>