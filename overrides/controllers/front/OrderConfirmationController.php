<?php

class OrderConfirmationController extends OrderConfirmationControllerCore
{   

    /**
     * Assign template vars related to page content.
     *
     * @see FrontController::initContent()
     */
    public function initContent()
    {
        if (Configuration::isCatalogMode()) {
            Tools::redirect('index.php');
        }

        $order = new Order(Order::getIdByCartId((int) ($this->id_cart)));
        $presentedOrder = $this->order_presenter->present($order);
        $register_form = $this
            ->makeCustomerForm()
            ->setGuestAllowed(false)
            ->fillWith(Tools::getAllValues());

        FrontController::initContent();
		
		$cart = new Cart($this->id_cart);		
		$carrier = $cart->getSelectedCarrier();					
		
        $this->context->smarty->assign(array(
            'HOOK_ORDER_CONFIRMATION' => $this->displayOrderConfirmation($order),
            'HOOK_PAYMENT_RETURN' => $this->displayPaymentReturn($order),
            'order' => $presentedOrder,
            'register_form' => $register_form,
			'carrier' => $carrier
        ));

        if ($this->context->customer->is_guest) {
            /* If guest we clear the cookie for security reason */
            $this->context->customer->mylogout();
        }
        $this->setTemplate('checkout/order-confirmation');
    }
}
