<?php
/**
 * Color
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
 * Color element
 *
 * input[type=color] for forms
 *
 * @package     Rootwork\Phalcon\Forms\Element
 */
class Color extends Element implements ElementInterface
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
		return Tag::colorField($this->prepareAttributes($attributes));
	}
}
