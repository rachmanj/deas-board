<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;

class EmlMetadataParser
{
    /**
     * @return array{
     *     from_name: ?string,
     *     from_email: ?string,
     *     to: ?string,
     *     cc: ?string,
     *     subject: ?string,
     *     received_at: ?Carbon,
     *     message_id: ?string
     * }
     */
    public function parse(string $raw, ?int $fileMtime = null): array
    {
        $headers = $this->parseHeaders($raw);

        $from = $this->parseMailbox($headers['from'][0] ?? null);
        $date = $this->parseDate($headers['date'][0] ?? null, $fileMtime);

        return [
            'from_name' => $from['name'],
            'from_email' => $from['email'],
            'to' => $this->joinMailboxes($headers['to'] ?? []),
            'cc' => $this->joinMailboxes($headers['cc'] ?? []),
            'subject' => $this->decodeHeaderValue($headers['subject'][0] ?? null),
            'received_at' => $date,
            'message_id' => $this->decodeHeaderValue($headers['message-id'][0] ?? null),
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    private function parseHeaders(string $raw): array
    {
        $normalized = str_replace("\r\n", "\n", $raw);
        $parts = preg_split("/\n\n/", $normalized, 2);
        $headerBlock = $parts[0] ?? '';

        $lines = explode("\n", $headerBlock);
        $headers = [];
        $currentName = null;
        $currentValue = null;

        foreach ($lines as $line) {
            if (preg_match('/^([\w-]+):\s*(.*)$/', $line, $match)) {
                if ($currentName !== null) {
                    $headers[$currentName][] = trim($currentValue ?? '');
                }
                $currentName = strtolower($match[1]);
                $currentValue = $match[2];
            } elseif ($currentName !== null && (str_starts_with($line, ' ') || str_starts_with($line, "\t"))) {
                $currentValue .= ' '.trim($line);
            }
        }

        if ($currentName !== null) {
            $headers[$currentName][] = trim($currentValue ?? '');
        }

        return $headers;
    }

    /**
     * @return array{name: ?string, email: ?string}
     */
    private function parseMailbox(?string $value): array
    {
        if ($value === null || trim($value) === '') {
            return ['name' => null, 'email' => null];
        }

        $value = $this->decodeHeaderValue($value) ?? $value;

        if (preg_match('/<([^>]+)>/', $value, $match)) {
            $email = trim($match[1]);
            $name = trim(preg_replace('/<[^>]+>/', '', $value) ?? '');
            $name = trim($name, '"\' ');

            return [
                'name' => $name !== '' ? $name : null,
                'email' => $email !== '' ? $email : null,
            ];
        }

        $email = trim($value);

        return ['name' => null, 'email' => $email !== '' ? $email : null];
    }

    /**
     * @param  list<string>  $values
     */
    private function joinMailboxes(array $values): ?string
    {
        if ($values === []) {
            return null;
        }

        $parts = [];
        foreach ($values as $value) {
            $mailbox = $this->parseMailbox($value);
            if ($mailbox['email']) {
                $parts[] = $mailbox['email'];
            }
        }

        return $parts === [] ? null : implode(', ', $parts);
    }

    private function parseDate(?string $value, ?int $fileMtime): ?Carbon
    {
        if ($value !== null && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (InvalidFormatException) {
                //
            }
        }

        if ($fileMtime !== null) {
            return Carbon::createFromTimestamp($fileMtime);
        }

        return null;
    }

    private function decodeHeaderValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (function_exists('iconv_mime_decode')) {
            $decoded = @iconv_mime_decode($value, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');
            if (is_string($decoded) && $decoded !== '') {
                return $decoded;
            }
        }

        return $value;
    }
}
