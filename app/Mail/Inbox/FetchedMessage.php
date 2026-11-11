<?php

namespace App\Mail\Inbox;

use Carbon\CarbonImmutable;
use Webklex\PHPIMAP\Message;

class FetchedMessage
{
    public function __construct(
        public readonly string $messageId,
        public readonly string $from,
        public readonly string $subject,
        public readonly CarbonImmutable $receivedAt,
        public readonly ?string $html,
        public readonly ?string $text,
    ) {}

    public static function fromEml(string $raw): self
    {
        return self::fromWebklex(Message::fromString($raw));
    }

    public static function fromWebklex(Message $message): self
    {
        $from = $message->getFrom()->first();
        $date = $message->getDate()->first();
        $subject = trim((string) $message->getSubject()->first());
        $messageId = trim((string) $message->getMessageId()->first());
        $html = $message->hasHTMLBody() ? $message->getHTMLBody() : null;
        $text = $message->hasTextBody() ? $message->getTextBody() : null;

        return new self(
            messageId: $messageId !== '' ? $messageId : 'sha1-'.sha1(serialize([$from?->mail, (string) $date, $subject, $html, $text])),
            from: strtolower((string) $from?->mail),
            subject: $subject,
            receivedAt: $date ? CarbonImmutable::instance($date->toDate()) : CarbonImmutable::now(),
            html: $html,
            text: $text,
        );
    }
}
