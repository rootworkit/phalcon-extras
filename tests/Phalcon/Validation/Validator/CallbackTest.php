<?php
/**
 * CallbackTest
 *
 * @package     Rootwork\Test\Phalcon\Validation\Validator
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     All Rights Reserved
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Test\Phalcon\Validation\Validator;

use PHPUnit_Framework_TestCase as TestCase;
use Phalcon\Validation;
use Phalcon\Validation\Message as ValidationMessage;
use Phalcon\Validation\Message\Group as MessageGroup;
use Rootwork\Phalcon\Validation\Validator\Callback as CallbackValidator;

/**
 * CallbackTest
 *
 * @package     Rootwork\Test\Phalcon\Validation\Validator
 */
class CallbackTest extends TestCase
{

    /**
     * Test validating a callback.
     *
     * @param MessageGroup|\Exception $expected
     * @param Validation                        $validation
     * @param array|null                        $data
     *
     * @dataProvider provideValidate
     */
    public function testValidate($expected, Validation $validation, array $data = null)
    {
        if ($expected instanceof \Exception) {
            $this->expectException(get_class($expected));
            $this->expectExceptionMessage($expected->getMessage());
        }

        $actual = $validation->validate($data);

        $this->assertEquals($expected, $actual);
    }

    /**
     * Provides data for testing the callback validator.
     *
     * @return array
     */
    public function provideValidate()
    {
        $validation = new Validation();
        $message    = new ValidationMessage(
            'Please enter a valid foo.',
            'foo',
            'Callback'
        );

        $validation->add('foo', new CallbackValidator([
            'message'       => $message->getMessage(),
            'allowEmpty'    => true,
            'callback'      => function ($value) {
                return $value == 'bar';
            },
        ]));

        return [
            [
                new MessageGroup([$message]),
                $validation,
                ['foo' => 'baz'],
            ],
            [
                new MessageGroup(),
                $validation,
                ['foo' => 'bar'],
            ],
            [
                new MessageGroup(),
                $validation,
                ['foo' => ''],
            ],
            [
                new Validation\Exception(
                    'The "callback" option must be set ' .
                    'to a callable function or method'
                ),
                (new Validation)->add('foo', new CallbackValidator()),
                ['foo' => 'bar'],
            ]
        ];
    }
}
