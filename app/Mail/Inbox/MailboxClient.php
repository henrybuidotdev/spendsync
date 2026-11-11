<?php

namespace App\Mail\Inbox;

use Carbon\CarbonInterface;

interface MailboxClient
{
    /**
     * @throws InvalidCredentialsException
     * @throws MailboxConnectionException
     */
    public function connect(string $email, #[\SensitiveParameter] string $appPassword): void;

    /**
     * Only messages from the given senders, filtered server-side (IMAP SEARCH FROM ... SINCE ...).
     *
     * @param array<int, string> $senders
     * @return iterable<FetchedMessage>
     */
    public function fetchFrom(array $senders, CarbonInterface $since): iterable;

    public function disconnect(): void;
}
