<?php
/**
 * DateTimeLocal
 *
 * @package     Rootwork\Phalcon\Forms\Element
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Forms\Element;

use Phalcon\Tag;
use Phalcon\Forms\Element;
use Phalcon\Forms\ElementInterface;

/**
 * DateTimeLocal element
 *
 * input[type=datetime-local] for forms
 *
 * @package     Rootwork\Phalcon\Forms\Element
 */
class DateTimeLocal extends Element implements ElementInterface
{

    /**
     * Renders the element as HTML.
     *
     * @param array|null $attributes
     *
     * @return string
     */
    public function render($attributes = null)
	{
		return Tag::dateTimeLocalField($this->prepareAttributes($attributes));
	}
}
