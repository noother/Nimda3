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

	public function onLoad() {
		if(!$this->getVar('leaderboard')) $this->saveVar('leaderboard', $this->getLeaderboard());
	}

	public function onInverval() {
		if(empty($this->getEnabledChannels())) return;

		$new_leaderboard = $this->getLeaderboard();
		$old_leaderboard = $this->getVar('leaderboard');

		$winners = $this->getWinners($old_leaderboard, $new_leaderboard);
		foreach($winners as $winner) {
			$message = sprintf("\x02[Leaderboard]\x02 %s %s %s and is now on rank %d (from %d) with %d points. He needs %d more points %s. %s",
				$winner['user'],
				self::VERBS[array_rand(self::VERBS)],
				$winner['losers'],
				$winner['rank_new'],
				$winner['rank_old'],
				$winner['points'],
				$winner['rankup_points'],
				self::MOTIVATIONAL_PHRASES[array_rand(self::MOTIVATIONAL_PHRASES)],
				self::MOTIVATIONAL_MESSAGES[array_rand(self::MOTIVATIONAL_MESSAGES)],
			);
			$this->sendToEnabledChannels($message);
		}

		$this->saveVar('leaderboard', $new_leaderboard);
	}

	private function getLeaderboard(): array {
		return (new WeChall())->getLeaderboard(self::PAGES);
	}

	private function getWinners($old, $new): array {
		$old_by_user = Arrays::hashtable($old, 'user');
		$new_by_user = Arrays::hashtable($new, 'user');
		$diff = Arrays::diffRelative($old_by_user, $new_by_user);

		$winners = [];
		foreach($diff as $user => $user_diff) {
			$rankups = ($user_diff['rank']??0)*-1;
			if($rankups <= 0) continue; // Only send messages for winners

			$rank_new = $new_by_user[$user]['rank'];
			$rank_old = $old_by_user[$user]['rank'];
			$points = $new[$rank_new-1]['points'];
			$rankup_points = $new[$rank_new-2]['points'] - $points + 1;

			if($rank_new == 100) {
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
				'rank_new' => $rank_new,
				'rank_old' => $rank_old,
				'losers'   => $losers,
				'points'   => $points,
				'rankup_points' => $rankup_points,
			];
		}

		return $winners;
	}
}
