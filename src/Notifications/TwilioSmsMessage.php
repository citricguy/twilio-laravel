<?php

namespace Citricguy\TwilioLaravel\Notifications;

class TwilioSmsMessage
{
    /**
     * The message content.
     *
     * @var string
     */
    public $content;

    /**
     * The options for the message.
     *
     * @var array
     */
    public $options = [];

    /**
     * Create a new message instance.
     *
     * @param  string  $content
     * @return void
     */
    public function __construct($content = '')
    {
        $this->content = $content;
    }

    /**
     * Set the message content.
     *
     * @param  string  $content
     * @return $this
     */
    public function content($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Set the from phone number.
     *
     * @param  string  $from
     * @return $this
     */
    public function from($from)
    {
        $this->options['from'] = $from;

        return $this;
    }

    /**
     * Set the messaging service SID.
     *
     * @param  string  $messagingServiceSid
     * @return $this
     */
    public function messagingService($messagingServiceSid)
    {
        $this->options['messagingServiceSid'] = $messagingServiceSid;

        return $this;
    }

    /**
     * Set media URLs to include in the message.
     *
     * @param  array|string  $urls
     * @return $this
     */
    public function mediaUrls($urls)
    {
        $this->options['mediaUrls'] = is_array($urls) ? $urls : [$urls];

        return $this;
    }

    /**
     * Set the status callback URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function statusCallback($url)
    {
        $this->options['statusCallback'] = $url;

        return $this;
    }

    /**
     * Set additional options for the message.
     *
     * @return $this
     */
    public function options(array $options)
    {
        $this->options = array_merge($this->options, $options);

        return $this;
    }

    /**
     * Get the array representation of the message.
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'content' => $this->content,
            'options' => $this->options,
        ];
    }
}
