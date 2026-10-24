<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Ebay;

/**
 * eBay rejected a request because of the data we sent or the seller's account state
 * (missing business policy, category needs item aspects, invalid SKU, ...), as opposed to
 * a transport or server fault. The message is eBay's own and is safe to show to the seller.
 *
 * Extends RuntimeException so existing `catch (\RuntimeException)` callers keep working.
 */
class EbayUserException extends \RuntimeException
{
    /**
     * @param  array<int, array<string, mixed>>  $errors  eBay's `errors[]` array, unmodified.
     */
    public function __construct(string $message, private readonly int $status, private readonly array $errors = [])
    {
        parent::__construct($message, $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
