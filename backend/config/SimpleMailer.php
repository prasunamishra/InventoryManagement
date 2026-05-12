<?php
/**
 * SimpleMailer – Minimal SMTP mailer (no vendor/composer needed).
 * Works with Gmail on port 465 (SSL).
 */
class SimpleMailer {

    private string $host;
    private int    $port;
    private string $username;
    private string $password;

    /** @var resource|false */
    private $socket;

    public function __construct(string $host, int $port, string $username, string $password) {
        $this->host     = $host;
        $this->port     = $port;
        $this->username = $username;
        $this->password = $password;
    }

    // ─── Private helpers ─────────────────────────────────────

    /** Open an SSL socket and log in via AUTH LOGIN */
    private function connect(): void {
        $this->socket = fsockopen("ssl://{$this->host}", $this->port, $errno, $errstr, 30);
        if (!$this->socket) {
            throw new RuntimeException("SMTP connect failed: $errstr ($errno)");
        }
        $this->read();                                        // 220 welcome banner
        $this->cmd("EHLO " . gethostname());                  // EHLO handshake
        $this->cmd("AUTH LOGIN");                             // start auth
        $this->cmd(base64_encode($this->username));           // send username
        $this->cmd(base64_encode($this->password));           // send password (235 = success)
    }

    /** Write a line to the SMTP socket and return the server response */
    private function cmd(string $line): string {
        fwrite($this->socket, $line . "\r\n");
        return $this->read();
    }

    /** Read a full (possibly multi-line) SMTP response */
    private function read(): string {
        $out = '';
        while ($line = fgets($this->socket, 512)) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last response line
        }
        return $out;
    }

    // ─── Public API ──────────────────────────────────────────

    /**
     * Send an HTML email.
     *
     * @param string $from      Sender email address
     * @param string $fromName  Sender display name
     * @param string $to        Recipient email address
     * @param string $subject   Email subject
     * @param string $htmlBody  HTML body content
     *
     * @throws RuntimeException if the connection or sending fails
     */
    public function send(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $htmlBody
    ): void {
        $this->connect();

        $this->cmd("MAIL FROM:<{$from}>");
        $this->cmd("RCPT TO:<{$to}>");
        $this->cmd("DATA");

        // Build RFC 2822 message
        $msg  = "From: =?UTF-8?B?" . base64_encode($fromName) . "?= <{$from}>\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "\r\n";
        $msg .= $htmlBody . "\r\n";
        $msg .= ".";   // DATA terminator

        $this->cmd($msg);
        $this->cmd("QUIT");
        fclose($this->socket);
    }
}
