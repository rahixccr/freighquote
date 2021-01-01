<?php

class OrderController extends OrderControllerCore
{

    public function displayAjaxselectDeliveryOption()
    {
		$params = Tools::getAllValues();		
		if($params && is_array($params) && ($params['action']=="selectDeliveryOption") && ($params['ajax'] == 1) && $params['delivery_option'] ) {
			foreach($params['delivery_option'] as $item){
				$this->context->cart->setDeliveryOption($item);
				break;
			}
		}
		
        $cart = $this->cart_presenter->present(
            $this->context->cart
        );

        ob_end_clean();
		header('Content-Type: application/json');		
        $this->ajaxRender(Tools::jsonEncode(array(
            'preview' => $this->render('checkout/_partials/cart-summary', array(
                'cart' => $cart,
                'static_token' => Tools::getToken(false),
            )),
        )));
		/*
        //Edited by Ahsan Aftab
        $params = Tools::getAllValues();
        // var_dump($this->getCheckoutSession()->getSelectedDeliveryOption());die;

        //Extracting the id and the amount 
        $data = explode("__", reset($params["delivery_option"]) );

        if($params && is_array($params) && ($params['action']=="selectDeliveryOption") && ($params['ajax'] == 1) && $params['delivery_option'] )
            {
                // var_dump(Configuration::get('PS_SHIPPING_METHOD') , "------" , $cart , ":::::::::" , $this->context->cart->id,"====>>" , $id_order ,"<<::::::" , $this->context->cookie->id_cart );
                $delivery_optionsId = $data[0];
                $shippingCharges = $data[1];    

                //Setting the selected delivery option
                // $this->context->cart->setDeliveryOption($delivery_optionsId);
                var_dump($this->context->cart , ":::::" , $this->getCheckoutSession()->setDeliveryOption($delivery_optionsId) , "========" , $this->getCheckoutSession()->getSelectedDeliveryOption());die;
                //Getting the count of products 
                $countProducts  =  count($cart["products"] );

                //Calculating the shipping charges applicable to each product 
                $shippingChargesEachProduct = $shippingCharges/$countProducts; 

                $cart["subtotals"]["shipping"]["amount"] = $shippingCharges;
                $cart["subtotals"]["shipping"]["value"] = $shippingCharges;
                $cart["totals"]["total"]["amount"]+=$shippingCharges;

                //Get the products from cart and adding price
                 array_walk($cart["products"] , function(&$product , $key){
                                    $productTemp = new Product( $product->id_product);
                                    global $shippingChargesEachProduct;
                                    $productTemp->additional_shipping_cost = $shippingChargesEachProduct;
                                    $productTemp->save(); 
                                });
            }
         //End of edit by Ahsan Aftab
		*/
        
    }
}
