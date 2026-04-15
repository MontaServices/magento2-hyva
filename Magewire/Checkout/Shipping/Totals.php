<?php
/**
 * @created 3/10/2026 16:55
 */
namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Hyva\Checkout\Model\Navigation\Navigator;
use Magento\Quote\Model\Quote\Address;
use Monta\Checkout\Helper\Quote;

class Totals extends Collect
{
    /**
     * @param Quote $quoteHelper
     * @param Navigator $navigator
     */
    public function __construct(
        Quote $quoteHelper,
        protected readonly Navigator $navigator,
    )
    {
        parent::__construct($quoteHelper);
    }

    /**
     * @return Address|null
     */
    public function getPickupAddress(): ?Address
    {
        // Only return something on Payment step
        return $this->navigator->getActiveStep()->getName() == 'payment'
            ? $this->quoteHelper->convertShippingAddress(onlyPickup: true) : null;
    }
}