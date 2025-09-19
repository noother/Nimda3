<?php

namespace Nimda;

class Common {
	public static function getBot(): Nimda {
		return Nimda::getInstance();
	}

	/**
	 * Returns the current Bot's tick time (which might be different from the actual time if the Bot is stuck on something
	 * This is usually the truth, for timing reasons
	 */
	public static function getTime(): int {
		return static::getBot()->getTime();
	}
}
