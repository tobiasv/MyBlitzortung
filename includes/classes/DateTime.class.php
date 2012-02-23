<?php


class Timestamp {

	private $seconds;
	private $nanoseconds;

	public function __construct($seconds=0, $nanoseconds=0) {
		$this->Set($seconds, $nanoseconds);
	}

	public function Set($seconds, $nanoseconds) {
		$this->seconds = $seconds;
		$this->nanoseconds = $nanoseconds;
	}

	public function isBefore($other) {
		if ($this->seconds == $other->seconds) {
			return $this->nanoseconds < $other->nanoseconds;
		} else {
			return $this->seconds < $other->seconds;
		}
	}

	public function usDifference($other) {
		return ($this->seconds - $other->seconds) * 1e6 + ($this->nanoseconds - $other->nanoseconds) * 1e-3;
	}

	public function __toString() {
		return gmdate('Y-m-d H:i:s', $this->seconds) . '.' . sprintf("%09d", $this->nanoseconds);
	}
}



?>