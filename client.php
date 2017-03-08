<?php

    namespace Epayco;

    require "vendor/autoload.php";
    // require_once "errors.php";

    /**
     * Client conection api epayco
     */
    class Client
    {
        const BASE_URL = "https://api.secure.payco.co";
        const BASE_URL_SECURE = "https://secure.payco.co";
        const IV = "0000000000000000";
        const LENGUAGE = "php";

        /**
         * Request api epayco
         * @param  String $method      method petition
         * @param  String $url         url request api epayco
         * @param  String $api_key     public key commerce
         * @param  Object $data        data petition
         * @param  String $private_key private key commerce
         * @param  String $test        type petition production or testing
         * @param  Boolean $switch     type api petition
         * @return Object
         */
        public function request(
            $method,
            $url,
            $api_key,
            $data = null,
            $private_key,
            $test,
            $switch,
            $lang
        ) {
          $dataSet = null;
          if ($switch && is_array($data)) {
              $util = new Util();
              $data = $util->setKeys($data);
          }
            $headers= array("Content-Type" => "application/json", "Accept" => "application/json", "type" => "sdk");
            try {
                $options = array(
                    'auth' => new \Requests_Auth_Basic(array($api_key, ''))
                );
                if ($method == "GET") {
                    if ($switch) {
                        $addData = array(
                              "public_key" => $api_key,
                              "i" => base64_encode(Client::IV),
                              "transactionID" => $data,
                              "lenguaje" => Client::LENGUAGE
                        );
                        $endData = $encryptData ? array_merge($encryptData, $addData) : $addData;
                        $url_params = is_array($endData) ? '?' . http_build_query($endData) : '';
                        $response = \Requests::get(Client::BASE_URL_SECURE . $url . $url_params, $headers, $options);
                    } else {
                        $url_params = is_array($data) ? '?' . http_build_query($data) : '';
                        $response = \Requests::get(Client::BASE_URL . $url . $url_params, $headers, $options);
                    }
                } elseif ($method == "POST") {
                    if ($switch) {
                        $aes = new PaycoAes($private_key, Client::IV, $lang);
                        $encryptData = null;
                        $encryptData = $aes->encryptArray($data);
                        $adddata = array(
                            "public_key" => $api_key,
                            "i" => base64_encode(Client::IV),
                            "enpruebas" => $test,
                            "lenguaje" => Client::LENGUAGE,
                            "p" => "",
                        );
                        $enddata = array_merge($encryptData, $adddata);
                        $url_params = is_array($enddata) ? '?' . http_build_query($enddata) : '';
                        $response = \Requests::post(Client::BASE_URL_SECURE . $url, $headers, json_encode($enddata), $options);
                    } else {
                        $response = \Requests::post(Client::BASE_URL . $url, $headers, json_encode($data), $options);
                    }
                } elseif ($method == "PATCH") {
                    $response = \Requests::patch(Client::BASE_URL . $url, $headers, json_encode($data), $options);
                } elseif ($method == "DELETE") {
                    $response = \Requests::delete(Client::BASE_URL . $url, $headers, $options);
                }
            } catch (\Exception $e) {
                throw new ErrorException($lang, 101);
            }
            if ($response->status_code >= 200 && $response->status_code <= 206) {
                if ($method == "DELETE") {
                    return $response->status_code == 204 || $response->status_code == 200;
                }
                return json_decode($response->body);
            }
            if ($response->status_code == 400) {
                $code = 0;
                $message = "";
                try {
                    $error = (array) json_decode($response->body)->errors[0];
                    $code = key($error);
                    $message = current($error);
                } catch (\Exception $e) {
                    throw new ErrorException($lang, 102);
                }
                throw new ErrorException($lang, 103);
            }
            if ($response->status_code == 401) {
                throw new ErrorException($lang, 104);
            }
            if ($response->status_code == 404) {
                throw new ErrorException($lang, 105);
            }
            if ($response->status_code == 403) {
                throw new ErrorException($lang, 106);
            }
            if ($response->status_code == 405) {
                throw new ErrorException($lang, 107);
            }
            throw new ErrorException($lang, 102);
        }
    }

    class Util
    {
        public function setKeys($array)
        {
          $aux = array();
          $file = dirname(__FILE__). "/utils/key_lang.json";
          $values = json_decode(file_get_contents($file), true);
          foreach ($array as $key => $value) {
              if (array_key_exists($key, $values)) {
                  $aux[$values[$key]] = $value;
              } else {
                  $aux[$key] = $value;
              }
          }
          return $aux;
        }
    }


    /**
     * Epayco library encrypt based in AES
     */
    class PaycoAes
    {
        private $_cipher = MCRYPT_RIJNDAEL_128;
        private $_mode = MCRYPT_MODE_CBC;
        private $_key;
        private $_initializationVectorSize;

        public function __construct($key, $iv, $lang)
        {
            $this->_key = $key;
            $this->iv=$iv;
            $this->_initializationVectorSize = mcrypt_get_iv_size($this->_cipher, $this->_mode);

            if (strlen($key) > ($keyMaxLength = mcrypt_get_key_size($this->_cipher, $this->_mode))) {
                throw new ErrorException($lang, 108);
            }
        }


        public function encrypt($data)
        {
            $encript= mcrypt_encrypt(
                $this->_cipher,
                $this->_key,
                $this->addpadPKCS7($data, $this->_initializationVectorSize),
                $this->_mode,
                $this->iv
            );
            return base64_encode($encript);
        }

        public function decrypt($encryptedData)
        {
            $data =  @mcrypt_decrypt(
                $this->_cipher,
                $this->_key,
                base64_decode($encryptedData),
                $this->_mode,
                $this->iv
            );
            return $this->unpadPKCS7($data);
        }

        private function addpadPKCS7($data, $block_size)
        {
            $pad = $block_size - (strlen($data) % $block_size);
            $data .= str_repeat(chr($pad), $pad);
            return $data;
        }
        private function unpadPKCS7($data)
        {
            $last = substr($data, -1);
            return substr($data, 0, strlen($data) - ord($last));
        }

        public function encryptArray($arrdata)
        {
            $aux = array();
            foreach ($arrdata as $key => $value) {
                $aux[$key] = $this->encrypt($value);
            }
            return $aux;
        }
    }