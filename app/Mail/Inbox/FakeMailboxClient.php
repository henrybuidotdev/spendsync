<?php

namespace App\Mail\Inbox;

use Carbon\CarbonInterface;

/** Test double: serves .eml files from a folder, applying the same sender/since/before filter as the IMAP search. */
class FakeMailboxClient implements MailboxClient
{
    /** @var array<int, array{senders: array<int, string>, since: CarbonInterface, before: ?CarbonInterface}> */
    public array $searches = [];

    public ?string $connectedAs = null;

    /** @param array<int, FetchedMessage> $messages */
    public function __construct(
        public array $messages = [],
        public bool $failAuth = false,
        public bool $failConnection = false,
    ) {}

    public static function fromDirectory(string $directory): self
    {
        $files = glob(rtrim($directory, '/').'/{,*/}*.eml', GLOB_BRACE);

        return new self(array_map(fn (string $file) => FetchedMessage::fromEml(file_get_contents($file)), $files));
    }

    public function connect(string $email, #[\SensitiveParameter] string $appPassword): void
    {
        if ($this->failConnection) {
            throw new MailboxConnectionException('imap_connection_failed');
        }

        if ($this->failAuth) {
            throw new InvalidCredentialsException('invalid_credentials');
        }

        $this->connectedAs = $email;
    }

    public function fetchFrom(array $senders, CarbonInterface $since, ?CarbonInterface $before = null): iterable
    {
        $this->searches[] = ['senders' => $senders, 'since' => $since, 'before' => $before];

        foreach ($this->messages as $message) {
            if (! in_array($message->from, $senders, true)) {
                continue;
            }

            if ($message->receivedAt->lt($since->copy()->startOfDay())) {
                continue;
            }

            if ($before !== null && $message->receivedAt->gte($before->copy()->startOfDay())) {
                continue;
            }

            yield $message;
        }
    }

    public function disconnect(): void
    {
        $this->connectedAs = null;
    }
}
