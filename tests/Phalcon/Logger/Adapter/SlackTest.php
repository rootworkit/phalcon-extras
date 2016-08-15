<?php
/**
 * SlackTest
 *
 * @package     Rootwork\Test\Phalcon\Logger\Adapter
 * @copyright   Copyright (c) 2016 Rootwork InfoTech LLC (www.rootwork.it)
 * @license     BSD-3-clause
 * @author      Mike Soule <mike@rootwork.it>
 * @filesource
 */

namespace Rootwork\Test\Phalcon\Logger\Adapter;

use PHPUnit_Framework_TestCase as TestCase;
use Rootwork\Phalcon\Logger\Adapter\Slack;
use Maknz\Slack\Client as SlackClient;
use Maknz\Slack\Message as SlackMessage;
use Maknz\Slack\Attachment as SlackAttachment;
use PHPUnit_Framework_MockObject_MockObject as MockObject;

/**
 * SlackTest
 *
 * @package     Rootwork\Test\Phalcon\Logger\Adapter
 */
class SlackTest extends TestCase
{

    /**
     * Slack logger instance.
     *
     * @var Slack
     */
    protected $sut;

    /**
     * @var SlackClient|MockObject
     */
    protected $client;

    /**
     * Last logged Slack message.
     *
     * @var SlackMessage
     */
    public $lastMessage;

    /**
     * Set up the test.
     */
    public function setUp()
    {
        $url        = 'https://hooks.slack.com/services/foo/bar/baz';
        $options    = [
            'formatterOptions' => [
                'alertChannel'      => true,
                'useAttachments'    => true,
            ],
            'clientSettings' => [
                'username'      => 'Mend Production',
                'link_names'    => true,
                'markdown_in_attachments' => ['text'],
            ],
        ];

        $this->client = $this->getMockBuilder(SlackClient::class)
            ->setMethods(['sendMessage'])
            ->setConstructorArgs([
                $url,
                $options['clientSettings'],
            ])
            ->getMock();

        $this->client->expects($this->once())->method('sendMessage')
            ->willReturnCallback(function ($slackMessage) {
                $this->lastMessage = $slackMessage;
            });

        $this->sut = $this->getMockBuilder(Slack::class)
            ->setMethods(['getClient'])
            ->setConstructorArgs([$url, $options])
            ->getMock();

        $this->sut->expects($this->any())->method('getClient')
            ->willReturn($this->client);
    }

    /**
     * Test logging to Slack.
     */
    public function testLog()
    {
        $entry = 'Test entry';
        $this->sut->error($entry);
        $actual = $this->lastMessage;

        $this->assertInstanceOf(SlackMessage::class, $actual);

        /** @var SlackAttachment $attachment */
        $attachments    = $actual->getAttachments();
        $attachment     = array_pop($attachments);

        $this->assertInstanceOf(SlackAttachment::class, $attachment);

        $dateTime = $attachment->getTimestamp();

        $this->assertEquals("ERROR: $entry", $attachment->getFallback());
        $this->assertEquals('ERROR', $attachment->getTitle());
        $this->assertEquals($entry, $attachment->getText());
        $this->assertEquals('danger', $attachment->getColor());
        $this->assertInstanceOf(\DateTime::class, $dateTime);
        $this->assertLessThan(strtotime('+10 seconds'), $dateTime->getTimestamp());
        $this->assertGreaterThan(strtotime('-10 seconds'), $dateTime->getTimestamp());
        $this->assertEquals('@channel', $actual->getText());
    }
}
