<?php

class LC_Page_Shopping_AmazonPay extends LC_Page_Cart_Ex
{
    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->skip_load_page_layout = true;
        $masterData = new SC_DB_MasterData_Ex();
        $this->arrPref = $masterData->getMasterData('mtb_pref');
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function process()
    {
        parent::process();
        $this->action();
        $this->sendResponse();
    }

    public function action()
    {
        $objCartSess = new SC_CartSession_Ex();
        $objPurchase = new SC_Helper_Purchase_Ex();
        $objSiteSess = new SC_SiteSession_Ex();

        $cartKey = $objCartSess->getKey();
        if (isset($_GET['cartKey'])) {
            $cartKey = intval($_GET['cartKey']);
        }
        // カート内商品のチェック
        $this->tpl_message = $objCartSess->checkProducts($cartKey);
        if (!SC_Utils_Ex::isBlank($this->tpl_message)) {
            SC_Response_Ex::sendRedirect(CART_URL);
            SC_Response_Ex::actionExit();
        }

        switch ($this->getMode()) {
            case 'processCheckout':
                $cartList = $objCartSess->getCartList($cartKey);
                // 商品が存在しない場合
                if (count($cartList) < 1) {
                    SC_Helper_AmazonPay::log('Get cartlist is empty. Redirect to cart: '.$cartKey);
                    SC_Response_Ex::sendRedirect(CART_URL);
                    SC_Response_Ex::actionExit();
                }
                // カートを購入モードに設定
                $this->lfSetCurrentCart($objSiteSess, $objCartSess, $cartKey);
                $checkoutSessionId = htmlspecialchars($_GET['amazonCheckoutSessionId'], ENT_QUOTES);
                $objAmazonPay = new SC_Helper_AmazonPay();
                try {
                    $arrBuyer = $objAmazonPay->getCheckoutSession($checkoutSessionId);
                    SC_Helper_AmazonPay::log('buyer: '.print_r($arrBuyer, true));
                    $arrOrder = SC_Helper_AmazonPay::buyerToArrayOfOrder($arrBuyer, ['memo04' => $checkoutSessionId]);
                    $arrOrder['update_date'] = 'CURRENT_TIMESTAMP'; // XXX null になってしまう場合がある
                    $arrShippings = SC_Helper_AmazonPay::buyerToArrayOfShipping($arrBuyer);
                    $objPurchase->saveShippingTemp($arrShippings);
                    $objPurchase->setShipmentItemTempForSole($objCartSess);
                    $objPurchase->saveOrderTemp($objSiteSess->getUniqId(), $arrOrder);
                    $objSiteSess->setRegistFlag();

                    // お支払い方法ページへ
                    SC_Response_Ex::sendRedirect(SHOPPING_PAYMENT_URLPATH);
                    SC_Response_Ex::actionExit();
                } catch (\Exception $e) {
                    SC_Helper_AmazonPay::log($e->getMessage());
                    $_SESSION['amazonpay_error'] = $e->getMessage();
                    SC_Response_Ex::sendRedirect(CART_URL);
                    SC_Response_Ex::actionExit();
                }

                break;
            case 'updateCheckout':
                $arrOrder = $objPurchase->getOrder($_SESSION['order_id']);
                $objAmazonPay = new SC_Helper_AmazonPay();

                $payload = [
                    'webCheckoutDetails' => [
                        'checkoutResultReturnUrl' => HTTPS_URL.'shopping/amazonpay.php?mode=completeCheckout'
                    ],
                    'paymentDetails' => [
                        'paymentIntent' => 'Authorize',
                        'canHandlePendingAuthorization' => false,
                        'chargeAmount' => [
                            'amount' => $arrOrder['payment_total'],
                            'currencyCode' => 'JPY'
                        ],
                    ],
                    'merchantMetadata' => [
                        'merchantReferenceId' => $arrOrder['order_id']                    ]
                ];

                try {
                    $result = $objAmazonPay->updateCheckoutSession($arrOrder['memo04'], $payload);
                    header('Location: '.$result['webCheckoutDetails']['amazonPayRedirectUrl']);
                } catch (\Exception $e) {
                    SC_Helper_AmazonPay::log($e->getMessage());
                    $_SESSION['amazonpay_error'] = $e->getMessage();
                    $objPurchase->rollbackOrder($arrOrder['order_id']);
                    SC_Response_Ex::sendRedirect(CART_URLPATH);
                }

                break;
            case 'completeCheckout':
                $objAmazonPay = new SC_Helper_AmazonPay();
                $arrOrder = $objPurchase->getOrder($_SESSION['order_id']);
                $payload = [
                    'chargeAmount' => [
                        'amount' => $arrOrder['payment_total'],
                        'currencyCode' => 'JPY'
                    ]
                ];

                try {
                    $result = $objAmazonPay->completeCheckoutSession($arrOrder['memo04'], $payload);
                    $objPurchase->sfUpdateOrderStatus($arrOrder['order_id'], ORDER_NEW);
                    $objPurchase->registerOrder($arrOrder['order_id'], ['memo05' => $result['chargePermissionId']]);

                    SC_Helper_Purchase_Ex::sendOrderMail($arrOrder['order_id'], $this);
                    SC_Response_Ex::sendRedirect(SHOPPING_COMPLETE_URLPATH);
                } catch (\Exception $e) {
                    SC_Helper_AmazonPay::log($e->getMessage());
                    $_SESSION['amazonpay_error'] = $e->getMessage();
                    $objPurchase->rollbackOrder($arrOrder['order_id']);
                    SC_Response_Ex::sendRedirect(CART_URLPATH);
                }

                break;
            default:
                SC_Helper_AmazonPay::log('query string is invalid: '.print_r($_GET, true));
                $_SESSION['amazonpay_error'] = '不正なパラメータを受信しました';
                SC_Response_Ex::sendRedirect(CART_URL);
        }

        SC_Response_Ex::actionExit();
    }
}
