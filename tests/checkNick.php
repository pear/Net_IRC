<?php
require '../IRC.php';
$nicks = array(
    'aaa' => true,
    'a1'  => true,
    'a1a' => true,
    '{1}' => true,
    '[a]' => true,
    'a'   => true,
    '['   => true,
    '1'   => false,
    '1a'  => false,
    'aaaaaaaaaa' => false,
    '?'   => false,
    'a?'  => false
);
foreach ($nicks as $nick => $res) {
    if ($res != Net_IRC::checkNick($nick)) {
        print "The test for nick '$nick' failed\n";
    }
}
?>