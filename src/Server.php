<?php

namespace Sofi\Server;

class BaseServer
{

    protected $host = null;
    protected $port = null;
    protected $socket = null;

    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = (int) $port;

        // create a socket
        $this->createSocket();

        // bind the socket
        $this->bind();
    }

    protected function createSocket()
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, 0);
    }

    protected function bind()
    {
        if (!socket_bind($this->socket, $this->host, $this->port)) {
            throw new Exception('Could not bind: ' . $this->host . ':' . $this->port . ' - ' . socket_strerror(socket_last_error()));
        }
        //разрешаем использовать один порт для нескольких соединений
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);
    }

    public function listen($callback)
    {
        // check if the callback is valid. Throw an exception
        // if not.
        if (!is_callable($callback)) {
            throw new \Exception('The given argument should be callable.');
        }

        // Now here comes the thing that makes this process
        // long, infinite, never ending..
        while (1) {
            // listen for connections
            socket_listen($this->socket);

            // try to get the client socket resource
            // if false we got an error close the connection and skip
            if (!$client = socket_accept($this->socket)) {
                socket_close($client);
                continue;
            }

            // create new request instance with the clients header.
            $request = \Sofi\HTTP\message\Request::createFromString(socket_read($client, 4096));

            // execute the callback 
            $response = call_user_func($callback, $request);

            // check if we really recived an Response object
            // if not return a 404 response object
            if (!$response || !$response instanceof \Psr\Http\Message\ResponseInterface) {
                $stream = fopen('php://temp', 'w+');
                $page404 = new \Sofi\HTTP\Body($stream);
                $page404->write('Page not found');
                
                $headers = [];
                $headers['Date'] = gmdate('D, d M Y H:i:s T');
                $headers['Content-Type'] = 'text/html; charset=utf-8';
                $headers['Server'] = 'Sofi App Server/1.0.0';
                $headers = new \Sofi\HTTP\Headers($headers);

                $response = new \Sofi\HTTP\message\Response(404, $headers, $page404);
            }

            // make a string out of our response
            $response = (string) $response;

            // write the response to the client socket
            socket_write($client, $response, strlen($response));

            // close the connetion so we can accept new ones
            socket_close($client);
        }
    }

}
