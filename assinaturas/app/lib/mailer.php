<?php
declare(strict_types=1);

/** Moldura simples e compatível para os e-mails enviados pela plataforma. */
function mail_layout(string $heading, string $bodyHtml, string $buttonLabel, string $buttonUrl, string $footnote): string
{
    $app = e((string) config('app_name', 'KIWEE Assinaturas'));
    $url = e($buttonUrl);
    return '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><title>' . e($heading) . '</title></head>'
        . '<body style="margin:0;padding:24px;background:#f5f7f4;font-family:Arial,Helvetica,sans-serif;color:#1c2420;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr><td align="center">'
        . '<table role="presentation" width="480" cellpadding="0" cellspacing="0" border="0" style="max-width:480px;background:#ffffff;border:1px solid #e3e8e4;">'
        . '<tr><td style="padding:24px 28px 8px 28px;font-size:14px;font-weight:bold;color:#3d7f21;">' . $app . '</td></tr>'
        . '<tr><td style="padding:0 28px;font-size:22px;font-weight:bold;line-height:28px;">' . e($heading) . '</td></tr>'
        . '<tr><td style="padding:8px 28px 0 28px;font-size:15px;line-height:22px;">' . $bodyHtml . '</td></tr>'
        . '<tr><td style="padding:16px 28px 8px 28px;"><a href="' . $url . '" style="display:inline-block;background:#3d7f21;color:#ffffff;'
        . 'font-size:15px;font-weight:bold;text-decoration:none;padding:12px 20px;border-radius:6px;">' . e($buttonLabel) . '</a></td></tr>'
        . '<tr><td style="padding:8px 28px 0 28px;font-size:12px;line-height:18px;color:#5d6b63;">Se o botão não funcionar, copie este endereço:<br>'
        . '<a href="' . $url . '" style="color:#3d7f21;word-break:break-all;">' . $url . '</a></td></tr>'
        . '<tr><td style="padding:16px 28px 24px 28px;font-size:12px;line-height:18px;color:#5d6b63;">' . e($footnote) . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** Envia um e-mail com versão em texto e em HTML. Devolve false quando o envio falha. */
function send_mail(string $to, string $subject, string $html, string $text): bool
{
    $driver = (string) config('mail.driver', 'mail');
    $fromEmail = (string) config('mail.from_email', 'no-reply@localhost');
    $fromName = (string) config('mail.from_name', config('app_name', 'KIWEE Assinaturas'));

    $boundary = 'kw_' . random_token(12);
    $headers = [
        'From' => mail_encode_header($fromName) . ' <' . $fromEmail . '>',
        'Reply-To' => $fromEmail,
        'MIME-Version' => '1.0',
        'Content-Type' => 'multipart/alternative; boundary="' . $boundary . '"',
        'Date' => date('r'),
        'Message-ID' => '<' . random_token(12) . '@' . (parse_url(app_url(), PHP_URL_HOST) ?: 'localhost') . '>',
    ];
    $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($text))
        . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . "--$boundary--\r\n";

    try {
        if ($driver === 'log') {
            $entry = json_encode(['at' => now_utc(), 'to' => $to, 'subject' => $subject, 'text' => $text], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return file_put_contents(KW_ROOT . '/storage/mail.log', $entry . "\n", FILE_APPEND | LOCK_EX) !== false;
        }
        if ($driver === 'smtp') {
            return smtp_send($fromEmail, $to, $subject, $headers, $body);
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        return @mail($to, mail_encode_header($subject), $body, implode("\r\n", $lines), '-f' . $fromEmail);
    } catch (Throwable $e) {
        error_log('KIWEE Assinaturas: falha ao enviar e-mail: ' . $e->getMessage());
        return false;
    }
}

function mail_encode_header(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    return preg_match('/[^\x20-\x7e]/', $value) ? '=?UTF-8?B?' . base64_encode($value) . '?=' : $value;
}

/** Cliente SMTP mínimo: SSL direto, STARTTLS ou sem criptografia, com AUTH LOGIN. */
function smtp_send(string $from, string $to, string $subject, array $headers, string $body): bool
{
    $host = (string) config('mail.host', 'localhost');
    $port = (int) config('mail.port', 465);
    $encryption = (string) config('mail.encryption', 'ssl');
    $user = (string) config('mail.user', '');
    $pass = (string) config('mail.pass', '');

    $socket = @stream_socket_client(($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $error, 15);
    if (!$socket) {
        throw new RuntimeException("conexão SMTP recusada: $error");
    }
    stream_set_timeout($socket, 20);

    $read = static function () use ($socket): string {
        $response = '';
        while (($line = fgets($socket, 600)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $response;
    };
    $command = static function (?string $line, array $expect) use ($socket, $read): string {
        if ($line !== null) {
            fwrite($socket, $line . "\r\n");
        }
        $response = $read();
        if (!in_array((int) substr($response, 0, 3), $expect, true)) {
            throw new RuntimeException('resposta SMTP inesperada: ' . trim($response));
        }
        return $response;
    };

    try {
        $name = parse_url(app_url(), PHP_URL_HOST) ?: 'localhost';
        $command(null, [220]);
        $command('EHLO ' . $name, [250]);
        if ($encryption === 'tls') {
            $command('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('não foi possível iniciar TLS');
            }
            $command('EHLO ' . $name, [250]);
        }
        if ($user !== '') {
            $command('AUTH LOGIN', [334]);
            $command(base64_encode($user), [334]);
            $command(base64_encode($pass), [235]);
        }
        $command('MAIL FROM:<' . $from . '>', [250]);
        $command('RCPT TO:<' . $to . '>', [250, 251]);
        $command('DATA', [354]);

        $message = 'To: <' . $to . ">\r\nSubject: " . mail_encode_header($subject) . "\r\n";
        foreach ($headers as $key => $value) {
            $message .= $key . ': ' . $value . "\r\n";
        }
        $message .= "\r\n" . $body;
        // Linhas que começam com ponto precisam ser duplicadas no protocolo SMTP.
        $message = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $message . "\r\n");
        $command('.', [250]);
        $command('QUIT', [221, 250]);
        return true;
    } finally {
        fclose($socket);
    }
}
