<?php
/**
 * IpAddress
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
 * IpAddress
 *
 * @package     Rootwork\Phalcon\Validation\Validator
 */
class IpAddress extends Validator implements ValidatorInterface
{

    /**
     * Executes the validation.
     *
     * @param Validation $validation
     * @param string     $field
     *
     * @return boolean
     */
    public function validate(Validation $validation, $field)
    {
        $value = $validation->getValue($field);

        if (!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6)) {
            $message = $this->getOption('message');

            if (!$message) {
                $message = 'The IP is not valid';
            }

            $validation->appendMessage(new Message($message, $field, 'IpAddress'));

            return false;
        }

        return true;
    }
}
