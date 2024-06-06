<?php

use Amazon\Pay\API\Client;

class SC_Helper_AmazonPay
{
    /** @var int */
    const PAYMENT_ID = 999998;
    /** @var string */
    const PAYMENT_METHOD = 'Amazon Pay';
    /** @var string */
    const MODULE_CODE = 'amazonpay_v2';
    /** @var string */
    const LOG_FILE = DATA_REALDIR.'logs/amazonpay.log';
    /** @var string */
    const TEMPLATE_REALDIR = __DIR__.'/../../../templates';
    /** @var string[] */
    const HYPHEN = ['‐', '-', '‑', '⁃', '−', 'ｰ'];

    /**
     * @var array{public_key_id:string, private_key:string, region:string, sandbox: bool}
     */
    private $amazonpayConfig;

    public function __construct()
    {
        $this->amazonpayConfig = self::getConfig();
    }

    /**
     * @return array{public_key_id:string, private_key:string, region:string, sandbox: bool}
     */
    public static function getConfig(): array
    {
        return [
            'public_key_id' => (string)getenv('AMAZONPAY_PUBLIC_KEY_ID'),
            'private_key'   => (string)getenv('AMAZONPAY_PRIVATE_KEY'),
            'region'        => (string)strtolower(getenv('AMAZONPAY_REGION')),
            'sandbox'       => (bool)getenv('AMAZONPAY_SANDBOX')
        ];
    }

    /**
     * @return string
     */
    public function generateSignature(array $payload): string
    {
        $client = new Client($this->amazonpayConfig);
        $signature = $client->generateButtonSignature($payload);

        return $signature;
    }

    public function getCheckoutSession(string $checkoutSessionId): array
    {
        $client = new Client($this->amazonpayConfig);
        $result = $client->getCheckoutSession($checkoutSessionId);
        self::log(print_r($result, true));

        $response = json_decode($result['response'], true);
        if ($result['status'] === 200) {
            return $response;
        }

        throw new \Exception($response['message']);
    }

    public function updateCheckoutSession(string $checkoutSessionId, array $payload): array
    {
        $client = new Client($this->amazonpayConfig);
        $result = $client->updateCheckoutSession($checkoutSessionId, $payload);
        self::log(print_r($result, true));

        $response = json_decode($result['response'], true);
        if ($result['status'] === 200) {
            return $response;
        }

        throw new \Exception($response['message']);
    }

    public function completeCheckoutSession(string $checkoutSessionId, array $payload): array
    {
        $client = new Client($this->amazonpayConfig);
        $result = $client->completeCheckoutSession($checkoutSessionId, $payload);
        self::log(print_r($result, true));

        $response = json_decode($result['response'], true);
        if ($result['status'] === 200) {
            return $response;
        }

        throw new \Exception($response['message']);
    }
    // Example of buyer response
    //
    // $buyer = array (
    //     'buyerId' => 'amzn1.account.AFCEARACKEJ2GBSGXLDWBGLZH6TA',
    //     'name' => '大河内健太郎',
    //     'email' => 'ohkouchi@skirnir.dev',
    //     'postalCode' => '1530064',
    //     'countryCode' => 'JP',
    //     'phoneNumber' => '+0398765432',
    //     'shippingAddress' =>
    //     array (
    //         'name' => 'テスト姓名二',
    //         'addressLine1' => '目黒区下目黒1-8-1',
    //         'addressLine2' => NULL,
    //         'addressLine3' => NULL,
    //         'city' => NULL,
    //         'county' => NULL,
    //         'district' => NULL,
    //         'stateOrRegion' => 'Tokyo',
    //         'postalCode' => '1530064',
    //         'countryCode' => 'JP',
    //         'phoneNumber' => '‪0312345678',
    //     ),
    //     'billingAddress' =>
    //     array (
    //         'name' => '請求先テスト',
    //         'addressLine1' => '品川区上大崎 3-1-1',
    //         'addressLine2' => '目黒セントラルスクエア',
    //         'addressLine3' => NULL,
    //         'city' => NULL,
    //         'county' => NULL,
    //         'district' => NULL,
    //         'stateOrRegion' => '東京都',
    //         'postalCode' => '141-0021',
    //         'countryCode' => 'JP',
    //         'phoneNumber' => '+0398765432',
    //     ),
    //     'primeMembershipTypes' => NULL,
    // );

    /**
     * @param array{
     *     buyerId: string,
     *     name: string,
     *     email: string,
     *     billingAddress: array{
     *         postalCode: string,
     *         stateOrRegion: string,
     *         city: string,
     *         addressLine1: string,
     *         addressLine2: string,
     *         addressLine3: string,
     *         phoneNumber: string,
     *     }
     * } $buyer
     * @param array{
     *     customer_id?: int,
     *     order_name01?: string,
     * } $arrOrder
     * @return array{
     *     customer_id?: int,
     *     order_name01?: string,
     * }
     */
    public static function buyerToArrayOfOrder(array $buyer, array $arrOrder = []): array
    {
        $arrOrder['memo06'] = $buyer['buyer']['buyerId'];
        $arrOrder['order_name01'] = $buyer['buyer']['name'];
        $arrOrder['order_name02'] = '';
        $arrOrder['order_email'] = $buyer['buyer']['email'];
        $arrOrder['payment_id'] = self::PAYMENT_ID;
        $arrOrder['payment_method'] = self::PAYMENT_METHOD;
        if (array_key_exists('billingAddress', $buyer)) {
            list($arrOrder['order_zip01'], $arrOrder['order_zip02']) = explode('-', $buyer['billingAddress']['postalCode']);
            $arrOrder['order_pref'] = self::convertToPrefId($buyer['billingAddress']['stateOrRegion']);
            $arrOrder['order_addr01'] = $buyer['billingAddress']['city'].$buyer['billingAddress']['addressLine1'];
            $arrOrder['order_addr02'] = $buyer['billingAddress']['addressLine2'];
            $arrOrder['order_company_name'] = $buyer['billingAddress']['addressLine3'];
            if (array_key_exists('phoneNumber', $buyer['billingAddress']) && !empty($buyer['billingAddress']['phoneNumber'])) {
                $phone = str_replace(self::HYPHEN, '', $buyer['billingAddress']['phoneNumber']);
                $plus = '';
                list($arrOrder['order_tel01'], $arrOrder['order_tel02'], $arrOrder['order_tel03'], $plus) = str_split($phone, 4);
                $arrOrder['order_tel03'] .= $plus;
            }
        }
        $arrOrder['memo07'] = $buyer['paymentPreferences'][0]['paymentDescriptor'];
        $objCustomer = new SC_Customer_Ex();
        if ($objCustomer->isLoginSuccess(true)) {
            $arrOrder['customer_id'] = $objCustomer->getValue('customer_id');
        } else {
            $arrOrder['customer_id'] = 0;
        }

        return $arrOrder;
    }

    public static function buyerToArrayOfShipping(array $buyer, array $arrShipping = []): array
    {
        if (array_key_exists('shippingAddress', $buyer)) {
            $arrShipping['shipping_name01'] = $buyer['shippingAddress']['name'];
            $arrShipping['shipping_name02'] = '';

            list($arrShipping['shipping_zip01'], $arrShipping['shipping_zip02']) = explode('-', $buyer['shippingAddress']['postalCode']);
            $arrShipping['shipping_pref'] = self::convertToPrefId($buyer['shippingAddress']['stateOrRegion']);
            $arrShipping['shipping_addr01'] = $buyer['shippingAddress']['city'].$buyer['shippingAddress']['addressLine1'];
            $arrShipping['shipping_addr02'] = $buyer['shippingAddress']['addressLine2'];
            $arrShipping['shipping_company_name'] = $buyer['shippingAddress']['addressLine3'];
            if (array_key_exists('phoneNumber', $buyer['shippingAddress']) && !empty($buyer['shippingAddress']['phoneNumber'])) {
                $phone = str_replace(self::HYPHEN, '', $buyer['shippingAddress']['phoneNumber']);
                $plus = '';
                list($arrShipping['shipping_tel01'], $arrShipping['shipping_tel02'], $arrShipping['shipping_tel03'], $plus) = str_split($phone, 4);
                $arrShipping['shipping_tel03'] .= $plus;
            }
        }

        return $arrShipping;
    }

    public static function convertToPrefId(string $StateOrRegion): int
    {
        $prefs = [
            'Hokkaido' => 1,
            'Aomori' => 2,
            'Iwate' => 3,
            'Miyagi' => 4,
            'Akita' => 5,
            'Yamagata' => 6,
            'Fukushima' => 7,
            'Ibaragi' => 8,
            'Tochigi' => 9,
            'Gunma' => 10,
            'Saitama' => 11,
            'Chiba' => 12,
            'Tokyo' => 13,
            'Kanagawa' => 14,
            'Nigata' => 15,
            'Toyama' => 16,
            'Ishikawa' => 17,
            'Fukui' => 18,
            'Yamanashi' => 19,
            'Nagano' => 20,
            'Gifu' => 21,
            'Shizuoka' => 22,
            'Aichi' => 23,
            'Mie' => 24,
            'Shiga' => 25,
            'Kyoto' => 26,
            'Osaka' => 27,
            'Hyogo' => 28,
            'Nara' => 29,
            'Wakayama' => 30,
            'Tottori' => 31,
            'Shimane' => 32,
            'Okayama' => 33,
            'Hiroshima' => 34,
            'Yamaguchi' => 35,
            'Tokushima' => 36,
            'Kagawa' => 37,
            'Ehime' => 38,
            'Kochi' => 39,
            'Fukuoka' => 40,
            'Saga' => 41,
            'Nagasaki' => 42,
            'Kumamoto' => 43,
            'Oita' => 44,
            'Miyazaki' => 45,
            'Kagoshima' => 46,
            'Okinawa' => 47,
            'Hokkai' => 1,
            'Hokkai-do' => 1,
            'Aomori-ken' => 2,
            'Iwate-ken' => 3,
            'Miyagi-ken' => 4,
            'Akita-ken' => 5,
            'Yamagata-ken' => 6,
            'Fukushima-ken' => 7,
            'Ibaragi-ken' => 8,
            'Tochigi-ken' => 9,
            'Gunma-ken' => 10,
            'Saitama-ken' => 11,
            'Chiba-ken' => 12,
            'Tokyo-to' => 13,
            'Kanagawa-ken' => 14,
            'Nigata-ken' => 15,
            'Toyama-ken' => 16,
            'Ishikawa-ken' => 17,
            'Fukui-ken' => 18,
            'Yamanashi-ken' => 19,
            'Nagano-ken' => 20,
            'Gifu-ken' => 21,
            'Shizuoka-ken' => 22,
            'Aichi-ken' => 23,
            'Mie-ken' => 24,
            'Shiga-ken' => 25,
            'Kyoto-fu' => 26,
            'Osaka-fu' => 27,
            'Hyogo-ken' => 28,
            'Nara-ken' => 29,
            'Wakayama-ken' => 30,
            'Tottori-ken' => 31,
            'Shimane-ken' => 32,
            'Okayama-ken' => 33,
            'Hiroshima-ken' => 34,
            'Yamaguchi-ken' => 35,
            'Tokushima-ken' => 36,
            'Kagawa-ken' => 37,
            'Ehime-ken' => 38,
            'Kochi-ken' => 39,
            'Fukuoka-ken' => 40,
            'Saga-ken' => 41,
            'Nagasaki-ken' => 42,
            'Kumamoto-ken' => 43,
            'Oita-ken' => 44,
            'Miyazaki-ken' => 45,
            'Kagoshima-ken' => 46,
            'Okinawa-ken' => 47,
            '北海道' => 1,
            '青森県' => 2,
            '岩手県' => 3,
            '宮城県' => 4,
            '秋田県' => 5,
            '山形県' => 6,
            '福島県' => 7,
            '茨城県' => 8,
            '栃木県' => 9,
            '群馬県' => 10,
            '埼玉県' => 11,
            '千葉県' => 12,
            '東京都' => 13,
            '神奈川県' => 14,
            '新潟県' => 15,
            '富山県' => 16,
            '石川県' => 17,
            '福井県' => 18,
            '山梨県' => 19,
            '長野県' => 20,
            '岐阜県' => 21,
            '静岡県' => 22,
            '愛知県' => 23,
            '三重県' => 24,
            '滋賀県' => 25,
            '京都府' => 26,
            '大阪府' => 27,
            '兵庫県' => 28,
            '奈良県' => 29,
            '和歌山県' => 30,
            '鳥取県' => 31,
            '島根県' => 32,
            '岡山県' => 33,
            '広島県' => 34,
            '山口県' => 35,
            '徳島県' => 36,
            '香川県' => 37,
            '愛媛県' => 38,
            '高知県' => 39,
            '福岡県' => 40,
            '佐賀県' => 41,
            '長崎県' => 42,
            '熊本県' => 43,
            '大分県' => 44,
            '宮崎県' => 45,
            '鹿児島県' => 46,
            '沖縄県' => 47,
            '北海' => 1,
            '青森' => 2,
            '岩手' => 3,
            '宮城' => 4,
            '秋田' => 5,
            '山形' => 6,
            '福島' => 7,
            '茨城' => 8,
            '栃木' => 9,
            '群馬' => 10,
            '埼玉' => 11,
            '千葉' => 12,
            '東京' => 13,
            '神奈川' => 14,
            '新潟' => 15,
            '富山' => 16,
            '石川' => 17,
            '福井' => 18,
            '山梨' => 19,
            '長野' => 20,
            '岐阜' => 21,
            '静岡' => 22,
            '愛知' => 23,
            '三重' => 24,
            '滋賀' => 25,
            '京都' => 26,
            '大阪' => 27,
            '兵庫' => 28,
            '奈良' => 29,
            '和歌山' => 30,
            '鳥取' => 31,
            '島根' => 32,
            '岡山' => 33,
            '広島' => 34,
            '山口' => 35,
            '徳島' => 36,
            '香川' => 37,
            '愛媛' => 38,
            '高知' => 39,
            '福岡' => 40,
            '佐賀' => 41,
            '長崎' => 42,
            '熊本' => 43,
            '大分' => 44,
            '宮崎' => 45,
            '鹿児島' => 46,
            '沖縄' => 47
        ];

        return $prefs[$StateOrRegion];
    }

    public static function log($message): void
    {
        GC_Utils_Ex::gfPrintLog($message, self::LOG_FILE);
    }

    public static function getTemplatePath(string $name): string
    {
        $path = self::TEMPLATE_REALDIR.'/'.$name.'.tpl';
        if (!file_exists($path)) {

            return self::TEMPLATE_REALDIR.'/dummy.tpl';
        }

        return $path;
    }

    public static function generatePayloadByCheckout(string $key = ''): array
    {
        return [
            'webCheckoutDetails' => [
                'checkoutReviewReturnUrl' => HTTPS_URL.'shopping/amazonpay.php?mode=processCheckout&cartKey='.$key
            ],
            'paymentDetails' => [
                'allowOvercharge' => true
            ],
            'storeId' => getenv('AMAZONPAY_STORE_ID'),
            'scopes' => ['name', 'email', 'phoneNumber', 'billingAddress'],
            // イレギュラーな都道府県名を禁止したい場合は以下を有効にする
            // 'deliverySpecifications' => [
            //     'addressRestrictions' => [
            //         'type' => 'Allowed',
            //         'restrictions' => [
            //             'JP' => [
            //                 'statesOrRegions' => ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'],
            //             ],
            //         ],
            //     ],
            // ],
        ];
    }
    /**
     * @param int[] $cartKeys
     */
    public static function generatePayloadByCart(array $cartKeys): array
    {
        $payload = [];
        foreach ($cartKeys as $key) {
            $payload[$key] = self::generatePayloadByCheckout($key);
        }

        return $payload;
    }

    public static function generatePayloadByLogin(string $signInReturnUrl): array
    {
        return [
            'signInReturnUrl' => $signInReturnUrl,
            'storeId' => getenv('AMAZONPAY_STORE_ID'),
            'signInScopes' => [
                'name', 'email', 'billingAddress', 'shippingAddress', 'phoneNumber'
            ]
        ];
    }

    public function generateSignatureByCart(array $payloads): array
    {
        $signature = [];
        foreach ($payloads as $key => $payload) {
            $signature[$key] = $this->generateSignature($payload);
        }

        return $signature;
    }

    public function getBuyer(string $buyerToken): array
    {
        $client = new Client($this->amazonpayConfig);
        $result = $client->getBuyer($buyerToken);

        return json_decode($result['response'], true);
    }

    public function findCustomer(string $buyer_id, string $email): ?array
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        return $objQuery->getRow('*', 'dtb_customer', '(amazonpay_buyer_id = ? OR email = ?) AND del_flg = 0 ', [$buyer_id, $email]);
    }

    public function registerCustomer(array $buyer, string $buyer_id): void
    {
        $objQuery = SC_Query_Ex::getSingletonInstance();
        $arrCustomer = [];
        $arrCustomer['name01'] = $buyer['name'];
        $arrCustomer['name02'] = '　';
        $arrCustomer['email'] = $buyer['email'];
        if (array_key_exists('billingAddress', $buyer)) {
            list($arrCustomer['zip01'], $arrCustomer['zip02']) = explode('-', $buyer['billingAddress']['postalCode']);
            $arrCustomer['pref'] = self::convertToPrefId($buyer['billingAddress']['stateOrRegion']);
            $arrCustomer['addr01'] = $buyer['billingAddress']['city'].$buyer['billingAddress']['addressLine1'];
            $arrCustomer['addr02'] = $buyer['billingAddress']['addressLine2'];
            $arrCustomer['company_name'] = $buyer['billingAddress']['addressLine3'];
            if (array_key_exists('phoneNumber', $buyer['billingAddress']) && !empty($buyer['billingAddress']['phoneNumber'])) {
                $phone = str_replace(self::HYPHEN, '', $buyer['billingAddress']['phoneNumber']);
                $plus = '';
                list($arrCustomer['tel01'], $arrCustomer['tel02'], $arrCustomer['tel03'], $plus) = str_split($phone, 4);
                $arrCustomer['tel03'] .= $plus;
            }
        }
        $arrCustomer['amazonpay_buyer_id'] = $buyer_id;
        $arrCustomer['secret_key'] = SC_Helper_Customer_Ex::sfGetUniqSecretKey();
        $arrCustomer['status'] = 2;

        $existCustomer = $this->findCustomer($buyer_id, $arrCustomer['email']);

        if (empty($existCustomer)) {
            $arrCustomer['customer_id'] = SC_Helper_Customer_Ex::sfEditCustomerData($arrCustomer);
        } else {
            $arrCustomer['customer_id'] = SC_Helper_Customer_Ex::sfEditCustomerData($arrCustomer, $existCustomer['customer_id']);
        }

        $arrOtherDeliv = $objQuery->getRow('*', 'dtb_other_deliv', 'customer_id = ? AND amazonpay_shipping_address_flg = ?', [$arrCustomer['customer_id'], 1]);

        if (array_key_exists('shippingAddress', $buyer)) {
            $arrOtherDeliv['name01'] = $buyer['shippingAddress']['name'];
            $arrOtherDeliv['name02'] = '';

            list($arrOtherDeliv['zip01'], $arrOtherDeliv['zip02']) = explode('-', $buyer['shippingAddress']['postalCode']);
            $arrOtherDeliv['pref'] = self::convertToPrefId($buyer['shippingAddress']['stateOrRegion']);
            $arrOtherDeliv['addr01'] = $buyer['shippingAddress']['city'].$buyer['shippingAddress']['addressLine1'];
            $arrOtherDeliv['addr02'] = $buyer['shippingAddress']['addressLine2'];
            $arrOtherDeliv['company_name'] = $buyer['shippingAddress']['addressLine3'];
            if (array_key_exists('phoneNumber', $buyer['shippingAddress']) && !empty($buyer['shippingAddress']['phoneNumber'])) {
                $phone = str_replace(self::HYPHEN, '', $buyer['shippingAddress']['phoneNumber']);
                $plus = '';
                list($arrOtherDeliv['tel01'], $arrOtherDeliv['tel02'], $arrOtherDeliv['tel03'], $plus) = str_split($phone, 4);
                $arrOtherDeliv['tel03'] .= $plus;
            }
        }
        $arrOtherDeliv['amazonpay_shipping_address_flg'] = 1;
        $arrOtherDeliv['customer_id'] = $arrCustomer['customer_id'];

        $objAddress = new SC_Helper_Address_Ex();
        $objAddress->registAddress($arrOtherDeliv);
    }
}
