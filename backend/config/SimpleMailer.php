<?php
/**
 * SimpleMailer – SMTP use garera email pathaune simple class
 * Gmail jasto mail server sanga kaam garxa (port 465 SSL)
 */
class SimpleMailer {

    private string $host;      // SMTP server host (ex: smtp.gmail.com)
    private int    $port;      // port number (ex: 465)
    private string $username;  // email username
    private string $password;  // email password

    /** @var resource|false */
    private $socket; // SMTP connection socket

    // constructor → object create huda values set garne
    public function __construct(string $host, int $port, string $username, string $password) {
        $this->host     = $host;
        $this->port     = $port;
        $this->username = $username;
        $this->password = $password;
    }

    // CONNECT 
    // SMTP server sanga connect + login garne
    private function connect(): void {

        // SSL socket open garne
        $this->socket = fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, 30);

        if (!$this->socket) {
            // connect fail bhayo vane error throw
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }

        $this->read(); // server ko welcome message (220)

        $this->cmd("EHLO " . gethostname()); // handshake
        $this->cmd("AUTH LOGIN"); // login start

        // username ra password base64 encode garera send
        $this->cmd(base64_encode($this->username));
        $this->cmd(base64_encode($this->password));
    }

    //  COMMAND SEND 
    // SMTP server ma command pathaune
    private function cmd(string $line): string {
        fwrite($this->socket, $line . "\r\n"); // command send
        return $this->read(); // response lina
    }

    //  READ RESPONSE 
    // server bata response read garne
    private function read(): string {
        $out = '';

        while ($line = fgets($this->socket, 512)) {
            $out .= $line;

            // last line detect (SMTP format)
            if (isset($line[3]) && $line[3] === ' ') break;
        }

        return $out;
    }

    // SEND EMAIL 
    // main function → email pathaune
    public function send(
        string $from,       // sender email
        string $fromName,   // sender name
        string $to,         // receiver email
        string $subject,    // subject
        string $htmlBody    // HTML content
    ): void {

        // server sanga connect garne
        $this->connect();

        // mail commands
        $this->cmd("MAIL FROM:<{$from}>");
        $this->cmd("RCPT TO:<{$to}>");
        $this->cmd("DATA");

        //  EMAIL BUILD 
        // proper email format banaune
        $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "\r\n"; // header ra body separate
        $msg .= $htmlBody . "\r\n";

        $msg .= "."; // SMTP ma message end signal

        // message send garne
        $this->cmd($msg);

        // connection close garne
        $this->cmd("QUIT");
        fclose($this->socket);
    }
}