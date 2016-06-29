<?php
/**
 * Slack message formatter
 *
 * @package     Rootwork\Phalcon\Logger\Formatter
 * @copyright   Copyright (c) 2016
 * @license     All Rights Reserved
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Phalcon\Logger\Formatter;

use Phalcon\Logger;
use Phalcon\Logger\Formatter;
use Phalcon\Logger\FormatterInterface;
use Phalcon\Logger\Formatter\Line as LineFormatter;
use Maknz\Slack\Client as SlackClient;
use Maknz\Slack\Message as SlackMessage;
use Maknz\Slack\Attachment as SlackAttachment;

/**
 * Slack message formatter
 *
 * @package     Rootwork\Phalcon\Logger\Formatter
 */
class Slack extends Formatter implements FormatterInterface
{

    /**
     * Slack client instance.
     *
     * @var SlackClient
     */
    protected $client;

    /**
     * Formatter options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * Slack formatter constructor.
     *
     * @param SlackClient   $client
     * @param array         $options
     */
    public function __construct(SlackClient $client, array $options = [])
    {
        $this->client   = $client;
        $this->options  = $options;
    }

    /**
     * Applies a format to a message before sent it to the internal log
     *
     * @param string $message
     * @param int    $type
     * @param int    $timestamp
     * @param mixed  $context
     *
     * @return SlackMessage
     */
    public function format($message, $type, $timestamp, $context = null)
    {
        if ($this->getOption('useAttachment', true)) {
            return $this->formatAsAttachment(
                $message, $type, $timestamp, $context
            );
        }

        $slackMessage   = new SlackMessage($this->client);
        $format         = $this->getOption(
            'format', '%date% *%type%:* %message%'
        );
        $lineFormatter  = new LineFormatter($format);
        $text           = $lineFormatter->format(
            $message, $type, $timestamp, $context
        );
        $text           = str_replace(PHP_EOL, '', $text);

        if ($this->getOption('alertChannel')) {
            $text = "@channel: $text";
        }

        $slackMessage->setText($text);

        return $slackMessage;
    }

    /**
     * Format the log entry as a Slack attachment.
     *
     * @param string $message
     * @param int    $type
     * @param int    $timestamp
     * @param mixed  $context
     *
     * @return SlackMessage
     */
    protected function formatAsAttachment(
        $message, $type, $timestamp, $context = null
    ) {
        $slackMessage   = new SlackMessage($this->client);
        $format         = $this->getOption('format', '%message%');
        $lineFormatter  = new LineFormatter($format);
        $title          = $this->getTypeString($type);
        $text           = $lineFormatter->format(
            $message, $type, $timestamp, $context
        );
        $text           = str_replace(PHP_EOL, '', $text);

        $attachment = new SlackAttachment([
            'fallback'  => "$title: $text",
            'title'     => $title,
            'text'      => $text,
            'color'     => $this->getColor($type),
            'timestamp' => (new \DateTime())->setTimestamp($timestamp),
        ]);

        $slackMessage->attach($attachment);

        if ($this->getOption('alertChannel')) {
            $slackMessage->setText('@channel');
        }

        return $slackMessage;
    }

    /**
     * Get the attachment color.
     *
     * @param integer $type
     *
     * @return string
     */
    protected function getColor($type)
    {
        switch ($type) {
            case Logger::EMERGENCY:
            case Logger::EMERGENCE:
            case Logger::CRITICAL:
            case Logger::ALERT:
            case Logger::ERROR:
                return 'danger';
            case Logger::WARNING:
            case Logger::NOTICE:
            case Logger::INFO:
                return 'warning';
            case Logger::DEBUG:
                return '#439FE0';
            case Logger::CUSTOM:
            case Logger::SPECIAL:
            default:
                return 'good';
        }
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

}
