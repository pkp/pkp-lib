<?php

/**
 * @file tests/classes/core/JSONTest.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class JSONTest
 * @ingroup tests_classes_core
 *
 * @see JSONMessage
 *
 * @brief Tests for the JSON class.
 */

namespace PKP\tests\classes\core;

use PKP\core\JSONMessage;
use PKP\tests\PKPTestCase;
use stdClass;

class JSONTest extends PKPTestCase
{
    /**
     * @covers JSONMessage
     */
    public function testGetString()
    {
        // Create a test object.
        $testObject = new stdClass();
        $testObject->someInt = 5;
        $testObject->someFloat = 5.5;
        $json = new JSONMessage(
            $status = true,
            $content = 'test content',
            $elementId = '0',
            $additionalAttributes = ['testObj' => $testObject]
        );
        $json->setEvent('someEvent', ['eventDataKey' => ['item1', 'item2']]);

        // Render the JSON message.
        $expectedString = '{"status":true,"content":"test content",' .
            '"elementId":"0","events":[{"name":"someEvent","data":{"eventDataKey":["item1","item2"]}}],' .
            '"testObj":{"someInt":5,"someFloat":5.5}}';
        self::assertEquals($expectedString, $json->getString());
    }
}
