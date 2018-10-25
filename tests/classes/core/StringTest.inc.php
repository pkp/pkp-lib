<?php

/**
 * @file tests/classes/core/StringTest.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StringTest
 * @ingroup tests_classes_core
 * @see String
 *
 * @brief Tests for the String class.
 */

import('tests.PKPTestCase');
import('core.PKPString');

class StringTest extends PKPTestCase {
	/**
	 * @covers PKPString::titleCase
	 */
	public function testTitleCase() {
		$originalTitle = 'AND This IS A TEST title';
		self::assertEquals('And This is a Test Title', PKPString::titleCase($originalTitle));
	}

	/**
	 * @covers PKPString::trimPunctuation
	 */
	public function testTrimPunctuation() {
		$trimmedChars = array(
			' ', ',', '.', ';', ':', '!', '?',
			'(', ')', '[', ']', '\\', '/'
		);

		foreach($trimmedChars as $trimmedChar) {
			self::assertEquals('trim.med',
					PKPString::trimPunctuation($trimmedChar.'trim.med'.$trimmedChar));
		}
	}
}
?>
