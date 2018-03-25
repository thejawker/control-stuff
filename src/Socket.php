<?php

namespace TheJawker\ControlStuff;

class Socket
{
    /**
     * The IP address of localhost.
     *
     * @var string
     */
    const HOST_ADDRESS = '0.0.0.0';

    /**
     * The IP address used for broadcasting.
     *
     * @var string
     */
    const BROADCAST_ADDRESS = '255.255.255.255';

    /**
     * The Port used to check for bulbs.
     *
     * @var int
     */
    const DISCOVERY_PORT = 48899;

    /**
     * The socket resource.
     *
     * @var resource
     */
    private $socket = null;

    /**
     * The timeout in seconds.
     *
     * @var int
     */
    public $timeout = 2;

    /**
     * The time the process started.
     *
     * @var
     */
    private $timeStarted;

    /**
     * Initializes the Socket.
     */
    public function __construct()
    {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_bind($this->socket, self::HOST_ADDRESS, self::DISCOVERY_PORT);
        socket_set_option($this->socket, SOL_SOCKET, SO_BROADCAST, 1);
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 1, "usec" => 0]);
    }

    /**
     * Broadcasts the message through the socket
     * and calls the callback when listening.
     *
     * @param string $message
     * @param callable $callback
     */
    public function broadcast(string $message, callable $callback)
    {
        $this->startTimer();
        
        while (true) {
            if ($this->shouldBreak()) {
                break;
            }

            $this->broadcastMessage($message);

            while (true) {
                if ($this->shouldBreak()) {
                    break 2;
                }

                socket_recvfrom($this->socket, $data, 64, null, $name, $port);

                if ($data === $message) {
                    continue;
                }

                $callback($data, $name, $port);
            }
        }

        $this->closeSocket();
    }

    /**
     * Starts the timer.
     */
    public function startTimer()
    {
        $this->timeStarted = time();
    }

    /**
     * Checks if the loop should timeout.
     *
     * @return bool
     */
    private function shouldBreak():bool
    {
        return time() > $this->timeStarted + $this->timeout;
    }

    /**
     * Broadcasts the message.
     *
     * @param $message
     * @return int
     */
    private function broadcastMessage(string $message): int
    {
        return socket_sendto(
            $this->socket,
            $message,
            strlen($message),
            null,
            self::BROADCAST_ADDRESS,
            self::DISCOVERY_PORT
        );
    }

    /**
     * Closes the socket.
     */
    private function closeSocket()
    {
        socket_close($this->socket);
    }
}