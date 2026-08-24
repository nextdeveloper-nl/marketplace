<?php

namespace NextDeveloper\Marketplace\Exceptions;

use NextDeveloper\Commons\Exceptions\AbstractCommonsException;

/**
 * Thrown when a compare-and-swap inventory write is rejected because the remote
 * quantity no longer matches the value we last observed.
 *
 * This is an expected outcome, not a fault: someone sold or adjusted stock on
 * the marketplace between our read and our write. The caller must re-read the
 * remote level and re-decide. Retrying the original write is exactly the
 * behaviour that produces oversell.
 */
class StaleInventoryException extends AbstractCommonsException
{
    protected $defaultMessage = 'Stock changed on the marketplace while we were updating it. Re-reading and trying again.';

    /**
     * Quantity the marketplace reports right now, when it tells us.
     */
    protected ?int $actualQuantity = null;

    public function __construct(string $message, ?int $actualQuantity = null, int $code = 0, ?\Exception $previous = null)
    {
        $this->actualQuantity = $actualQuantity;

        parent::__construct($message, $code, $previous);
    }

    public function getActualQuantity(): ?int
    {
        return $this->actualQuantity;
    }
}
