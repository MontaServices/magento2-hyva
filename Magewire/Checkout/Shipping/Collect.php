<?php
/**
 * @author Jacco.Amersfoort <jacco.amersfoort@monta.nl>
 * @created 3/4/2026 11:59
 */
namespace Monta\Hyva\Magewire\Checkout\Shipping;

class Collect extends Pickup
{
    public const string IDENTIFIER = "StoreCollect";

    public bool $showingMap = false;
}