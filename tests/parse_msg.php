<?php
require_once '../IRC.php';
$tests = array(
    'PING :foo',
    ':cox!localhost PRIVMSG #pear :hi my name is Luser',
    ':sapiens.dhis.org 353 net_irc = #pear :net_irc cox',
    ':net_irc!~myident@sapiens.idecnet.com JOIN :#pear',
    ':cox!~cox@sapiens.idecnet.com PART #pear'
);
$irc = new Net_IRC;
foreach($tests as $t) {
    echo "Parsing: ($t)\n";
    print_r($irc->parseResponse($t));
}

?>