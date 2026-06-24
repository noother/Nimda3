<?php

namespace Nimda\Plugin\User;

use Nimda\Plugin\Plugin;
use noother\ChallengeSite\WeChall;
use noother\Library\Arrays;

class WeChallLeaderboardAnnouncerPlugin extends Plugin {
	public $enabledByDefault = false;
	public $interval = 300;

	private const PAGES = 2;

	private const VERBS = [
		'annihilated', 'bulldozed', 'conquered', 'crushed', 'decimated', 'demolished', 'destroyed', 'dethroned', 'dismantled', 'dominated', 'eclipsed',
		'eradicated', 'floored', 'humbled', 'nullified', 'obliterated', 'outclassed', 'outdid', 'outlasted', 'outmatched', 'outpaced', 'outperformed',
		'outshone', 'outsmarted', 'overpowered', 'overthrew', 'overtook', 'overwhelmed', 'prevailed against', 'pummeled', 'routed', 'shellacked',
		'skunked', 'smashed', 'squashed', 'steamrolled', 'surpassed', 'swept', 'toppled', 'torpedoed', 'triumphed over', 'trounced', 'vanquished', 'wiped out',
	];

	private const MOTIVATIONAL_PHRASES = [
		'for a chance at the top', 'for a new milestone', 'for absolute domination', 'for another conquest', 'for another victory', 'for eternal bragging rights', 'for everlasting glory',
		'for fame and fortune', 'for glory and honor', 'for greatness', 'for greatness in the game', 'for immortality in the rankings', 'for leaderboard immortality', 'for the big leagues',
		'for the crown', 'for the hall of fame', 'for the leaderboard glory', 'for the next challenge', 'for the next milestone', 'for the next triumph', 'for the thrill of victory',
		'for the throne', 'for the ultimate goal', 'for the ultimate showdown', 'for the victory parade', 'for the win', 'for their next conquest', 'for their rightful place',
		'for ultimate supremacy', 'for unrivaled supremacy', 'to ascend even higher', 'to break new records', 'to cement their rank', 'to continue the streak', 'to keep the fire alive',
		'to keep the momentum going', 'to maintain their lead', 'to make history', 'to make their mark', 'to prove they belong', 'to push the limits', 'to reach the stars',
		'to remain a legend', 'to remain unstoppable', 'to rise even higher', 'to secure their dominance', 'to shatter expectations', 'to solidify their position', 'to write their name in legend'
	];

	private const MOTIVATIONAL_MESSAGES = [
		'Good job!', 'Way to go!', 'Keep it up!', 'Fantastic work!', 'Excellent job!', 'You rock!', 'Nice one!', 'Amazing!', 'Bravo!', 'Outstanding!', 'Superb!', 'Incredible!',
		'Stellar performance!', 'Exceptional!', 'Keep crushing it!', 'You’re on fire!', 'Well played!', 'Hats off to you!', 'Wonderful!', 'Magnificent!', 'Impressive!', 'Nice work!',
		'Keep blazing ahead!', 'Great job!', 'Sensational!', 'Tremendous effort!', 'Glorious!', 'Keep shining!', 'You’re unstoppable!', 'Phenomenal!', 'Keep dominating!', 'Way to shine!',
		'Remarkable!', 'You’re killing it!', 'Take a bow!', 'Kudos!', 'Keep soaring!', 'You’ve got this!', 'Unbelievable!', 'Just wow!', 'Keep leading!', 'Jaw-dropping!', 'Excellent work!',
		'Keep smashing it!', 'Mind-blowing!', 'Epic!', 'Keep conquering!', 'Legendary!', 'Splendid!', 'Keep climbing!'
	];

	private const WINNER_MESSAGES = [
		'All hail the new grandmaster!',
		'The throne changes hands!',
		'The race has a winner... for now!',
		'Their reign begins today!',
		'Everyone else is playing for second place now.',
		'Somebody stop this menace!',
		'The boss battle has spawned.',
		'Achievement unlocked: World\'s Largest Bragging Rights.',
		'The throne has entered safe mode.',
		'The leaderboard would like a word with HR.',
		'Congratulations, you\'ve become the problem.',
		'Main character energy detected.',
		'The crown is now officially glued on.',
		'Rank 1 acquired. Touch grass optional.',
		'The throne has been claimed. Let the reign begin!',
		'Rank 2 has left the chat.',
		'We checked the logs. Somehow it\'s legal.',
		'Somebody forgot to nerf this player.',
		'The leaderboard\'s HR department has concerns.',
		'The competition has been reduced to a documentary.',
		'The throne has changed passwords.',
		'The boss music is now permanently enabled.',
		'Congratulations on becoming a loading screen tip.',
		'The leaderboard is no longer a democracy.',
		'The rest of you may now resume fighting over scraps.',
		'Congratulations, you\'ve unlocked "Target on Your Back".',
		'Security has been notified.',
		'The leaderboard would like to file a complaint.',
		'Admins are investigating possible witchcraft.',
		'This seems statistically suspicious.',
		'The economy may never recover.',
		'Witness protection has been recommended for rank 2.',
		'The competition has been downgraded to spectators.',
		'The tutorial is officially over.',
		'The king has arrived. Please update your taxes accordingly.',
		'This was not in the script.',
		'The prophecy has been fulfilled.',
		'Rank 2 is typing...',
		'This is why we can\'t have nice things.',
		'Somebody call a rollback.',
		'We asked for competition, not domination.',
		'This victory has been reported to the authorities.',
		'The anti-monopoly committee is watching closely.',
		'The leaderboard is now under new management.',
		'Several challengers have fainted.',
		'Reality has been patched accordingly.',
		'Congratulations on winning today\'s argument.',
		'Everybody else may now update their expectations.',
		'Rank 1 has been captured and domesticated.',
		'The throne has accepted its new owner.',
		'A new apex predator has appeared.',
		'The patch notes forgot to mention this.',
		'The competition is now considered endangered.',
		'Somebody forgot to nerf this player.',
		'The throne was seized in a hostile takeover.',
		'The throne has been conquered without mercy.',
		'Somebody tell the historians.',
	];

	public function onLoad() {
		if(!$this->getVar('leaderboard')) $this->saveVar('leaderboard', $this->getLeaderboard());
	}

	public function onInterval() {
		if(empty($this->getEnabledChannels())) return;

		$new_leaderboard = $this->getLeaderboard();
		if(!isset($new_leaderboard)) return; // Wechall down
		$old_leaderboard = $this->getVar('leaderboard');

		$winners = $this->getWinners($old_leaderboard, $new_leaderboard);
		foreach($winners as $winner) {
			$message = sprintf("\x02[Leaderboard]\x02 \x02%s\x02 %s \x02%s\x02 and is now on rank \x02%d\x02 with \x02%d\x02 points.",
				$winner['user'],
				self::VERBS[array_rand(self::VERBS)],
				$winner['losers'],
				$winner['rank'],
				$winner['points'],
			);

			if($winner['rank'] == 1) {
				$message.= ' '.self::WINNER_MESSAGES[array_rand(self::WINNER_MESSAGES)];
			} else {
				$message.= sprintf(" He needs \x02%d\x02 more points %s. %s",
					$winner['rankup_points'],
					self::MOTIVATIONAL_PHRASES[array_rand(self::MOTIVATIONAL_PHRASES)],
					self::MOTIVATIONAL_MESSAGES[array_rand(self::MOTIVATIONAL_MESSAGES)],
				);
			}

			$this->sendToEnabledChannels($message);
		}

		$this->saveVar('leaderboard', $new_leaderboard);
	}

	private function getLeaderboard(): ?array {
		return (new WeChall())->getLeaderboard(self::PAGES);
	}

	private function getWinners($old, $new): array {
		$old_by_user = Arrays::hashtable($old, 'user');
		$new_by_user = Arrays::hashtable($new, 'user');
		$diff = Arrays::diffRelative($old_by_user, $new_by_user);

		$winners = [];
		foreach($diff as $user => $user_diff) {
			if(array_key_exists('user', $user_diff) && $user_diff['user'] === null) break; // An account was deleted. Don't do anything to prevent mass announcements

			$rankups = ($user_diff['rank']??0)*-1;
			if($rankups <= 0) continue; // Only send messages for winners

			$rank = $new_by_user[$user]['rank'];
			$points = $new[$rank-1]['points'];
			$rankup_points = $rank != 1 ? $new[$rank-2]['points'] - $points + 1 : 0;

			if($rank == 100) {
				$losers = "no one, because he's rank 100 duh";
			} else {
				$losers = array_column(array_reverse(array_slice($new, $new_by_user[$user]['rank'], $rankups)), 'user');
				$losers = implode(', ', $losers);
				if(false !== $last_comma = strrpos($losers, ',')) {
					$losers = substr($losers, 0, $last_comma).' and '.substr($losers, $last_comma+2);
				}
			}

			$winners[] = [
				'user'     => $user,
				'rank'     => $rank,
				'losers'   => $losers,
				'points'   => $points,
				'rankup_points' => $rankup_points,
			];
		}

		return $winners;
	}
}
