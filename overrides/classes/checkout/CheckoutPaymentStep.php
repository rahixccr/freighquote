<?php

class CheckoutPaymentStep extends CheckoutPaymentStepCore
{    
    public function render(array $extraParams = array())
    {
        $isFree = 0 == (float) $this->getCheckoutSession()->getCart()->getOrderTotal(true, Cart::BOTH);
        $paymentOptions = $this->paymentOptionsFinder->present($isFree);
        $conditionsToApprove = $this->conditionsToApproveFinder->getConditionsToApproveForTemplate();
        //$deliveryOptions = $this->context->cart->getCarriersFromAPI();//$this->getCheckoutSession()->getDeliveryOptions();
        $delivery_option = $this->context->cart->delivery_option;//$this->getCheckoutSession()->getSelectedDeliveryOption();		
		
		$selectedDeliveryOption = $this->context->cart->getSelectedCarrier();		

        $assignedVars = array(
            'is_free' => $isFree,
            'payment_options' => $paymentOptions,
            'conditions_to_approve' => $conditionsToApprove,
            'selected_payment_option' => $this->selected_payment_option,
            'selected_delivery_option' => $selectedDeliveryOption,
            'show_final_summary' => Configuration::get('PS_FINAL_SUMMARY_ENABLED'),
        );

        return $this->renderTemplate($this->getTemplate(), $extraParams, $assignedVars);
    }
}
