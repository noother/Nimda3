<?php

class Plugin_TwPlayerStatus extends Plugin
{
  public $triggers = array('!twpstatus', '!twp');
  public $helpText = 'Display the status of a player on Teeworlds.';
  public $usage = '<player>';
  public $helpCategory = 'Teeworlds';
  
  function isTriggered()
  {
    if (!isset($this->data['text']))
      {
	$this->printUsage();
	return;
      }
    $player = $this->data['text'];
    // API by EastBite !
    $url = 'http://ebeur.eastbit.net:8888/get/'.rawurlencode($player).'/matchall';
    $page = libHTTP::GET($url);
    if ($page === FALSE || strlen($page) == 0)
      {
	$this->reply('An error occured while retrieving the data.');
	return;
      }
    $result = json_decode($page, true);
    if ($result == NULL)
      {
	$this->reply('The data could not be parsed successfully.');
	return;
      }
    if (!isset($result["error"]))
      {
	for ($i = 0; $i < count($result["players"]); $i++)
	  {
	    $this->reply(sprintf("\x02%s\x02 is currently playing %s on server : %s.",
				 $result["players"][$i]["name"],
				 trim($result["servers"][$i]["map"]),
				 $result["servers"][$i]["name"]));
	  }
      }
    else
      {
	$this->reply(sprintf("No \x02%s\x02 found online.", $player));
      }
  }	
}

?>
