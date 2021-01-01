<?php

class Cart extends CartCore
{
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    public function getProducts($refresh = false, $id_product = false, $id_country = null, $fullInfos = true)
    {
        $parent = parent::getProducts($refresh, $id_product, $id_country, $fullInfos);
        $ret = array();
        $iteration = 0;
        $module = Module::getInstanceByName('an_productfields');
        $cart_product_context = Context::getContext()->cloneContext();
        $this->productfieldsaddon = 0;
        $this->productfieldsaddon_without_tax = 0;
        foreach ($parent as $product) {
            $product['price_without_specific_price'] = Product::getPriceStatic(
                $product['id_product'],
                !Product::getTaxCalculationMethod(),
                $product['id_product_attribute'],
                6,
                null,
                false,
                false,
                1,
                false,
                null,
                null,
                null,
                $null,
                true,
                true,
                $cart_product_context
            );
            $cartItemData = $module->getCartItemData(
                $this->id,
                $product['id_product'],
                $product['id_product_attribute']
            );
            if (count($cartItemData)) {
                if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
                    $address_id = (int)$this->id_address_invoice;
                } else {
                    $address_id = (int)$product['id_address_delivery'];
                }
                if (!Address::addressExists($address_id)) {
                    $address_id = null;
                }
                $context = Context::getContext()->cloneContext();
                if ($context->shop->id != $product['id_shop']) {
                    $context->shop = new Shop((int)$product['id_shop']);
                }
                $address = Address::initialize($address_id, true);
                if (Configuration::get('an_pf_include_tax')) {
                    $tax_manager = TaxManagerFactory::getManager(
                        $address,
                        Product::getIdTaxRulesGroupByIdProduct(
                            (int)$product['id_product'],
                            $context
                        )
                    );
                    $product_tax_calculator = $tax_manager->getTaxCalculator();
                } else {
                    $product_tax_calculator = null;
                }
                if (Configuration::get('an_pf_include_specific')
                    && array_key_exists("price_without_reduction", $product)
                    && array_key_exists("price_with_reduction", $product)
                    && $product['price_with_reduction'] != 0
                    && $product['price_without_reduction'] != 0
                ) {
                    $reduction = ($product['price_without_reduction']-$product['price_with_reduction'])/$product['price_without_reduction'];
                } else {
                    $reduction = 0;
                }
                $quantity = $product['quantity'];
                foreach ($cartItemData as $hash => $data) {
                    $ret[$iteration] = $product;
                    $ret[$iteration]['cart_quantity'] = $data['qty'];
                    $ret[$iteration]['quantity'] = $data['qty'];
                    $quantity = $quantity - $data['qty'];
                    $ret[$iteration]['hash'] = $hash;
                    $attributesstring = '';
                    foreach ($data['fieldvalues'] as $name => $values) {
                        $price = '';
                        if ($values['price'] > 0) {
                           
                            $currency = new Currency(
                                $this->id_currency ? $this->id_currency : Configuration::get('PS_CURRENCY_DEFAULT')
                            );
                            $this->addFieldsPrices($ret[$iteration], $values['price'], $product_tax_calculator, $reduction);
                            $this->productfieldsaddon_without_tax += $data['qty'] * ($values['price'] - ($values['price']*$reduction));
                          
                            $price = ' (+' ;
                            if ($product_tax_calculator != null) {
                                $price .= Tools::convertPriceFull($product_tax_calculator->addTaxes($values['price'] - ($values['price']*$reduction)), null, $currency);
                                $this->productfieldsaddon += $data['qty'] * $product_tax_calculator->addTaxes($values['price'] - ($values['price']*$reduction));
                            } else {
                                $price .= Tools::convertPriceFull($values['price'] - ($values['price']*$reduction), null, $currency);
                                $this->productfieldsaddon += $data['qty'] * ($values['price'] - ($values['price']*$reduction));
                            }
                            $price .= ' ' .$currency->sign . ')';
                        }
                        $keyprice = $module->getKeyPriceBothViewsFromString($values['value'], $product_tax_calculator, $reduction);
                        $attributesstring .= ' - ' . $name . $price . ': ';
                        foreach ($keyprice as $key => $pricesarray) {
                            if ($values['field_type'] === 'date'
                                && !strpos($key, '-')//these checks are needed for
                                && !strpos($key, '/')// older versions of an_productfields compability
                            ) {//Display date in prestashop current format
                                $endash = html_entity_decode('&#x2013;', ENT_COMPAT, 'UTF-8');
                                $key = str_replace('-', $endash, Tools::displayDate(date("Y-m-d", $key)));
                            }
                            $attributesstring .= $key . $pricesarray['string'] . '; ';
                            if ($product_tax_calculator != null) {
                            } else {
                            }
                        }
                    }
                    if (version_compare(_PS_VERSION_, '1.7.4.9', '>')
                        && Tools::strtolower(Dispatcher::getInstance()->getController()) == 'cart'
                    ) {
                        $attributesstring .= ' - productfields_hash: '.$hash;
                    }
                    $attributesstring = rtrim($attributesstring, '; ');
                    if (array_key_exists('attributes', $ret[$iteration])) {
                        $ret[$iteration]['attributes'] .= $attributesstring;
                    } else {
                        $ret[$iteration]['attributes'] = ltrim($attributesstring, ' -');
                    }
                    if (array_key_exists('price_with_reduction_without_tax', $product) && array_key_exists('total', $product)) {
                        $ret[$iteration]['total'] = $ret[$iteration]['price_with_reduction_without_tax'] * $data['qty'];
                    }
                    if (array_key_exists('price_with_reduction', $product) && array_key_exists('total_wt', $product)) {
                        $ret[$iteration]['total_wt'] = $ret[$iteration]['price_with_reduction'] * $data['qty'];
                    }
                    if (array_key_exists('price_without_reduction', $product) && array_key_exists('price_without_specific_price', $product)) {
                        $ret[$iteration]['price_without_specific_price'] = $ret[$iteration]['price_without_reduction'];
                    }
                    $ret[$iteration]['cart_quantity'] = $data['qty'];
                    $ret[$iteration]['quantity'] = $data['qty'];
                    $iteration++;
                }
                if ($quantity > 0) {
                    $ret[$iteration] = $product;
                    $ret[$iteration]['quantity'] = $quantity;
                    $ret[$iteration]['cart_quantity'] = $quantity;
                    $ret[$iteration]['total'] = $product['price'] * $quantity;
                    if (array_key_exists('price_wt', $ret[$iteration])) {
                        $ret[$iteration]['total_wt'] = $ret[$iteration]['price_wt'] * $quantity;
                    }
                    $iteration++;
                }
            } else {
                $ret[$iteration] = $product;
                $iteration++;
            }
        }
        if (version_compare(_PS_VERSION_, '1.7.4.9', '>')
            && Tools::getIsset('update')
            && Tools::getIsset('op')
            && Tools::getValue('op', 'up') == 'down'
        ) {
            return $parent;
        }
        return $ret;
    }
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    protected function addFieldsPrices(&$product, $fieldPrice, $taxCalculator, $reduction = 0)
    {
        if ($fieldPrice == 0 || $fieldPrice == '0') {
            return;
        }
        if (!Product::getTaxCalculationMethod()) {
            $product['price_without_specific_price']
                += $taxCalculator != null ? $taxCalculator->addTaxes($fieldPrice) : $fieldPrice;
        } else {
            $product['price_without_specific_price'] += $fieldPrice;
        }
        $product['price'] += $fieldPrice - ($fieldPrice*$reduction);
        if (array_key_exists('price_without_reduction', $product)) {
            $product['price_without_reduction']
                += $taxCalculator != null ? $taxCalculator->addTaxes($fieldPrice) : $fieldPrice;
        }
        if (array_key_exists('price_with_reduction', $product)) {
            $product['price_with_reduction']
                += $taxCalculator != null ? $taxCalculator->addTaxes($fieldPrice - ($fieldPrice*$reduction)) :
                $fieldPrice - ($fieldPrice*$reduction);
        }
        if (array_key_exists('price_wt', $product)) {
            $product['price_wt']
                += $taxCalculator != null ? $taxCalculator->addTaxes($fieldPrice - ($fieldPrice*$reduction)) :
                $fieldPrice - ($fieldPrice*$reduction);
        } else {
            $product['price_wt'] = $taxCalculator != null ? $taxCalculator->addTaxes($product['price']) :
                $product['price'];
        }
        if (array_key_exists('price_with_reduction_without_tax', $product)) {
            $product['price_with_reduction_without_tax'] += $fieldPrice - ($fieldPrice*$reduction);
        }
    }
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    public function getSummaryDetails($id_lang = null, $refresh = false)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')
            && isset($this->productfieldsaddon)
            && !(Context::getContext()->controller instanceof OrderOpcController)
        ) {
            $summary = parent::getSummaryDetails($id_lang, $refresh);
            $summary['total_products_wt']
                = round($summary['total_products_wt'] + $this->productfieldsaddon, 2, PHP_ROUND_HALF_UP);
            $summary['total_products']
                = round($summary['total_products'] + $this->productfieldsaddon_without_tax, 2, PHP_ROUND_HALF_UP);
            $summary['total_price']
                = round($summary['total_price'] + $this->productfieldsaddon, 2, PHP_ROUND_HALF_UP);
            $summary['total_tax']
                = round($summary['total_tax'] + $this->productfieldsaddon - $this->productfieldsaddon_without_tax, 2, PHP_ROUND_HALF_UP);
            $summary['total_price_without_tax']
                = round($summary['total_price_without_tax'] + $this->productfieldsaddon_without_tax, 2, PHP_ROUND_HALF_UP);
            $this->summary_pf_added = true;
            return $summary;
        } else {
            return parent::getSummaryDetails($id_lang, $refresh);
        }
    }
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    public function getOrderTotal(
        $with_taxes = true,
        $type = Cart::BOTH,
        $products = null,
        $id_carrier = null,
        $use_cache = true,
        $paymentmodule = false
    ) {
        $parent = $this->_getOrderTotal(
            $with_taxes,
            $type,
            $products,
            $id_carrier,
            $use_cache
        );
        if ((
                isset($this->summary_pf_added)
                && $this->summary_pf_added
                && !isset($this->total_firsttime)
            ) || (
                $paymentmodule
                || version_compare(_PS_VERSION_, '1.6.9.9', '>')
                || !(Context::getContext()->controller instanceof OrderController)
                
            ) && (
                $type == Cart::ONLY_PRODUCTS
                || $type == Cart::BOTH
                || $type == Cart::BOTH_WITHOUT_SHIPPING
                || $type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING
                || $type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING
            )
        ) {
            $discount = 0;
            if ($type != Cart::ONLY_PRODUCTS
            ) {
                $sql = '
                SELECT * FROM `' . _DB_PREFIX_ . 'cart_cart_rule` ccr';
                $sql .= '
                LEFT JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.`id_cart_rule` = ccr.`id_cart_rule`';
                $sql .= '
                WHERE ccr.`id_cart` = ' . (int)$this->id;
                $sql .= '
                AND cr.`reduction_product` = 0';
                $rows = Db::getInstance()->ExecuteS($sql);
                if (count($rows)) {
                    foreach ($rows as $row) {
                        $discount += $row['reduction_percent'];
                    }
                }
            }
            $reduction = $discount / 100;
            $this->total_firsttime = true;
            if (!isset($this->productfieldsaddon)) {
                $this->getProducts();
            }
            if ($with_taxes) {
                return round($parent + $this->productfieldsaddon - ($this->productfieldsaddon * $reduction), 2, PHP_ROUND_HALF_UP);
            } else {
                return round($parent + $this->productfieldsaddon_without_tax - ($this->productfieldsaddon_without_tax * $reduction), 2, PHP_ROUND_HALF_UP);
            }
        } elseif ((
                version_compare(_PS_VERSION_, '1.6.9.9', '>') || !(Context::getContext()->controller instanceof OrderController)
                
            )
            && $type == Cart::ONLY_DISCOUNTS
        ) {
            if (!isset($this->productfieldsaddon)) {
                $this->getProducts();
            }
            $discount = 0;
            $sql = '
                SELECT * FROM `' . _DB_PREFIX_ . 'cart_cart_rule` ccr';
            $sql .= '
                LEFT JOIN ' . _DB_PREFIX_ . 'cart_rule cr ON cr.`id_cart_rule` = ccr.`id_cart_rule`';
            $sql .= '
                WHERE ccr.`id_cart` = ' . (int)$this->id;
            $sql .= '
                AND cr.`reduction_product` = 0';
            $rows = Db::getInstance()->ExecuteS($sql);
            if (count($rows)) {
                foreach ($rows as $row) {
                    $discount += $row['reduction_percent'];
                }
            }
            $reduction = $discount / 100;
            return round($parent + ($this->productfieldsaddon * $reduction), 2, PHP_ROUND_HALF_UP);
        } else {
            return $parent;
        }
    }
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    public function deleteProduct(
        $id_product,
        $id_product_attribute = null,
        $id_customization = null,
        $id_address_delivery = 0,
        $auto_add_cart_rule = false
    ) {
        $module = Module::getInstanceByName('an_productfields');
        if (Tools::getIsset('anproductfieldshash') && Tools::getValue('anproductfieldshash') != 'no') {
            $quantity = $module->deleteByHash(Tools::getValue('anproductfieldshash'));
            $product_total_quantity = (int)Db::getInstance()->getValue(
                'SELECT `quantity`
				FROM `'._DB_PREFIX_.'cart_product`
				WHERE `id_product` = '.(int)$id_product.'
				AND `id_cart` = '.(int)$this->id.'
				AND `id_product_attribute` = '.(int)$id_product_attribute
            );
            $diference =$product_total_quantity-$quantity;
            if ($diference < 1) {
                return parent::deleteProduct($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $auto_add_cart_rule);
            }
            return $this->updateQty(
                $quantity,
                $id_product,
                $id_product_attribute,
                $id_customization,
                'down',
                $id_address_delivery
            );
        }
        if (Tools::getIsset('anproductfieldshash') && Tools::getValue('anproductfieldshash') == 'no') {
            $cartItemData = $module->getCartItemData(
                $this->id,
                $id_product,
                $id_product_attribute
            );
            if (count($cartItemData)) {
                $fieldsquantity = 0;
                foreach ($cartItemData as $hash => $data) {
                    $fieldsquantity += $data['qty'];
                }
                $row = '
                    SELECT quantity
                    FROM `'._DB_PREFIX_.'cart_product`
                    WHERE `id_cart` = '.(int)$this->id.'
                    AND `id_product` = '.(int)$id_product;
                if ($id_customization != null) {
                    $row .= ' AND `id_customization` = '.(int)$id_customization;
                }
                if ($id_product_attribute != null) {
                    $row .= ' AND `id_product_attribute` = '.(int)$id_product_attribute;
                }
                $result = Db::getInstance()->getRow($row);
                $quantity = $result['quantity'] - $fieldsquantity;
                if ($quantity < 1) {
                    return parent::deleteProduct($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $auto_add_cart_rule);
                }
                if ($quantity > 0) {
                    return $this->updateQty(
                        $quantity,
                        $id_product,
                        $id_product_attribute,
                        $id_customization,
                        'down',
                        $id_address_delivery
                    );
                } else {
                    return false;
                }
            }
        }
        $cartItemData = $module->getCartItemData(
            $this->id,
            $id_product,
            $id_product_attribute
        );
        if (count($cartItemData)) {
            foreach ($cartItemData as $hash => $data) {
                $module->deleteByHash($hash);
            }
        }
        return parent::deleteProduct($id_product, $id_product_attribute, $id_customization, $id_address_delivery, $auto_add_cart_rule);
    }
    /*
    * module: an_productfields
    * date: 2020-05-09 15:28:53
    * version: 2.4.2
    */
    public function duplicate()
    {
        $parent = parent::duplicate();
        if ($parent['success']) {
            $module = Module::getInstanceByName('an_productfields');
            $products = parent::getProducts();
            foreach ($products as $product) {
                $cartItemData = $module->getRawCartItemData(
                    $this->id,
                    $product['id_product'],
                    $product['id_product_attribute']
                );
                if (count($cartItemData)) {
                    $hashData = '';
                    $values = array();
                    foreach ($cartItemData as $data) {
                        $hashData .= $data['id_an_productfields'] . '_' . $this->id . '_' . $product['id_product']
                            . '_' . $product['id_product_attribute'] . '_' . pSQL($data['value']) . '_' . (float)$data['price'];
                        $values[] = array(
                            'id_an_productfields' => (int)$data['id_an_productfields'],
                            'id_cart' => (int)$parent['cart']->id,
                            'id_product' => (int)$product['id_product'],
                            'id_product_attribute' => (int)$product['id_product_attribute'],
                            'value' => pSQL($data['value']),
                            'field_name' => pSQL($data['field_name']),
                            'field_type' => pSQL($data['field_type']),
                            'price' => (float)$data['price'],
                        );
                        $values_hash = md5($hashData);
                        foreach ($values as $value) {
                            $value['values_hash'] = pSQL($values_hash);
                            Db::getInstance()->insert('an_productfields_cart', $value, true, false, Db::REPLACE);
                        }
                        $cart_values = array(
                            'values_hash' => pSQL($values_hash),
                            'qty' => (int)$data['qty']
                        );
                        Db::getInstance()->insert('an_productfields_cart_values', $cart_values, true, false, Db::REPLACE);
                    }
                }
            }
        }
        return $parent;
    }
    /*
    * module: productsamples
    * date: 2020-06-11 17:38:27
    * version: 2.0.12
    */
    public function getTotalWeight($products = null)
    {
        if (!Module::isEnabled('ProductSamples')) {
            return parent::getTotalWeight($products);
        }
        if (!is_null($products)) {
            $cart_controller = new PSCartFrontController($this);
            return $cart_controller->getTotalCartWeight($products);
        } else {
            $products = Context::getContext()->cart->getProducts();
            if (!empty($products)) {
                $cart_controller = new PSCartFrontController($this);
                return $cart_controller->getTotalCartWeight($products);
            }
            return parent::getTotalWeight($products);
        }
    }
	
	public function setDeliveryOption($delivery_option = null)
    {
		if (empty($delivery_option) || count($delivery_option) == 0) {
            $this->delivery_option = '';
            $this->id_carrier = 0;

            return;
        }
		
		Cache::clean('getContextualValue_*');
		
		if(strpos($delivery_option, '__')>0) {
			$this->delivery_option = $delivery_option;
			$this->update();
		} else {
        
			$delivery_option_list = $this->getDeliveryOptionList(null, true);

			foreach ($delivery_option_list as $id_address => $options) {
				if (!isset($delivery_option[$id_address])) {
					foreach ($options as $key => $option) {
						if ($option['is_best_price']) {
							$delivery_option[$id_address] = $key;

							break;
						}
					}
				}
			}

			if (count($delivery_option) == 1) {
				$this->id_carrier = $this->getIdCarrierFromDeliveryOption($delivery_option);
			}
			// var_dump($this->delivery_option, "------");die ;
			$this->delivery_option = json_encode($delivery_option);
		}

        // update auto cart rules
        CartRule::autoRemoveFromCart();
        CartRule::autoAddToCart();
    }
	
	public function getTotalShippingCost($delivery_option = null, $use_tax = true, Country $default_country = null)
    {
        if (isset(Context::getContext()->cookie->id_country)) {
            $default_country = new Country(Context::getContext()->cookie->id_country);
        }
        if (null === $delivery_option) {
            $delivery_option = $this->delivery_option; //getDeliveryOption($default_country, false, false);
			if(strpos($delivery_option, '{')>-1){
				$tmp = json_decode($delivery_option, true);
				foreach($tmp as $item){
					$delivery_option = $item;
					break;
				}
			}
        }		
		
		$_total_shipping = array(
			'with_tax' => 0,
			'without_tax' => 0,
		);
		
		if(strpos($delivery_option, '__')>0) {
			$tmp = explode('__', $delivery_option);
			
			$total_products_wt = $this->getOrderTotal(true, Cart::ONLY_PRODUCTS);
			$total_products = $this->getOrderTotal(false, Cart::ONLY_PRODUCTS);
			$tax_rate = $total_products_wt/$total_products;
			
			$_total_shipping['without_tax'] = (float) $tmp[1];
			$_total_shipping['with_tax'] = (float) $tmp[1] * $tax_rate;
			
			
		} else {
			/*
			$delivery_option_list = $this->getDeliveryOptionList($default_country);
			foreach ($delivery_option as $id_address => $key) {
				if (!isset($delivery_option_list[$id_address]) || !isset($delivery_option_list[$id_address][$key])) {
					continue;
				}

				$_total_shipping['with_tax'] += $delivery_option_list[$id_address][$key]['total_price_with_tax'];
				$_total_shipping['without_tax'] += $delivery_option_list[$id_address][$key]['total_price_without_tax'];
			}
			*/
		}

        return ($use_tax) ? $_total_shipping['with_tax'] : $_total_shipping['without_tax'];
    }    
	
	private function _getOrderTotal(
        $withTaxes = true,
        $type = Cart::BOTH,
        $products = null,
        $id_carrier = null,
        $use_cache = false
    ) {
        if ((int) $id_carrier <= 0) {
            $id_carrier = null;
        }

        // deprecated type
        if ($type == Cart::ONLY_PRODUCTS_WITHOUT_SHIPPING) {
            $type = Cart::ONLY_PRODUCTS;
        }

        // check type
        $type = (int) $type;
        $allowedTypes = array(
            Cart::ONLY_PRODUCTS,
            Cart::ONLY_DISCOUNTS,
            Cart::BOTH,
            Cart::BOTH_WITHOUT_SHIPPING,
            Cart::ONLY_SHIPPING,
            Cart::ONLY_WRAPPING,
            Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING,
        );
        if (!in_array($type, $allowedTypes)) {
            throw new \Exception('Invalid calculation type: ' . $type);
        }

        // EARLY RETURNS

        // if cart rules are not used
        if ($type == Cart::ONLY_DISCOUNTS && !CartRule::isFeatureActive()) {
            return 0;
        }
        // no shipping cost if is a cart with only virtuals products
        $virtual = $this->isVirtualCart();
        if ($virtual && $type == Cart::ONLY_SHIPPING) {
            return 0;
        }
        if ($virtual && $type == Cart::BOTH) {
            $type = Cart::BOTH_WITHOUT_SHIPPING;
        }

        // filter products
        if (null === $products) {
            $products = $this->getProducts();
        }

        if ($type == Cart::ONLY_PHYSICAL_PRODUCTS_WITHOUT_SHIPPING) {
            foreach ($products as $key => $product) {
                if ($product['is_virtual']) {
                    unset($products[$key]);
                }
            }
            $type = Cart::ONLY_PRODUCTS;
        }

        if (Tax::excludeTaxeOption()) {
            $withTaxes = false;
        }

        // CART CALCULATION
        $cartRules = array();
        if (in_array($type, [Cart::BOTH, Cart::BOTH_WITHOUT_SHIPPING, Cart::ONLY_DISCOUNTS])) {
            $cartRules = $this->getTotalCalculationCartRules($type, $type == Cart::BOTH);
        }

        $calculator = $this->newCalculator($products, $cartRules, $id_carrier);


        $computePrecision = $this->configuration->get('_PS_PRICE_COMPUTE_PRECISION_');
        // echo "Processing calculation...<BR/>";

        switch ($type) {
            case Cart::ONLY_SHIPPING:
                $calculator->calculateRows();
                $calculator->calculateFees($computePrecision);
                $amount = $calculator->getFees()->getInitialShippingFees();

                break;
            case Cart::ONLY_WRAPPING:
                $calculator->calculateRows();
                $calculator->calculateFees($computePrecision);
                $amount = $calculator->getFees()->getInitialWrappingFees(); 

                break;
			/*
            case Cart::BOTH:                

                $calculator->processCalculation($computePrecision);
                $amount = $calculator->getTotal();

                break;
			*/
            case Cart::BOTH_WITHOUT_SHIPPING:
				$calculator->calculateRows();
                // dont process free shipping to avoid calculation loop (and maximum nested functions !)
                $calculator->calculateCartRulesWithoutFreeShipping();
                $amount = $calculator->getTotal(true);
                break;
            case Cart::ONLY_PRODUCTS:
			case Cart::BOTH:                
                $calculator->calculateRows();
                $amount = $calculator->getRowTotal();

                break;
            case Cart::ONLY_DISCOUNTS:
                $calculator->processCalculation($computePrecision);
                $amount = $calculator->getDiscountTotal();

                break;
            default:
                throw new \Exception('unknown cart calculation type : ' . $type);
        }

        // TAXES ?

        $value = $withTaxes ? $amount->getTaxIncluded() : $amount->getTaxExcluded();
		if($type == Cart::BOTH) {
			$value += $this->getTotalShippingCost(null, $withTaxes);
		}

        // ROUND AND RETURN

        $compute_precision = $this->configuration->get('_PS_PRICE_COMPUTE_PRECISION_');

        return $value;
    }
	
	public function getSelectedCarrier() {
		$deliveryOptions = $_SESSION['carries_from_api'];
		if(!$deliveryOptions)
			$deliveryOptions = Cart::getCarriersFromAPI($this->id);//$this->getCheckoutSession()->getDeliveryOptions();
        $delivery_option = $this->delivery_option;//$this->getCheckoutSession()->getSelectedDeliveryOption();
		if(strpos($delivery_option, '{')>-1) {
			$tmp = json_decode($delivery_option, true);
			foreach($tmp as $item) {
				$delivery_option = $item;
				break;
			}
		}
		
		$carrier = array('name'=>'', 'delay'=>'0 day', 'price'=>'Free');
		if($deliveryOptions && $delivery_option) {
			$tmp = explode('__', $delivery_option);
			foreach($deliveryOptions as $key=>$item) {				
				if($tmp[0] == $item['id']) {
					$carrier['name'] = $item['name'];
					$carrier['price'] = Tools::displayPrice((float)$tmp[1], $this->context->currency);
					$carrier['amount'] = $tmp[1];
					break;
				}
			}
		}
		return $carrier;
	}
	
	//Fetch carriers from API
    public static function getCarriersFromAPI($id_cart=null){  
		if($id_cart == null)
			$id_cart = Context::getContext()->cart->id;
		$cart = new Cart($id_cart);
		if(!$cart) return null;
        $address = new Address($cart->id_address_delivery);      
        $products = $cart->getProducts();
    
        $productsXML = ''; $product_total = 0;
		if(sizeof($products)==0) return null;
		
        foreach ($products as $key => $product) 
        {  
            foreach($product['features_details'] as $features){                
               if($features['name']=='Flooring_coverage'){
	    			//Means the product is of flooring type
	                $flooring_coverage = $features['value'];
    	            $boxes = (int)($product['cart_quantity']/$flooring_coverage);

    	            //Check if products weight is 0 then set it to some standard value
    	            if($product['weight'] == '0')
    	            	$product['weight'] = 1;
                }
            }
			if($product['id_category_default']=='23' || $product['id_category_default']=='24'){
               $pallets = $boxes/48;
			}
			if($product['id_category_default']=='25'){
	            $pallets = $boxes/36;
    	    }
        	if($product['id_category_default']=='27'){
            	$pallets = $boxes/36;
        	}
        	if($product['id_category_default']=='26'){
            	$pallets = $boxes/30;
        	}
        	if($product['id_category_default']=='47' || $product['id_category_default']=='49'){
            	$pallets = $boxes/30;
            }
			
            $productsXML.="<Product>
                    <Class>55</Class>
                    <Weight>".($boxes*$product['weight'])."</Weight>
                    <Length>".(int)$product['depth']."</Length>
                    <Width>".(int)$product['width']."</Width>
                    <Height>".(int)$product['height']."</Height>
                    <ProductDescription>$product[description_short]</ProductDescription>
                    <PackageType>Pallets_other</PackageType>
                    <IsStackable>false</IsStackable>
                    <DeclaredValue>".$product['total']."</DeclaredValue>
                    <CommodityType>GeneralMerchandise</CommodityType>
                    <ContentType>NewCommercialGoods</ContentType>
                    <IsHazardousMaterial>false</IsHazardousMaterial>
                    <NMFC />
                    <DimWeight>0</DimWeight>
                    <EstimatedWeight>".$boxes*$product['weight']."</EstimatedWeight>
                    <PieceCount>9</PieceCount>
                    <ItemNumber>".((int)$key+1)."</ItemNumber>
                </Product>";
			$product_total = $product['total'];
        }
		
        $xml ="<?xml version='1.0' encoding='utf-8'?>
        <soap:Envelope xmlns:soap='http://schemas.xmlsoap.org/soap/envelope/' xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance' xmlns:xsd='http://www.w3.org/2001/XMLSchema'>
            <soap:Body>
                <GetRatingEngineQuote xmlns='http://tempuri.org/'>
                    <request>
                        <CustomerId>".$address->id_customer."</CustomerId>
                        <QuoteType>B2B</QuoteType>
                        <ServiceType>LTL</ServiceType>
                        <QuoteShipment>
                            <IsBlind>false</IsBlind>
                            <PickupDate>2020-09-10T00:00:00</PickupDate>
                            <SortAndSegregate>false</SortAndSegregate>
                            <UseStackableFlag>false</UseStackableFlag>
                            <DeclaredValue>".$product_total."</DeclaredValue>
                            <MaxPickupDate />
                    <TLDeliveryDate />
                    <TLEquipmentType>Any</TLEquipmentType>
                    <TLEquipmentSize>Any</TLEquipmentSize>
                    <TLTarpSizeType>NoTarpRequired</TLTarpSizeType>
                    <ShipmentLocations>
                                <Location>
                                    <LocationType>Origin</LocationType>
                                    <RequiresArrivalNotification>false</RequiresArrivalNotification>
                                    <HasLoadingDock>false</HasLoadingDock>
                                    <IsConstructionSite>false</IsConstructionSite>
                                    <RequiresInsideDelivery>false</RequiresInsideDelivery>
                                    <IsTradeShow>false</IsTradeShow>
                                    <TradeShow>TradeShowDesc</TradeShow>
                                    <IsResidential>false</IsResidential>
                                    <RequiresLiftgate>false</RequiresLiftgate>
                                    <HasDeliveryAppointment>false</HasDeliveryAppointment>
                                    <IsLimitedAccess>false</IsLimitedAccess>
                                    <LocationAddress>
                                        <PostalCode>L6T 1A2</PostalCode>
                                        <CountryCode>CA</CountryCode>
                                    </LocationAddress>
                                    <AdditionalServices />
                                </Location>
                                <Location>
                                    <LocationType>Destination</LocationType>
                                    <RequiresArrivalNotification>false</RequiresArrivalNotification>
                                    <HasLoadingDock>false</HasLoadingDock>
                                    <IsConstructionSite>false</IsConstructionSite>
                                    <RequiresInsideDelivery>false</RequiresInsideDelivery>
                                    <IsTradeShow>false</IsTradeShow>
                                    <TradeShow>TradeShowDesc</TradeShow>
                                    <IsResidential>false</IsResidential>
                                    <RequiresLiftgate>false</RequiresLiftgate>
                                    <HasAppointment>false</HasAppointment>
                                    <IsLimitedAccess>false</IsLimitedAccess>
                                    <LocationAddress>
                                        <PostalCode>".$address->postcode."</PostalCode>
                                        <CountryCode>CA</CountryCode>
                                    </LocationAddress>
                                    <AdditionalServices />
                                </Location>
                            </ShipmentLocations>
                            <ShipmentProducts>
                                ".$productsXML."
                                </ShipmentProducts>
                                <ShipmentContacts />
                            </QuoteShipment>
                        </request>
                        <user>
                            <Name>xmltest@freightquote.com</Name>
                            <Password>xml</Password>
                            <CredentialType>Default</CredentialType>
                        </user>
                    </GetRatingEngineQuote>
                </soap:Body>
            </soap:Envelope>";
			
        $url = 'https://b2b.freightquote.com/WebService/QuoteService.asmx';
        // $auth = base64_encode($this->uApi->getApiDetails('CREDENTIALS'));
        $soap_do = curl_init($url);
        $header = array(
			"Content-Type: text/xml;charset=UTF-8",
			"Accept: gzip,deflate",
			"Cache-Control: no-cache",
			"Pragma: no-cache",
			"Content-length: ".strlen($xml),
        );

        // Sending CURL Request To Fetch Data From API
        curl_setopt($soap_do, CURLOPT_CONNECTTIMEOUT, 120);
        curl_setopt($soap_do, CURLOPT_TIMEOUT, 120);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($soap_do, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($soap_do, CURLOPT_POST, true );
        curl_setopt($soap_do, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($soap_do, CURLOPT_HTTPHEADER, $header);
        curl_setopt($soap_do, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($soap_do);
        curl_close($soap_do);    
        $xml = simplexml_load_string($resp);
        
        //Confirming if we have info in the session
        $carrierOptionsArr  = array( );
        if(!($xml===false) && $xml->children("soap" , true)->count() ){
            $GetRatingEngineQuoteResponse = $xml->children("soap" , true)->Body->children()->GetRatingEngineQuoteResponse;
            
            //First check for errors
            $errorsList = $GetRatingEngineQuoteResponse->children()->GetRatingEngineQuoteResult->children()->ValidationErrors->children();
            foreach ($errorsList->B2BError as $key => $value) {
                # code...
                echo "".$value->children()->ErrorMessage."<br>";
            }
        
            $carrierOptions = $GetRatingEngineQuoteResponse->children()->GetRatingEngineQuoteResult->children()->QuoteCarrierOptions;     
            foreach ($carrierOptions->children() as $key => $value) {
                //Carrier option id
                $carrier = array();
                $carrier["id"] = "".$value->children()->CarrierOptionId;

                //CArrier Name
                $carrier["name"] = "".$value->children()->CarrierName;

                //Quote Amount
                $carrier["amount"] = "".$value->children()->QuoteAmount;

                $carrierOptionsArr[] = $carrier;

            }
        }
		
        return $carrierOptionsArr;
    }
}
