<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class ChaparTracking extends Module {

    public function __construct() {
        $this->name                   = 'chapartracking';
        $this->tab                    = 'shipping_logistics';
        $this->version                = '1.0.0';
        $this->author                 = 'rahkarnet.com';
        $this->need_instance          = 0;
        $this->ps_versions_compliancy = [
            'min' => '1.7.0.0', 
            'max' => _PS_VERSION_
        ];
        $this->bootstrap              = true;
        parent::__construct();
    
    	$this->icon = 'modules/'.$this->name.'/logo.png';
        $this->displayName      = $this->l('صدور بارنامه چاپار');
        $this->description      = $this->l('همگام سازی چاپار و پرستاشاپ - به کمک این افزونه به صورت خودکار با تغییر وضعیت سفارشات بارنامه چاپار صادر میشود.');
        $this->confirmUninstall = $this->l('آیا مطمئن هستید که می‌خواهید حذف کنید؟');
    }

    public function install() {
        return parent::install()
            && $this->registerHook('actionOrderStatusPostUpdate')
            && $this->registerHook('displayAdminOrderMain')
            && $this->installDb()
    		&& $this->initConfig();
    }
    private function initConfig() {
        // ذخیره مقادیر پیش‌فرض جدید
        Configuration::updateValue('CHAPARTRACKING_PERSON', 'نام جدید فروشگاه');
        Configuration::updateValue('CHAPARTRACKING_CITY_NO', '12345');
        Configuration::updateValue('CHAPARTRACKING_TELEPHONE', '09123456789');
        Configuration::updateValue('CHAPARTRACKING_MOBILE', '09123456789');
        Configuration::updateValue('CHAPARTRACKING_EMAIL', 'info@newshop.com');
        Configuration::updateValue('CHAPARTRACKING_ADDRESS', 'آدرس جدید');
        Configuration::updateValue('CHAPARTRACKING_USERNAME', 'new.username');
        Configuration::updateValue('CHAPARTRACKING_PASSWORD', 'newpassword123');
        Configuration::updateValue('CHAPARTRACKING_STATUS_ID', 21); // پیش فرض جدید
        Configuration::updateValue('CHAPARTRACKING_CARRIER_ID', 12); // پیش فرض جدید
        return true;
    }

    public function uninstall() {
            // حذف تنظیمات هنگام حذف ماژول
        Configuration::deleteByName('CHAPARTRACKING_PERSON');
        Configuration::deleteByName('CHAPARTRACKING_CITY_NO');
        Configuration::deleteByName('CHAPARTRACKING_TELEPHONE');
        Configuration::deleteByName('CHAPARTRACKING_MOBILE');
        Configuration::deleteByName('CHAPARTRACKING_EMAIL');
        Configuration::deleteByName('CHAPARTRACKING_ADDRESS');
        Configuration::deleteByName('CHAPARTRACKING_USERNAME');
        Configuration::deleteByName('CHAPARTRACKING_PASSWORD');
        Configuration::deleteByName('CHAPARTRACKING_STATUS_ID');
        Configuration::deleteByName('CHAPARTRACKING_CARRIER_ID');
        return parent::uninstall() && $this->uninstallDb();
    }

    private function uninstallDb() {
        $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'chapar_tracking`';
        return Db::getInstance()->execute($sql);
    }

    public function reset() {
        if (!parent::reset()) {
            return false;
        }
        return $this->uninstallDb() && $this->installDb();
    }

    private function installDb() {
        $sql = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'chapar_tracking` (
            `id_tracking` INT(11) NOT NULL AUTO_INCREMENT,
            `id_order` INT(11) NOT NULL,
            `chapar_tracking` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`id_tracking`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
        return Db::getInstance()->execute($sql);
    }

    public function hookActionOrderStatusPostUpdate($params) {
        $order          = new Order((int)$params['id_order']);
        $newOrderStatus = $params['newOrderStatus'];

        // دریافت تنظیمات وضعیت سفارش و روش حمل و نقل
    $configuredStatusId = Configuration::get('CHAPARTRACKING_STATUS_ID');
    $configuredCarrierId = Configuration::get('CHAPARTRACKING_CARRIER_ID');
    
        if ($newOrderStatus->id == $configuredStatusId && $order->id_carrier == $configuredCarrierId) {
            $this->sendCurlRequest($order);
        }
    }

    private function sendCurlRequest($order) {
    $person   = Configuration::get('CHAPARTRACKING_PERSON');
    $cityNo   = Configuration::get('CHAPARTRACKING_CITY_NO');
    $telephone = Configuration::get('CHAPARTRACKING_TELEPHONE');
    $mobile    = Configuration::get('CHAPARTRACKING_MOBILE');
    $email     = Configuration::get('CHAPARTRACKING_EMAIL');
    $address   = Configuration::get('CHAPARTRACKING_ADDRESS');
    $username  = Configuration::get('CHAPARTRACKING_USERNAME');
    $password  = Configuration::get('CHAPARTRACKING_PASSWORD');
        $curl     = curl_init();
        $customer = new Customer($order->id_customer);
        $address  = new Address($order->id_address_delivery);
        $data     = array(
            'input' => '{"user":{"username":"' . $username . '","password":"' . $password . '"},"bulk":[{"cn":{"reference":"' . $order->reference . '","date":"' . $order->date_add . '","assinged_pieces":"1","service":"35","value":"' . $order->total_paid . '","inv_value":0,"payment_term":"0","weight":"1","content":"","note":""},"sender":{"person":"' . $person . '","city_no":"' . $cityNo . '","telephone":"' . $telephone . '","mobile":"' . $mobile . '","email":"' . $email . '","address":"' . $address . '"},"receiver":{"person":"' . $order->getCustomer()->firstname . ' ' . $order->getCustomer()->lastname . '","company":"","city_no":"' . $cityNo . '","telephone":"' . $address->phone_mobile . '","mobile":"' . $address->phone_mobile . '","email":"' . $order->getCustomer()->email . '","address":"' . $address->address1 . ' ' . $address->address2 . '","postcode":"' . $address->postcode . '"}}]}'
        );
        curl_setopt_array($curl, array(
            CURLOPT_URL            => "https://api.krch.ir/v1/bulk_import",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS     => $data,
            CURLOPT_HTTPHEADER     => array(
                "app-auth: aW9zX2N1c3RvbWVyX2FwcDpUUFhAMjAxNg==",
                "content-type: multipart/form-data;charset=utf-8",
                'Cookie: PHPSESSID=0uo8kk84t6is4h3u11qbuuh8d6'
            ) ,
        ));
        $response              = curl_exec($curl);
        curl_close($curl);
        $result   = json_decode($response, true);
        $tracking = $result["objects"]["result"][0]["tracking"];
        $this->saveChaparTracking($order->id, $tracking);
    }

    private function saveChaparTracking($orderId, $tracking) {
        Db::getInstance()->insert('chapar_tracking', array(
            'id_order' => (int)$orderId,
            'chapar_tracking' => pSQL($tracking)
        ));
    }

    public function hookDisplayAdminOrderMain($params) {
        if (!isset($params['id_order']) || !is_numeric($params['id_order'])) {
            return '';
        }
        $orderId      = (int)$params['id_order'];
        $sql          = 'SELECT `chapar_tracking` FROM `' . _DB_PREFIX_ . 'chapar_tracking` WHERE `id_order` = ' . $orderId;
        $trackingCode = Db::getInstance()->getValue($sql);

        if ($trackingCode) {
            $context      = Context::getContext();
            $context->smarty->assign('trackingCode', $trackingCode);
            return $this->display(__FILE__, 'views/templates/admin/tracking_info.tpl');
        }
        return '';
    }
    public function getContent() {
        $output = '';
        if (Tools::isSubmit('submitChaparTrackingConfig')) {
            // ذخیره تنظیمات
            Configuration::updateValue('CHAPARTRACKING_PERSON', Tools::getValue('CHAPARTRACKING_PERSON'));
            Configuration::updateValue('CHAPARTRACKING_CITY_NO', Tools::getValue('CHAPARTRACKING_CITY_NO'));
            Configuration::updateValue('CHAPARTRACKING_TELEPHONE', Tools::getValue('CHAPARTRACKING_TELEPHONE'));
            Configuration::updateValue('CHAPARTRACKING_MOBILE', Tools::getValue('CHAPARTRACKING_MOBILE'));
            Configuration::updateValue('CHAPARTRACKING_EMAIL', Tools::getValue('CHAPARTRACKING_EMAIL'));
            Configuration::updateValue('CHAPARTRACKING_ADDRESS', Tools::getValue('CHAPARTRACKING_ADDRESS'));
            Configuration::updateValue('CHAPARTRACKING_USERNAME', Tools::getValue('CHAPARTRACKING_USERNAME'));
            Configuration::updateValue('CHAPARTRACKING_PASSWORD', Tools::getValue('CHAPARTRACKING_PASSWORD'));
            Configuration::updateValue('CHAPARTRACKING_STATUS_ID', Tools::getValue('CHAPARTRACKING_STATUS_ID'));
            Configuration::updateValue('CHAPARTRACKING_CARRIER_ID', Tools::getValue('CHAPARTRACKING_CARRIER_ID'));

            $output .= $this->displayConfirmation($this->l('Settings updated'));
        }

        return $output . $this->renderForm();
    }

    public function renderForm() {
        $statuses = OrderState::getOrderStates((int)$this->context->language->id);
        $carriers = Carrier::getCarriers($this->context->language->id, true);

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('تنظیمات ماژول صدور بارنامه چاپار'),
                ],
                'description' => html_entity_decode($this->l('توضیحات کلی در مورد تنظیمات ماژول و نحوه استفاده از آن: برای اطلاعات بیشتر به <a href="https://example.com" target="_blank">این لینک</a> مراجعه کنید.')),
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('نام فروشگاه'),
                        'name' => 'CHAPARTRACKING_PERSON',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('آیدی شهر فروشگاه'),
                        'name' => 'CHAPARTRACKING_CITY_NO',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('تلفن فروشگاه'),
                        'name' => 'CHAPARTRACKING_TELEPHONE',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('موبایل فروشگاه'),
                        'name' => 'CHAPARTRACKING_MOBILE',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('ایمیل فروشگاه'),
                        'name' => 'CHAPARTRACKING_EMAIL',
                        'required' => true,
                    ],
                    [
                        'type' => 'textarea',
                        'label' => $this->l('آدرس فروشگاه'),
                        'name' => 'CHAPARTRACKING_ADDRESS',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('نام کاربری چاپار'),
                        'name' => 'CHAPARTRACKING_USERNAME',
                        'required' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('رمز ورود چاپار'),
                        'name' => 'CHAPARTRACKING_PASSWORD',
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('وضعیت سفارش'),
                        'name' => 'CHAPARTRACKING_STATUS_ID',
                        'options' => [
                            'query' => $statuses,
                            'id' => 'id_order_state',
                            'name' => 'name'
                        ],
                        'required' => true,
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('روش پستی'),
                        'name' => 'CHAPARTRACKING_CARRIER_ID',
                        'options' => [
                            'query' => $carriers,
                            'id' => 'id_carrier',
                            'name' => 'name'
                        ],
                        'required' => true,
                    ]
                ],
                'submit' => [
                    'title' => $this->l('ذخیره'),
                ]
            ]
        ];

        $helper = new HelperForm();
        $helper->fields_value['CHAPARTRACKING_PERSON'] = Configuration::get('CHAPARTRACKING_PERSON');
        $helper->fields_value['CHAPARTRACKING_CITY_NO'] = Configuration::get('CHAPARTRACKING_CITY_NO');
        $helper->fields_value['CHAPARTRACKING_TELEPHONE'] = Configuration::get('CHAPARTRACKING_TELEPHONE');
        $helper->fields_value['CHAPARTRACKING_MOBILE'] = Configuration::get('CHAPARTRACKING_MOBILE');
        $helper->fields_value['CHAPARTRACKING_EMAIL'] = Configuration::get('CHAPARTRACKING_EMAIL');
        $helper->fields_value['CHAPARTRACKING_ADDRESS'] = Configuration::get('CHAPARTRACKING_ADDRESS');
        $helper->fields_value['CHAPARTRACKING_USERNAME'] = Configuration::get('CHAPARTRACKING_USERNAME');
        $helper->fields_value['CHAPARTRACKING_PASSWORD'] = Configuration::get('CHAPARTRACKING_PASSWORD');
        $helper->fields_value['CHAPARTRACKING_STATUS_ID'] = Configuration::get('CHAPARTRACKING_STATUS_ID');
        $helper->fields_value['CHAPARTRACKING_CARRIER_ID'] = Configuration::get('CHAPARTRACKING_CARRIER_ID');

        return $helper->generateForm([$fields_form]);
    }
}
