<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 3/4/2026 11:59
 */
namespace Monta\Hyva\Magewire\Checkout\Shipping;

class Collect extends Pickup
{
    public const string IDENTIFIER = "StoreCollect";

    public function boot(): void
    {
        // Never show Map for Collect
        $this->showingMap = false;
    }

    /** Override from parent to avoid breaking
     *
     * @return void
     */
    protected function triggerMap(): void
    {
        // Do nothing, skip parent functionality
    }
}