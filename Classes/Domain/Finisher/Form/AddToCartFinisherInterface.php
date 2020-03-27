<?php

namespace Extcode\Cart\Domain\Finisher\Form;

/*
 * This file is part of the package extcode/cart.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Extcode\Cart\Domain\Model\Cart\Cart;

interface AddToCartFinisherInterface
{
    /**
     * @param array $formValues
     * @param Cart $cart
     */
    public function getProductFromForm(
        array $formValues,
        Cart $cart
    );
}
