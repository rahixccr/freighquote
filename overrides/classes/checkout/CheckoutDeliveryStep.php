<?php

class CheckoutDeliveryStep extends CheckoutDeliveryStepCore
{
    public function render(array $extraParams = array())
    { 
		$delivery_option = $this->context->cart->delivery_option;
		if(strpos($delivery_option, '{')>-1) {
			$tmp = json_decode($delivery_option, true);
			foreach($tmp as $item) {
				$delivery_option = $item;
				break;
			}
		}
		$api_options = Cart::getCarriersFromAPI();
		if($api_options) {			
			$_SESSION['carries_from_api'] = $api_options;
		}
        return $this->renderTemplate(
            $this->getTemplate(),
            $extraParams,
            array(
                'hookDisplayBeforeCarrier' => Hook::exec('displayBeforeCarrier', array('cart' => $this->getCheckoutSession()->getCart())),
                'hookDisplayAfterCarrier' => Hook::exec('displayAfterCarrier', array('cart' => $this->getCheckoutSession()->getCart())),
                'id_address' => $this->getCheckoutSession()->getIdAddressDelivery(),
                'delivery_options' => $this->getCheckoutSession()->getDeliveryOptions(),
                'delivery_option' => $delivery_option,
                'recyclable' => $this->getCheckoutSession()->isRecyclable(),
                'recyclablePackAllowed' => $this->isRecyclablePackAllowed(),
                'delivery_message' => $this->getCheckoutSession()->getMessage(),
                'gift' => array(
                    'allowed' => $this->isGiftAllowed(),
                    'isGift' => $this->getCheckoutSession()->getGift()['isGift'],
                    'label' => $this->getTranslator()->trans(
                        'I would like my order to be gift wrapped %cost%',
                        array('%cost%' => $this->getGiftCostForLabel()),
                        'Shop.Theme.Checkout'
                    ),
                    'message' => $this->getCheckoutSession()->getGift()['message']
                ),
                'options' => $api_options

            )
        );
    }
}
