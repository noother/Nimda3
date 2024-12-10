<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;

class SnipePlugin extends Plugin {
	public $triggers = array('!snipe');
	public $helpTriggers = array('!snipe');
	public $helpText = 'Kicks a random person who doesn\'t have at least +v. Lets you have a little fun if your channel gets mass infilitrated by bots.';
	
	private $slaps = array( // Taken from Lamb3 by gizmore - thx
		'adverbs' => array(
			array('silently', 30),
			array('happily', 40),
			array('viciously', 60),
			array('sneakily', 52),
			array('urgently', 50),
			array('quickly', 55),
			array('angrily', 60),
			array('sadly', 25),
			array('funnily', 28),
			array('grossly', 35),
			array('weirdly', 32),
			array('immediately', 30),
			array('suddenly', 35),
			array('hardly', 14),
			array('massively', 40),
			array('accidently', 15),
			array('periodically', 33),
			array('arbitarily', 32),
			array('ruthlessly', 45),
		),

		'verbs' => array(
			array('disconnects' , 60),
			array('slaps' , 50),
			array('blats' , 45),
			array('blunts' , 40),
			array('brats' , 40),
			array('blanches' , 59),
			array('bangs' , 55),
			array('cuffs' , 33),
			array('cuts' , 50),
			array('destroys' , 90),
			array('damages' , 75),
			array('disables' , 80),
			array('eleminates' , 79),
			array('punishes' , 66),
			array('tackles' , 45),
			array('hits' , 39),
			array('dangles' , 25),
			array('wobbles' , 15),
			array('kills', 70),
			array('battles', 55),
			array('screws', 50),
			array('debugs', 30),
			array('stamps', 30),
			array('faps', 18),
			array('punches', 24),
			array('combats', 38),
			array('eliminates', 71),
			array('trolls', 10),
			array('dissects', 50),
			array('stomps on', 60),
			array('kicks', 25),
			array('clubs', 20),
		),

		'adjectives' => array(
			array('a tiny' , 15),
			array('a very small' , 20),
			array('a rotten' , 25),
			array('a small' , 30),
			array('a' , 40),
			array('a large' , 50),
			array('a huge' , 60),
			array('an enormous' , 70),
			array('a giant' , 80),
			array('an evil' , 95),
			array('a funny', 35),
			array('a hellish', 66),
			array('the first', 50),
			array('the frist', 30),
			array('the one and only', 55),
			array('a monster', 65),
			array('a flying', 40),
			array('a gross', 35),
			array('a living', 37),
			array('a dangerous', 65),
			array('an epic', 80),
			array('a wonderful', 25),
			array('a grim', 50),
			array('a gay', 15),
			array('a poison', 35),
			array('a sharp', 29),
			array('an amazing', 40),
			array('an electric', 33),
			array('a bloody', 35),
			array('a mysterious', 40),
			array('a hot', 30),
			array('a wild', 30),
			array('an awesome', 40),
		),

		'items' => array(
			array('railgun' , 75), 
			array('vacuum cleaner' , 35),
			array('trout' , 25),
			array('monitor', 35),
			array('vacuum', 10),
			array('mouse', 13),
			array('used quote', 11),
			array('chainsaw', 60),
			array('rusty chainsaw', 65),
			array('book', 13),
			array('dictionary', 15),
			array('lightsabre', 78),
			array('toilet paper roll', 18),
			array('WC brush', 15),
			array('big fucking gun', 75),
			array('gravity gun', 65),
			array('frag grenade', 52),
			array('toy', 24),
			array('Deep Blue Computer', 36),
			array('garden gnome', 16),
			array('C64 home computer', 22),
			array('P5 microprocessor', 11),
			array('hadron collider', 50),
			array('hammer', 32),
			array('smithhammer', 38),
			array('pneumatic drill', 35),
			array('screwdriver', 24),
			array('mini elevator', 14),
			array('nunchaku', 45),
			array('ZX Spectrum home computer', 21),
			array('closet', 27),
			array('dandi', 19),
			array('gremlin', 23),
			array('dildo', 18),
			array('black hole', 96),
			array('space station', 74),
			array('comet', 54),
			array('doll', 23),
			array('tape', 17),
			array('strawberry', 12),
			array('sausage', 13),
			array('blog', 17),
			array('ninja', 51),
			array('steak', 20),
			array('sphere of healing', -20),
			array('mashup', 12),
			array('popcorn bucket', 18),
			array('dictionary', 22),
			array('google translation', 17),
			array('eel', 19),
			array('shark', 37),
			array('iron', 25),
			array('skull', 18),
			array('deer', 22),
			array('painting', 15),
			array('rainbow', 12),
			array('pot of gold', 23),
			array('grim', 50),
			array('duck', 15),
			array('XBOX', 20),
			array('PS3', 19),
			array('unicorn', 35),
			array('toilet paper roll', 13),
			array('flamethrower', 45),
			array('sabretooth', 21),
			array('CPU', 13),
			array('edge', 22),
			array('drone strike', 55),
			array('horse', 30),
			array('cactus', 22),
			array('puppy', 18),
			array('boar', 29),
			array('parrot', 24),
			array('link', 21),
		)
);
	
	function isTriggered() {
		
		if($this->Channel === false) {
			$this->reply('This command only works in a channel.');
			return;
		}
		
		if($this->User->mode != '@') {
			$this->reply('You have to be an operator in this channel to use '.$this->data['trigger'].'.');
			return;
		}
		
		if($this->Server->Me->modes[$this->Channel->id] != '@') {
			$this->reply('I need operator status to fulfill your wish.');
			return;
		}
		
		$users = array();
		foreach($this->Channel->users as $User) {
			if(empty($User->modes[$this->Channel->id])) {
				$users[] = $User;
			}
		}
		
		if(empty($users)) {
			$this->reply('There are no possible victims left.');
			return;
		}
		
		$Victim = $users[rand(0,sizeof($users)-1)];
		
		$this->Channel->kick($Victim, $this->getKickMessage($Victim->nick));
		
	}
	
	private function getKickMessage($nick) {
		$me = $this->Server->Me->nick;
		$adverb = $this->slaps['adverbs'][array_rand($this->slaps['adverbs'])];
		$verb = $this->slaps['verbs'][array_rand($this->slaps['verbs'])];
		$adjective = $this->slaps['adjectives'][array_rand($this->slaps['adjectives'])];
		$item = $this->slaps['items'][array_rand($this->slaps['items'])];
		
		$text = $me.' '.$adverb[0].' '.$verb[0].' '.$nick.' with '.$adjective[0].' '.$item[0].'.';
		$damage = $adverb[1] + $verb[1] + $adjective[1] + $item[1];
		$text.= ' ('.$damage.' damage)';
	
	return $text;
	}
		
}

?>
