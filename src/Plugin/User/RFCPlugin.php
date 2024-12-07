<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\Network\HTTP;

class RFCPlugin extends Plugin {

    public $triggers        = array('!rfc', '!bcp', '!std', '!fyi');
    public $helpCategory    = 'Internet';
    public $helpTriggers    = array('!rfc');
    public $usage           = '<num>';
    public $helpText        = 'Prints title and link for RFCs (!rfc) / BCPs (!bcp) / STDs (!std) / FYIs (!fyi)';

    private $cachefile;

    function onLoad() {
        $this->cachefile = 'tmp/cache/rfcindex';
    }

    function isTriggered() {
        if (empty($this->data['text'])) {
            $this->printUsage();
            return;
        }

        $docType = $this->data['trigger'];
        $docNum  = (int)$this->data['text'];

        $this->addJob('getRfc', array(
                                     'docType'   => substr($docType, 1),
                                     'docNum'    => $docNum,
                                     'cachefile' => $this->cachefile
                                ));
    }

    function onJobDone() {
        $this->reply($this->data['result']);
    }

    function getRfc($data) {

        $this->cachefile = $data['cachefile'];

        $HTTP = new HTTP('www.ietf.org', true);

        if (($index = $this->getCache()) === false) {
            echo "fetching new data\n";

            $index = $HTTP->GET('/rfc/rfc-index.xml');
            $index = preg_replace('/<rfc-index.*?>/is', '<rfc-index>', $index); // remove namespaces (they break simplexml xpath...)

            if ($index === false)
                return 'timeout';
            $this->putCache($index);
        }

        // pad docnum to 4 nums with zeroes
        $data['docNum'] = str_pad($data['docNum'], 4, '0', STR_PAD_LEFT);

        $xml   = simplexml_load_string($index);
        $query = $data['docType'] . '-entry[doc-id[.="' . strtoupper($data['docType']) . $data['docNum'] . '"]]';
        $docs  = $xml->xpath($query);

        if (!$docs)
            return 'doc does not exist';
        $doc = $docs[0];

        $link = 'http://www.ietf.org/rfc/';
        if ($data['docType'] != 'rfc') {
            // others use subfolders
            $link .= $data['docType'] . '/';
            // others dont use number padding... (but in rfc-index.xml, they do)
            $data['docNum'] = (int)$data['docNum'];
        }

        $link .= $data['docType'] . $data['docNum'] . '.txt';

        $reply = "\x02[" . $data['docType'] . "]\x02 " . (string)$doc->{'doc-id'};
        $title = (string)$doc->title;
        if (!empty($title))
            $reply .= ' - ' . $title;

        $reply .= ' - ' . $link;

        return $reply;
    }

    private function getCache($lifetime = 86400) {
        if (file_exists($this->cachefile) && time() - filemtime($this->cachefile) < $lifetime)
            return file_get_contents($this->cachefile);

        return false;
    }

    private function putCache($data) {
        file_put_contents($this->cachefile, $data);
        clearstatcache();
    }
}

?>
