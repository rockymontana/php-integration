<?php

$root = realpath(dirname(__FILE__));
require_once $root . '/../../../src/Includes.php';
require_once $root . '/../../TestUtil.php';

/** helper class, used to return information about an order */
class orderToCredit {
    var $orderId;
    var $invoiceId;

    function orderToCredit( $orderId, $invoiceId ) {
        $this->orderId = $orderId;
        $this->invoiceId = $invoiceId;
    }
}

/**
 * @author Kristian Grossman-Madsen for Svea WebPay
 */
class CreditOrderRowsRequestIntegrationTest extends PHPUnit_Framework_TestCase {

    /** helper function, returns invoice for delivered order with one row, sent with PriceIncludingVat flag set to true */
    public function get_orderInfo_sent_inc_vat( $amount, $vat, $quantity ) {
        $config = Svea\SveaConfig::getDefaultConfig();
        $orderResponse = WebPay::createOrder($config)
                ->addOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountIncVat($amount)
                        ->setVatPercent($vat)
                        ->setQuantity($quantity)
                )
                ->addCustomerDetails(TestUtil::createIndividualCustomer("SE"))
                ->setCountryCode("SE")
                ->setOrderDate("2012-12-12")
                ->useInvoicePayment()->doRequest();
        $this->assertEquals(1, $orderResponse->accepted);
        
        $deliver = WebPayAdmin::deliverOrderRows($config)
                ->setOrderId($orderResponse->sveaOrderId)
                ->setCountryCode('SE')
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setRowToDeliver(1)
                ->deliverInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $deliver->accepted); 
        
        return new orderToCredit( $orderResponse->sveaOrderId, $deliver->invoiceId );
    }     
 
    /** helper function, returns invoice for delivered order with one row, sent with PriceIncludingVat flag set to false */
    public function get_orderInfo_sent_ex_vat( $amount, $vat, $quantity ) {
        $config = Svea\SveaConfig::getDefaultConfig();
        $orderResponse = WebPay::createOrder($config)
                ->addOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountExVat($amount)
                        ->setVatPercent($vat)
                        ->setQuantity($quantity)
                )
                ->addCustomerDetails(TestUtil::createIndividualCustomer("SE"))
                ->setCountryCode("SE")
                ->setOrderDate("2012-12-12")
                ->useInvoicePayment()->doRequest();
        $this->assertEquals(1, $orderResponse->accepted);
        
        $deliver = WebPayAdmin::deliverOrderRows($config)
                ->setOrderId($orderResponse->sveaOrderId)
                ->setCountryCode('SE')
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setRowToDeliver(1)
                ->deliverInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $deliver->accepted); 
        
        return new orderToCredit( $orderResponse->sveaOrderId, $deliver->invoiceId );
    } 
    
    
    public function test_creditOrderRows_creditInvoiceOrderRows_credit_row_using_row_index() {
        $config = Svea\SveaConfig::getDefaultConfig();

        $orderInfo = $this->get_orderInfo_sent_ex_vat( 99.99, 24, 1 );

        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->setRowToCredit(1)
                ->creditInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $credit->accepted); 
        //print_r($credit);
    } 
           
    public function test_creditOrderRows_creditInvoiceOrderRows_credit_row_using_new_order_row_original_exvat_new_exvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_ex_vat( 99.99, 24, 1 );

        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);                
        $this->assertEquals("99.99", $query->numberedOrderRows[0]->amountExVat);
        $this->assertEquals("24", $query->numberedOrderRows[0]->vatPercent);            
        
        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->addCreditOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountExVat(99.99) // => 123.9876 inc
                        ->setVatPercent(24)
                        ->setQuantity(1)
                )
                ->creditInvoiceOrderRows()->doRequest();
        //print_r($credit);
        $this->assertEquals(1, $credit->accepted); 
   
        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);   
        // NOTE the order row status/amount does not reflect that the corresponding invoice row has been credited
        // TODO implement queryInvoice and recurse invoices to get the current order row status
        $this->assertEquals("99.99", $query->numberedOrderRows[0]->amountExVat);   // sent 99.99 ex * 1.24 => sent 123.9876 inc => 123.99 queried
        $this->assertEquals("24", $query->numberedOrderRows[0]->vatPercent);
    }
    
    public function test_creditOrderRows_creditInvoiceOrderRows_credit_row_using_original_exvat_new_order_incvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_ex_vat( 99.99, 24, 1 );

        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);                
        $this->assertEquals("99.99", $query->numberedOrderRows[0]->amountExVat);
        $this->assertEquals("24", $query->numberedOrderRows[0]->vatPercent);            

        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->setRowToCredit(1)
                ->creditInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $credit->accepted); 
        //print_r($credit);
   
        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);   
        $this->assertEquals("99.99", $query->numberedOrderRows[0]->amountExVat);   // sent 99.99 ex * 1.24 => sent 123.9876 inc => 123.99 queried
        $this->assertEquals("24", $query->numberedOrderRows[0]->vatPercent);
    }
           
    /// characterizing unit tests for INTG-551
    function test_creditOrderRows_handles_creditOrderRows_specified_using_exvat_and_vatpercent() {       
        // needs either setRow(s)ToCredit or addCreditOrderRow(s)    
        $creditOrder = WebPayAdmin::creditOrderRows( Svea\SveaConfig::getDefaultConfig() )
            ->setInvoiceId("123456789")                
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setCountryCode("SE")            
            ->addCreditOrderRow( 
                    WebPayItem::orderRow()
                        ->setAmountExVat(10.00)
                        ->setVatPercent(25)
                        ->setQuantity(1)
            )
        ;
        $request = $creditOrder->creditInvoiceOrderRows()->prepareRequest();
     
        $this->assertEquals("10", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PricePerUnit->enc_value);
        $this->assertEquals("25", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->VatPercent->enc_value);
        $this->assertEquals(null, $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PriceIncludingVat->enc_value);       
    }
    
    function test_creditOrderRows_handles_creditOrderRows_specified_using_incvat_and_vatpercent() {       
        // needs either setRow(s)ToCredit or addCreditOrderRow(s)    
        $creditOrder = WebPayAdmin::creditOrderRows( Svea\SveaConfig::getDefaultConfig() )
            ->setInvoiceId("123456789")                
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setCountryCode("SE")            
            ->addCreditOrderRow( 
                    WebPayItem::orderRow()
                        ->setAmountIncVat(10.00)
                        ->setVatPercent(25)
                        ->setQuantity(1)
            )
        ;
        $request = $creditOrder->creditInvoiceOrderRows()->prepareRequest();
     
        $this->assertEquals("10", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PricePerUnit->enc_value);
        $this->assertEquals("25", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->VatPercent->enc_value);
        $this->assertEquals(true, $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PriceIncludingVat->enc_value);    
    }    
    
    function test_creditOrderRows_handles_creditOrderRows_specified_using_incvat_and_exvat() {       
        // needs either setRow(s)ToCredit or addCreditOrderRow(s)    
        $creditOrder = WebPayAdmin::creditOrderRows( Svea\SveaConfig::getDefaultConfig() )
            ->setInvoiceId("123456789")                
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setCountryCode("SE")            
            ->addCreditOrderRow( 
                    WebPayItem::orderRow()
                        ->setAmountIncVat(12.50)
                        ->setAmountExVat(10.00)
                        ->setQuantity(1)
            )
        ;
        $request = $creditOrder->creditInvoiceOrderRows()->prepareRequest();

    $this->assertEquals("12.50", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PricePerUnit->enc_value);
    $this->assertEquals("25", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->VatPercent->enc_value);
    $this->assertEquals(true, $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PriceIncludingVat->enc_value);    
    }  
    
    // INTG-551 integration tests
    public function test_credit_row_sent_exandvat_credit_sent_incandex() {  // credit req. should be resent, see backoffice logs
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_ex_vat( 100.00, 25, 2 );

        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);                
        $this->assertEquals("100", $query->numberedOrderRows[0]->amountExVat);
        $this->assertEquals(null, $query->numberedOrderRows[0]->amountIncVat);
        $this->assertEquals("25", $query->numberedOrderRows[0]->vatPercent);            
        $this->assertEquals("2", $query->numberedOrderRows[0]->quantity);            

        $creditOrder = WebPayAdmin::creditOrderRows( $config )
            ->setInvoiceId($orderInfo->invoiceId)                
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setCountryCode("SE")            
            ->addCreditOrderRow( 
                    WebPayItem::orderRow()
                        ->setAmountIncVat(20.00)
                        ->setAmountExVat(16.00)
                        ->setQuantity(1)
            )
        ;
        $request = $creditOrder->creditInvoiceOrderRows()->prepareRequest();
     
        $this->assertEquals("20", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PricePerUnit->enc_value);
        $this->assertEquals("25", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->VatPercent->enc_value);
        $this->assertEquals(true, $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PriceIncludingVat->enc_value);           

        $response = $creditOrder->creditInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $response->accepted); 
   
        // query order and assert row totals
        $query2 = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query2->accepted);   

        // NOTE the order row status/amount does not reflect that the corresponding invoice row has been credited
        // TODO implement queryInvoice and recurse invoices to get the current order row status
        $this->assertEquals("100", $query2->numberedOrderRows[0]->amountExVat);
        $this->assertEquals(null, $query2->numberedOrderRows[0]->amountIncVat);
        $this->assertEquals("25", $query2->numberedOrderRows[0]->vatPercent);            
        $this->assertEquals("2", $query2->numberedOrderRows[0]->quantity);           
        // nope, can't be seen in the order, only in backoffice in delivered invoice as cumulative discount amount
        // $this->assertEquals("-10.00", $query->numberedOrderRows[1]->amountExVat); 
        // $this->assertEquals("25", $query->numberedOrderRows[1]->vatPercent);        
    }    

    public function test_credit_row_sent_inc_credit_sent_incandex() {  // credit req. should not be resent, see backoffice logs
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_inc_vat( 125.00, 25, 2 );

        // query order and assert row totals
        $query = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query->accepted);                
        $this->assertEquals(null, $query->numberedOrderRows[0]->amountExVat);
        $this->assertEquals("125", $query->numberedOrderRows[0]->amountIncVat);
        $this->assertEquals("25", $query->numberedOrderRows[0]->vatPercent);            
        $this->assertEquals("2", $query->numberedOrderRows[0]->quantity);            

        $creditOrder = WebPayAdmin::creditOrderRows( $config )
            ->setInvoiceId($orderInfo->invoiceId)                
            ->setInvoiceDistributionType(DistributionType::POST)
            ->setCountryCode("SE")            
            ->addCreditOrderRow( 
                    WebPayItem::orderRow()
                        ->setAmountIncVat(20.00)
                        ->setAmountExVat(16.00)
                        ->setQuantity(1)
            )
        ;
        $request = $creditOrder->creditInvoiceOrderRows()->prepareRequest();
     
        $this->assertEquals("20", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PricePerUnit->enc_value);
        $this->assertEquals("25", $request->NewCreditInvoiceRows->enc_value[0]->enc_value->VatPercent->enc_value);
        $this->assertEquals(true, $request->NewCreditInvoiceRows->enc_value[0]->enc_value->PriceIncludingVat->enc_value);           

        $response = $creditOrder->creditInvoiceOrderRows()->doRequest();
        $this->assertEquals(1, $response->accepted); 
   
        // query order and assert row totals
        $query2 = WebPayAdmin::queryOrder($config)
                ->setOrderId($orderInfo->orderId)
                ->setCountryCode('SE')
                ->queryInvoiceOrder()->doRequest();       
        $this->assertEquals(1, $query2->accepted);   

        // NOTE the order row status/amount does not reflect that the corresponding invoice row has been credited
        // TODO implement queryInvoice and recurse invoices to get the current order row status
        $this->assertEquals(null, $query2->numberedOrderRows[0]->amountExVat);
        $this->assertEquals("125", $query2->numberedOrderRows[0]->amountIncVat);
        $this->assertEquals("25", $query2->numberedOrderRows[0]->vatPercent);            
        $this->assertEquals("2", $query2->numberedOrderRows[0]->quantity);           
        // nope, can't be seen in the order, only in backoffice in delivered invoice as cumulative discount amount
        // $this->assertEquals("-20.00", $query->numberedOrderRows[1]->amountExVat); 
        // $this->assertEquals("25", $query->numberedOrderRows[1]->vatPercent);        
    } 
    
    /// characterising integration test for INTG-576
    public function test_creditOrderRows_creditInvoiceOrderRows_original_exvat_new_exvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_ex_vat( 100.00, 25, 1 );
        
        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->addCreditOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountExVat(10.00)
                        ->setVatPercent(25)
                        ->setQuantity(1)
                )
                ->creditInvoiceOrderRows()->doRequest();
        // logs should createOrderEU (w/priceIncludingVat = false) => deliverOrderRows 
        // => creditOrderRows (w/priceIncludingVat = false) success        
        $this->assertEquals(1, $credit->accepted);
    }
    
    public function test_creditOrderRows_creditInvoiceOrderRows_original_exvat_new_incvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_ex_vat( 100.00, 25, 1 );

        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->addCreditOrderRow( 
                        WebPayItem::orderRow()
                            ->setAmountIncVat(12.50)
                            ->setVatPercent(25)
                            ->setQuantity(1)
                )                
                ->creditInvoiceOrderRows()->doRequest();
        // logs should createOrderEU (w/priceIncludingVat = false) => deliverOrderRows 
        // => creditOrderRows (w/priceIncludingVat = false) fail =>  creditOrderRows (w/priceIncludingVat = true) success          
        $this->assertEquals(1, $credit->accepted); 
    }

    public function test_creditOrderRows_creditInvoiceOrderRows_original_incvat_new_incvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_inc_vat( 100.00, 25, 1 );
        
        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->addCreditOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountIncVat(10)
                        ->setVatPercent(25)
                        ->setQuantity(1)
                )
                ->creditInvoiceOrderRows()->doRequest();
        // logs should createOrderEU (w/priceIncludingVat = true) => deliverOrderRows 
        // => creditOrderRows (w/priceIncludingVat = true) success                
        $this->assertEquals(1, $credit->accepted); 
    }
    
    public function test_creditOrderRows_creditInvoiceOrderRows_original_incvat_new_exvat() {
        $config = Svea\SveaConfig::getDefaultConfig();
        
        $orderInfo = $this->get_orderInfo_sent_inc_vat( 100.00, 25, 1 );               

        $credit = WebPayAdmin::creditOrderRows($config)
                ->setInvoiceId($orderInfo->invoiceId)
                ->setInvoiceDistributionType(DistributionType::POST)
                ->setCountryCode('SE')
                ->addCreditOrderRow(
                        WebPayItem::orderRow()
                        ->setAmountExVat(8)
                        ->setVatPercent(25)
                        ->setQuantity(1)
                )
                ->creditInvoiceOrderRows()->doRequest();
        // logs should createOrderEU (w/priceIncludingVat = true) => deliverOrderRows 
        // => creditOrderRows (w/priceIncludingVat = false) fail =>  creditOrderRows (w/priceIncludingVat = true) success          
        $this->assertEquals(1, $credit->accepted); 

    }    
}