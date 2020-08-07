<?php

namespace Bow\Mail\Driver;

use Bow\Mail\Contracts\MailDriverInterface;
use Bow\Mail\Exception\SmtpException;
use Bow\Mail\Exception\SocketException;
use Bow\Mail\Message;
use ErrorException;

class SmtpDriver implements MailDriverInterface
{

    /**
     * Socket connection
     *
     * @var resource
     */
    private $sock;

    /**
     * The username
     *
     * @var string
     */
    private $username;

    /**
     * The password
     *
     * @var string
     */
    private $password;

    /**
     * The SMPT server
     *
     * @var string
     */
    private $url;

    /**
     * Define the security
     *
     * @var bool
     */
    private $secure;

    /**
     * Enable TLS
     *
     * @var bool
     */
    private $tls = false;

    /**
     * Connexion time out
     *
     * @var int
     */
    private $timeout;

    /**
     * The SMTP server
     *
     * @var int
     */
    private $port = 25;

    /**
     * Smtp Constructor
     *
     * @param array $config
     */
    public function __construct(array $config)
    {
        if (!isset($config['secure'])) {
            $config['secure'] = false;

            if (!isset($config['tls'])) {
                $config['tls'] = false;
            }
        }

        $this->url = $config['hostname'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->secure = $config['ssl'];
        $this->tls = $config['tls'];
        $this->timeout = $config['timeout'];
        $this->port = $config['port'];
    }

    /**
     * Private setting of the magic functions
     */
    private function __clone()
    {
    }

    /**
     * Start sending mail
     *
     * @param  Message $message
     * @return bool
     * @throws SocketException
     * @throws ErrorException
     */
    public function send(Message $message)
    {
        $this->connection();

        $error = true;

        // SMTP command
        if ($this->username !== null) {
            $this->write('MAIL FROM: <' . $this->username . '>', 250);
        } else {
            if ($message->getFrom() !== null) {
                $this->write('MAIL FROM: <' . $message->getFrom() . '>', 250);
            }
        }

        foreach ($message->getTo() as $value) {
            if ($value[0] !== null) {
                $to = $value[0] . '<' . $value[1] . '>';
            } else {
                $to = '<' . $value[1] . '>';
            }

            $this->write('RCPT TO: ' . $to, 250);
        }

        $this->write('DATA', 354);
        $data = 'Subject: ' . $message->getSubject() . Message::END;
        $data .= $message->compileHeaders();
        $data .= 'Content-Type: ' . $message->getType() . '; charset=' . $message->getCharset() . Message::END;
        $data .= 'Content-Transfer-Encoding: 8bit' . Message::END;
        $data .= Message::END . $message->getMessage() . Message::END;
        $this->write($data);

        try {
            $this->write('.', 250);
        } catch (SmtpException $e) {
            echo $e->getMessage();
        }

        $status = $this->disconnect();

        if ($status == 221) {
            $error = false;
        }

        return (bool) $error;
    }


    /**
     * Connect to an SMTP server
     *
     * @throws ErrorException
     * @throws SocketException | SmtpException
     */
    private function connection()
    {
        $url = $this->url;

        if ($this->secure === true) {
            $url = 'ssl://' . $this->url;
        }

        $this->sock = fsockopen($url, $this->port, $errno, $errstr, $this->timeout);

        if ($this->sock == null) {
            throw new SocketException('Impossible to get connected to ' . $this->url . ':' . $this->port, E_USER_ERROR);
        }

        stream_set_timeout($this->sock, $this->timeout, 0);
        $code = $this->read();

        $host = isset($_SERVER['HTTP_HOST']) &&
        preg_match('/^[\w.-]+\z/', $_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';

        if ($code == 220) {
            $code = $this->write('EHLO ' . $host, 250, 'HELO');
            if ($code != 250) {
                $this->write('EHLO ' . $host, 250, 'HELO');
            }
        }

        if ($this->tls === true) {
            $this->write('STARTTLS', 220);

            $secured = @stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);

            if (!$secured) {
                throw new ErrorException('Can not secure your connection with tls', E_ERROR);
            }
        }

        if ($this->username !== null && $this->password !== null) {
            $this->write('AUTH LOGIN', 334);
            $this->write(base64_encode($this->username), 334, 'username');
            $this->write(base64_encode($this->password), 235, 'password');
        }
    }

    /**
     * Disconnection
     *
     * @return mixed
     * @throws ErrorException
     */
    private function disconnect()
    {
        $r = $this->write('QUIT');

        fclose($this->sock);

        $this->sock = null;

        return $r;
    }

    /**
     * Read the current connection stream.
     *
     * @return string
     */
    private function read()
    {
        $s = null;

        for (; !feof($this->sock);) {
            if (($line = fgets($this->sock, 1e3)) == null) {
                continue;
            }

            $s = explode(' ', $line)[0];

            if (preg_match('#^[0-9]+$#', $s)) {
                break;
            }
        }

        return (int) $s;
    }

    /**
     * Start an SMTP command
     *
     * @param string $command
     * @param int    $code
     * @param null   $message
     *
     * @throws SmtpException
     * @return string
     */
    private function write($command, $code = null, $message = null)
    {
        if ($message == null) {
            $message = $command;
        }

        $command = $command . Message::END;

        fwrite($this->sock, $command, strlen($command));

        $response = null;


        if ($code !== null) {
            $response = $this->read();

            if (!in_array($response, (array) $code)) {
                $message = isset($message) ? $message : '';

                throw new SmtpException(
                    sprintf('SMTP server did not accept %s with code [%s]', $message, $response),
                    E_ERROR
                );
            }
        }

        return $response;
    }
}
