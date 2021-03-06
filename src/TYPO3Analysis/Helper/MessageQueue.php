<?php
/**
 * @todo adds a description (license text, description of this class / file, etc)
 */
namespace TYPO3Analysis\Helper;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MessageQueue {

    /**
     * Message Queue connection
     *
     * @var \PhpAmqpLib\Connection\AMQPConnection
     */
    protected $handle = null;

    /**
     * Message Queue channel
     *
     * @var \PhpAmqpLib\Channel\AMQPChannel
     */
    protected $channel = null;

    /**
     * Store of declared exchanges and queues
     *
     * @var array
     */
    protected $declared = array(
        'exchange' => array(),
        'queue' => array(),
    );

    /**
     * Constructor to set up a connection to the RabbitMQ server
     *
     * @param string    $host
     * @param integer   $port
     * @param string    $username
     * @param string    $password
     * @param string    $vHost
     * @return void
     */
    public function __construct($host, $port, $username, $password, $vHost) {
        $this->handle = new AMQPConnection($host, $port, $username, $password, $vHost);
        $this->renewChannel();
    }

    /**
     * Creates a new channel with QoS!
     *
     * @return void
     */
    protected function renewChannel() {
        $this->channel = $this->handle->channel();
        $this->channel->basic_qos(0, 1, false);
    }

    /**
     * Gets the AMQPConnection
     *
     * @return null|AMQPConnection
     */
    protected function getHandle() {
        return $this->handle;
    }

    /**
     * Gets the channel for AMQPConnection
     *
     * @return null|\PhpAmqpLib\Channel\AMQPChannel
     */
    protected function getChannel() {
        return $this->channel;
    }

    /**
     * Declares a new exchange at the message queue server
     *
     * @param string    $exchange
     * @param string    $exchangeType
     * @return void
     */
    protected function declareExchange($exchange, $exchangeType) {
        if (isset($this->declared['exchange'][$exchange]) === false) {
            $this->getChannel()->exchange_declare($exchange, $exchangeType, false, true, true);
            $this->declared['exchange'][$exchange] = true;
        }
    }

    /**
     * Declares a new queue at the message queue server
     *
     * @param string    $queue
     * @return void
     */
    protected function declareQueue($queue) {
        $this->getChannel()->queue_declare($queue, false, true, false, false);
        $this->declared['queue'][$queue] = true;
    }

    /**
     * Sends a new message to message queue server
     *
     * @param mixed     $message
     * @param string    $exchange
     * @param string    $queue
     * @param string    $routing
     * @param string    $exchangeType
     */
    public function sendMessage($message, $exchange = '', $queue = '', $routing = '', $exchangeType = 'topic') {
        if (is_array($message) === true) {
            $message = json_encode($message);
        }

        if ($exchange) {
            $this->declareExchange($exchange, $exchangeType);
        }

        if ($queue) {
            $this->declareQueue($queue);
        }

        $message = new AMQPMessage($message, array('content_type' => 'text/plain'));
        $this->getChannel()->basic_publish($message, $exchange, $routing);
    }

    /**
     * Consumer registration.
     * Registered a new consumer at message queue server to consume messages
     *
     * @param string    $exchange
     * @param string    $queue
     * @param string    $routing
     * @param string    $consumerTag
     * @param array     $callback
     * @param string    $exchangeType
     * @return void
     */
    public function basicConsume($exchange, $queue, $routing, $consumerTag, array $callback, $exchangeType = 'topic') {
        $this->declareQueue($queue);

        if ($exchange) {
            $this->declareExchange($exchange, $exchangeType);
            $this->getChannel()->queue_bind($queue, $exchange, $routing);
        }
        $this->getChannel()->basic_consume($queue, $consumerTag, false, false, false, false, $callback);

        // Loop as long as the channel has callbacks registered
        while (count($this->getChannel()->callbacks)) {
            $this->getChannel()->wait();
        }
    }

    /**
     * Closes the message queue connection
     *
     * @return void
     */
    public function close() {
        #$this->getChannel()->close();
        #$this->getHandle()->close();
    }
}