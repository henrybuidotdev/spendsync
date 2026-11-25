<?php

namespace App\Mail\Inbox;

use RuntimeException;

/** Network/TLS/server problem — not the user's fault, retry later. */
class MailboxConnectionException extends RuntimeException {}
