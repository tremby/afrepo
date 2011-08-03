<?php

class NullClassifier extends AFClassifierBase {
	public function getName() {
		return "Null classifier";
	}
	public function getDescription() {
		return "Fail to classify any tune";
	}
	protected function runClassifier($filepath) {
		return false;
	}
}
