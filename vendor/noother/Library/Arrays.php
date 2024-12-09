<?php

namespace noother\Library;

class Arrays {

	/*
	 * This works with multidimenstional arrays, because assigning a new value to the array in the foreach by reference
	 * extends the foreach, and if this one is also an array, it will be flattened further
	*/
	public static function flatten(array $array): array {
		foreach($array as $key => &$value) {
			if(!is_array($value)) continue;

			foreach($value as $key2 => $value2) {
				$array["$key.$key2"] = $value2;
			}
			unset($array[$key]);
		}

	return $array;
	}

	public static function hash(array $array): string {
		$flattened = self::flatten($array);

		// Sort array by keys, so we get the same hash for differently ordered arrays
		$keys = array_keys($flattened);
		sort($keys);

		$hash_array = [];
		foreach($keys as $key) {
			$hash_array[$key] = $flattened[$key];
		}

	return sha1(json_encode($hash_array));
	}

	public static function hashtable(array $array, string $key, array $options=[]): array {
		$options+= ['unique' => true, 'normalize' => false, 'remove_key' => false, 'bool' => false];

		$new = [];
		foreach($array as $item) {
			if($options['normalize']) {
				if(is_bool($options['normalize'])) $new_key = trim(strtolower($item[$key]));
				else $new_key = call_user_func($options['normalize'], $item[$key]);
			} else {
				$new_key = $item[$key];
			}

			if($options['remove_key']) unset($item[$key]);
			if($options['bool']) $item = true;
			if($options['unique']) $new[$new_key] = $item;
			else $new[$new_key][] = $item;
		}

	return $new;
	}

	/**
	 * Extract one or several keys from the values of an array
	 *
	 * @param string|array $keys
	 */
	public static function extract(array $array, $keys): array {
		$extracted = [];

		foreach($array as $item) {
			if(is_array($keys)) {
				$append = [];
				foreach($keys as $key) {
					$append[$key] = $item[$key];
				}
			} else {
				$append = $item[$keys];
			}

			$extracted[] = $append;
		}

		return $extracted;
	}

	public static function removeKeys(array $array, array $keys): array {
		foreach($keys as $key) {
			unset($array[$key]);
		}

	return $array;
	}

	public static function cleanup(array &$array, array $options=[]): void {
		$options+= ['remove_empty_arrays' => true, 'remove_empty_values' => true];

		foreach($array as $key => &$value) {
			if(is_array($value)) {
				self::cleanup($value);
				if($options['remove_empty_arrays'] && empty($value)) unset($array[$key]);
				continue;
			}

			if($options['remove_empty_values'] && empty($value)) unset($array[$key]);
		}
	}

	// Return an array that only contains the differences between the 2 arrays
	public static function diff(array $first, array $second, array $exclude_org=[]): array {
		$exclude = array_flip($exclude_org);
		$diff = [];

		// Check if there are keys only in first
		foreach($first as $key => $value) {
			if(isset($exclude[$key])) continue;

			if(!array_key_exists($key, $second)) {
				$diff[0][$key] = is_array($value) ? self::removeKeys($value, $exclude_org) : $value;
				$diff[1][$key] = null;
			}
		}

		// Check if there are keys only in second
		foreach($second as $key => $value) {
			if(isset($exclude[$key])) continue;

			if(!array_key_exists($key, $first)) {
				$diff[0][$key] = null;
				$diff[1][$key] = is_array($value) ? self::removeKeys($value, $exclude_org) : $value;
			}
		}

		// Check if values are the same
		foreach($first as $key => $value1) {
			if(!array_key_exists($key, $second)) continue;
			if(isset($exclude[$key])) continue;

			$value2 = $second[$key];

			if(is_array($value1)) {
				$array_diff = self::diff($value1, $value2, $exclude_org);
				if(!empty($array_diff)) {
					$diff[0][$key] = $array_diff[0];
					$diff[1][$key] = $array_diff[1];
				}
				continue;
			}

			if(is_float($value1) || is_float($value2)) {
				// Compare floats as strings to evade floating point imprecision stuff
				if((string)$value1 != (string)$value2) {
					$diff[0][$key] = $value1;
					$diff[1][$key] = $value2;
				}
			} elseif($value1 != $value2) { // Intentional lose compare
				$diff[0][$key] = $value1;
				$diff[1][$key] = $value2;
			}
		}

	return $diff;
	}

	public static function diffRelative(array $first, array $second, array $exclude_org=[]): array {
		$diff = self::diff($first, $second, $exclude_org);
		if(empty($diff)) return [];

		$new = [];
		foreach($diff[0] as $key => $value) {
			if(is_array($value)) {
				$new[$key] = self::diffRelative($value, $diff[1][$key]);
				continue;
			}

			if(is_numeric($value) && is_numeric($second[$key]??0)) {
				$new[$key] = ($second[$key]??0) - $value;
			} else {
				$new[$key] = $second[$key];
			}
		}

	return $new;
	}

	// Recursively overwrite values from $patch in $array
	public static function patch(?array $array, ?array $patch, array $skip=[]): array {
		if(empty($array)) return $patch;
		if(empty($patch)) return $array;

		foreach($patch as $field => $value) {
			if(in_array($field, $skip)) continue;

			if(is_array($value)) {
				$array[$field] = self::patch($array[$field]??[], $value, $skip);
			} else {
				$array[$field] = $value;
			}
		}

	return $array;
	}

	// Recursively mathematically add values from $patch to $array
	public static function patchRelative(?array $array, ?array $patch, array $skip=[], array $absolute=[], int $round_precision=null): array {
		// If one of the arrays doesn't exist, return the other
		if(empty($array)) return $patch;
		if(empty($patch)) return $array;

		// Add $patch to $array
		foreach($patch as $field => $value) {
			if(in_array($field, $skip)) continue;

			if(!isset($array[$field]) || in_array($field, $absolute)) {
				$array[$field] = $value;
			} elseif(is_array($value)) {
				$array[$field] = self::patchRelative($array[$field]??[], $value, $skip, $absolute);
			} else {
				$array[$field]+= $value;
				if(isset($round_precision)) $array[$field] = round($array[$field], $round_precision);
			}
		}

	return $array;
	}
}
