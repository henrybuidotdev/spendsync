<?php

namespace App\Mail\Inbox;

use Carbon\CarbonInterface;
use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;
use Webklex\PHPIMAP\Exceptions\ConnectionFailedException;
use Webklex\PHPIMAP\Folder;

class WebklexGmailClient implements MailboxClient
{
    private const ALL_MAIL_FOLDERS = ['[Gmail]/All Mail', '[Google Mail]/All Mail', '[Gmail]/Tất cả thư'];

    private ?Client $client = null;

    public function connect(string $email, #[\SensitiveParameter] string $appPassword): void
    {
        $this->client = (new ClientManager)->make([
            'host' => 'imap.gmail.com',
            'port' => 993,
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => $email,
            'password' => $appPassword,
            'protocol' => 'imap',
            'authentication' => null,
            'timeout' => 30,
        ]);

        try {
            $this->client->connect();
        } catch (ConnectionFailedException) {
            // Never pass the original exception on: its trace holds the password argument.
            throw new MailboxConnectionException('imap_connection_failed');
        } catch (Throwable) {
            throw new InvalidCredentialsException('invalid_credentials');
        }
    }

    public function fetchFrom(array $senders, CarbonInterface $since): iterable
    {
        $folder = $this->allMailFolder();

        foreach ($senders as $sender) {
            $messages = $folder->query()
                ->whereFrom($sender)
                ->whereSince($since->toDateTime())
                ->leaveUnread()
                ->setFetchBody(true)
                ->get();

            foreach ($messages as $message) {
                yield FetchedMessage::fromWebklex($message);
            }
        }
    }

    public function disconnect(): void
    {
        $this->client?->disconnect();
        $this->client = null;
    }

    private function allMailFolder(): Folder
    {
        foreach (self::ALL_MAIL_FOLDERS as $path) {
            $folder = $this->client->getFolderByPath($path, false, true);

            if ($folder !== null) {
                return $folder;
            }
        }

        return $this->client->getFolderByPath('INBOX');
    }
}
