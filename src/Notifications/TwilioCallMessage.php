<?php

namespace Citricguy\TwilioLaravel\Notifications;

class TwilioCallMessage
{
    /**
     * The URL to the TwiML document.
     *
     * @var string
     */
    public $url;

    /**
     * The options for the call.
     *
     * @var array
     */
    public $options = [];

    /**
     * Create a new message instance.
     *
     * @param  string  $url
     * @return void
     */
    public function __construct($url = '')
    {
        $this->url = $url;
    }

    /**
     * Set the TwiML URL.
     *
     * @param  string  $url
     * @return $this
     */
    public function url($url)
    {
        $this->url = $url;

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
     * Set the status callback events.
     *
     * @param  array  $events
     * @return $this
     */
    public function statusCallbackEvent(array $events)
    {
        $this->options['statusCallbackEvent'] = $events;

        return $this;
    }

    /**
     * Set whether to record the call.
     *
     * @param  bool  $record
     * @return $this
     */
    public function record($record = true)
    {
        $this->options['record'] = $record;

        return $this;
    }

    /**
     * Set timeout for the call (in seconds).
     *
     * @param  int  $timeout
     * @return $this
     */
    public function timeout($timeout)
    {
        $this->options['timeout'] = $timeout;

        return $this;
    }

    /**
     * Set additional options for the call.
     *
     * @param  array  $options
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
            'url' => $this->url,
            'options' => $this->options,
        ];
    }
}