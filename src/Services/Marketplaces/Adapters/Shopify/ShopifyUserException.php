<?php

namespace NextDeveloper\Marketplace\Services\Marketplaces\Adapters\Shopify;

use NextDeveloper\Commons\Exceptions\AbstractCommonsException;

/**
 * Raised when a Shopify mutation reports userErrors.
 *
 * Shopify returns these inside an HTTP 200 response, so they are ordinary
 * failures rather than transport faults. The raw error list is kept so callers
 * can branch on a specific code — most importantly CHANGE_FROM_QUANTITY_STALE,
 * which means the inventory compare-and-swap lost a race and must be re-read
 * rather than retried.
 */
class ShopifyUserException extends AbstractCommonsException
{
    protected $defaultMessage = 'The marketplace rejected this change.';

    /**
     * @var array<int, array<string, mixed>>
     */
    protected array $userErrors;

    /**
     * @param  array<int, array<string, mixed>>  $userErrors
     */
    public function __construct(string $message, array $userErrors = [], int $code = 0, ?\Exception $previous = null)
    {
        $this->userErrors = $userErrors;

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUserErrors(): array
    {
        return $this->userErrors;
    }

    /**
     * Every Shopify error code carried by this failure.
     *
     * @return string[]
     */
    public function getCodes(): array
    {
        return array_values(array_filter(array_map(
            static fn ($error) => isset($error['code']) ? (string) $error['code'] : null,
            $this->userErrors
        )));
    }

    public function hasCode(string $code): bool
    {
        return in_array($code, $this->getCodes(), true);
    }
}
