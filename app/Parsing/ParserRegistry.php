<?php

namespace App\Parsing;

class ParserRegistry
{
    /** @param array<int, BankEmailParser> $parsers */
    public function __construct(private readonly array $parsers) {}

    public static function fromConfig(): self
    {
        return new self(array_map(
            fn (array $bank) => app($bank['parser']),
            array_values(config('banks')),
        ));
    }

    /** @return array<int, BankEmailParser> */
    public function all(): array
    {
        return $this->parsers;
    }

    /** @return array<int, string> */
    public function senders(): array
    {
        return array_values(array_unique(array_merge(...array_map(
            fn (BankEmailParser $parser) => $parser->senders(),
            $this->parsers,
        ))));
    }

    public function findFor(string $from, string $subject): ?BankEmailParser
    {
        $from = self::address($from);

        foreach ($this->parsers as $parser) {
            if ($parser->supports($from, $subject)) {
                return $parser;
            }
        }

        return null;
    }

    /** "Vietcombank <VCB@x.vn>" -> "vcb@x.vn" */
    public static function address(string $from): string
    {
        if (preg_match('/<([^>]+)>/', $from, $match)) {
            $from = $match[1];
        }

        return strtolower(trim($from));
    }
}
