<?php

class NullClassifier extends AFClassifierBase {
	public function getName() {
		return "Null classifier";
	}
	public function getDescription() {
		return "Fail to classify any tune";
	}
	public function available() {
		return false;
	}
	protected function runClassifier($filepath) {
		return false;
	}
}
