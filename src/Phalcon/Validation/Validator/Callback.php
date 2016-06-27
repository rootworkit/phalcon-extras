<?php
/**
 * Callback validator
 *
 * @package     Rootwork\Phalcon\Validation\Validator
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     All Rights Reserved
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Validation\Validator;

use Phalcon\Validation;
use Phalcon\Validation\Validator;

/**
 * Callback validator
 *
 * @package     Rootwork\Phalcon\Validation\Validator
 */
class Callback extends Validator
{

    /**
     * Executes the validation
     *
     * @param Validation $validation
     * @param string     $field
     *
     * @return bool
     * @throws Validation\Exception
     */
    public function validate(Validation $validation, $field)
    {
        $value = $validation->getValue($field);

		if ($this->hasOption('allowEmpty') && empty($value)) {
            return true;
        }

        $callback = $this->getOption('callback');

        if (!is_callable($callback)) {
            throw new Validation\Exception(
                'The "callback" option must be set ' .
                'to a callable function or method'
            );
        }

        if (!$callback($value)) {
            $label = $this->getOption('label');
            if (!$label) {
                $label = $validation->getLabel($field);
            }

            $message        = $this->getOption('message');
            $replacePairs   = [':field' => $label];

            $validation->appendMessage(new Validation\Message(
                strtr($message, $replacePairs),
                $field,
                'Callback')
            );

            return false;
        }

        return true;
    }
}
