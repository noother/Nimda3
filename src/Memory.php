<?php

namespace Nimda;

class Memory {
	public static function read(string $name, string $type='bot', string $target='me'): mixed {
		return Common::getBot()->getPermanent($name, $type, $target);
	}

	public static function write(string $name, mixed $value, string $type='bot', string $target='me'): void {
		Common::getBot()->savePermanent($name, $value, $type, $target);
	}

	public static function delete($name, $type='bot', $target='me'): bool {
		return Common::getBot()->removePermanent($name, $type, $target);
	}
}
