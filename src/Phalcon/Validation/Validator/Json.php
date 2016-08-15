<?php
/**
 * Json
 *
 * @package     Rootwork\Phalcon\Validation\Validator
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Validation\Validator;

use Phalcon\Validation\Validator;
use Phalcon\Validation\ValidatorInterface;
use Phalcon\Validation;
use Phalcon\Validation\Message;

/**
 * Json
 *
 * @package     Rootwork\Phalcon\Validation\Validator
 */
class Json extends Validator implements ValidatorInterface
{

    /**
     * Executes the validation.
     *
     * @param mixed  $validation
     * @param string $field
     *
     * @return bool
     */
    public function validate(Validation $validation, $field)
    {
        $value = $validation->getValue($field);
        
        if (json_decode($value) === null) {
            $message = $this->getOption('message');

            if (!$message) {
                $message = 'The value is not valid JSON';
            }

            $validation->appendMessage(new Message($message, $field, 'Json'));

            return false;
        }

        return true;
    }
}
