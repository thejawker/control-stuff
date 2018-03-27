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
     * Broadcasts the message through the socket
     * and calls the callback when listening.
     *
     * @param string $message
     * @param callable $callback
     */
    public function broadcast(string $message, callable $callback)
    {
        $this->prepareBroadCast();
        
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
    private function startTimer()
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
    public function closeSocket()
    {
        if ($this->socket) {
            socket_close($this->socket);
            $this->socket = null;
        }
    }

    private function prepareBroadCast()
    {
        $this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);

        socket_bind($this->socket, self::HOST_ADDRESS, self::DISCOVERY_PORT);
        socket_set_option($this->socket, SOL_SOCKET, SO_BROADCAST, 1);
        $this->setSocketTimeout();

        $this->startTimer();
    }

    public function openStream(string $ip, int $port): bool
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        $this->setSocketTimeout();
        dump('Yes');

        return socket_connect($this->socket, $ip, $port);
    }

    private function setSocketTimeout(): void
    {
        socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, ["sec" => 1, "usec" => 0]);
    }

    public function sendMessage($bytes)
    {
        $packedBytes = pack("C*", ...$bytes);
        return socket_send($this->socket, $packedBytes, count($bytes), 0);
    }

    public function readMessage(int $expected)
    {
        $remaining = $expected;
        $rx = "";
        $this->startTimer();

        while ($remaining > 0) {
            if ($this->shouldBreak()) {
                break;
            }

            socket_set_nonblock($this->socket);
            socket_recv($this->socket, $chunk, $remaining, null);

            if ($chunk) {
                $this->startTimer();
            }
            $remaining -= strlen($chunk);
            $rx .= $chunk;
        }

        return array_values(unpack("C*", $rx));
    }
}