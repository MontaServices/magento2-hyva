<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 3/10/2026 16:55
 */
namespace Monta\Hyva\Magewire\Checkout\Shipping;

use Magento\Quote\Model\Quote\Address;

class Totals extends Collect
{
    /**
     * @return Address|null
     */
    public function getPickupAddress(): ?Address
    {
        return $this->quoteHelper->convertShippingAddress(onlyPickup: true);
    }
}