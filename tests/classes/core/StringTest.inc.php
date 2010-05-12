<?php

/**
 * @file tests/classes/core/StringTest.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class StringTest
 * @ingroup tests_classes_core
 * @see String
 *
 * @brief Tests for the String class.
 */

import('lib.pkp.tests.PKPTestCase');
import('lib.pkp.classes.core.String');

class StringTest extends PKPTestCase {
	/**
	 * @covers String::titleCase
	 */
	public function testTitleCase() {
		$originalTitle = 'AND This IS A TEST title';
		self::assertEquals('And This is a Test Title', String::titleCase($originalTitle));
	}

	/**
	 * @covers String::trimPunctuation
	 */
	public function testTrimPunctuation() {
		$trimmedChars = array(
			' ', ',', '.', ';', ':', '!', '?',
			'(', ')', '[', ']', '\\', '/'
		);

		foreach($trimmedChars as $trimmedChar) {
			self::assertEquals('trim.med',
					String::trimPunctuation($trimmedChar.'trim.med'.$trimmedChar));
		}
	}
}
?>
