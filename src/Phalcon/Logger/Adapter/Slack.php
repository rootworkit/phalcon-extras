<?php
/**
 * Phalcon log adapter for Slack
 *
 * @package     Rootwork\Phalcon\Logger\Adapter
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 * @see         https://slack.com
 */

namespace Rootwork\Phalcon\Logger\Adapter;

use Phalcon\Logger\Adapter;
use Phalcon\Logger\AdapterInterface;
use Phalcon\Logger\Exception;
use Rootwork\Phalcon\Logger\Formatter\Slack as SlackFormatter;
use Maknz\Slack\Client as SlackClient;

/**
 * Phalcon log adapter for Slack
 *
 * @package     Rootwork\Phalcon\Logger\Adapter
 * @see         https://slack.com
 */
class Slack extends Adapter implements AdapterInterface
{

    /**
     * The Slack webhook URL.
     *
     * @var string
     */
    protected $url;

    /**
     * Adapter options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Slack client instance.
     *
     * Note: The client provides a callable interface to the message class.
     *
     * @var SlackClient
     */
    protected $client;

    /**
     * Slack constructor.
     *
     * @param string $url
     * @param array  $options
     *
     * @throws Exception
     */
    public function __construct($url, array $options = [])
    {
        $this->url = $url;

        if (isset($options['level'])) {
            $this->setLogLevel($options['level']);
        }

        $this->options = $options;
    }

    /**
     * Internal log writer.
     *
     * @param string    $message
     * @param integer   $type
     * @param integer   $time
     * @param array     $context
     */
    public function logInternal($message, $type, $time, array $context = [])
    {
        $formatter      = $this->getFormatter();
        $slackMessage   = $formatter->format($message, $type, $time, $context);

        $this->getClient()->sendMessage($slackMessage);
    }

    /**
     * Returns the internal formatter
     *
     * @return \Phalcon\Logger\Formatter|SlackFormatter
     */
    public function getFormatter()
    {
        if (!$this->_formatter) {
            $this->_formatter = new SlackFormatter(
                $this->getClient(),
                $this->getOption('formatterOptions', [])
            );
        }

        return $this->_formatter;
    }

    /**
     * Closes the logger
     *
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * Get an option by name.
     *
     * @param string    $name
     * @param mixed     $default
     *
     * @return mixed|null
     */
    public function getOption($name, $default = null)
    {
        if (array_key_exists($name, $this->options)) {
            return $this->options[$name];
        }

        return $default;
    }

    /**
     * Get the Slack client instance.
     *
     * @return SlackClient
     */
    protected function getClient()
    {
        if (!$this->client) {
            $settings       = $this->getOption('clientSettings', []);
            $this->client   = new SlackClient($this->url, $settings);
        }

        return $this->client;
    }
}
