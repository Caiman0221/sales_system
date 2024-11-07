<?php 
    function debug($data, $isDie = true, $html = true) {
        if ($html) echo '<pre>' . var_export($data, return: true) . '</pre>';
        else echo "\n" . var_export($data, true) . "\n";
        if ($isDie) {
            die();
        }
    }

    require 'vendor/autoload.php';
    require '../config/config.php';

    // config.php
    // <?php
    // const spreadsheetId = 'id таблицы';
    // const api_url = 'ссылка на api';
    

    use Google\Client;
    use Google\Service\Sheets;

    final class google {
        private $client;
        private $service;

        public function __construct() {
            $this->client = new Client();
            $this->client->setApplicationName('Google Sheets API PHP');
            $this->client->setScopes([Sheets::SPREADSHEETS]);
            $this->client->setAuthConfig('credentials.json');
            $this->client->setAccessType('offline');

            $this->service = $this->getGoogleSheetService();
        }

        public function getGoogleSheetService() {
            $redirectUri = 'http://localhost:8000'; // Укажите ваш Redirect URI для OAuth
            $this->client->setRedirectUri($redirectUri);
        
            $tokenPath = 'token.json';
            
            // Если токен уже сохранен, загружаем его
            if (file_exists($tokenPath)) {
                $accessToken = json_decode(file_get_contents($tokenPath), true);
                $this->client->setAccessToken($accessToken);
            }
        
            // Если токен истек или отсутствует, запросим новый
            if ($this->client->isAccessTokenExpired()) {
                // Если есть refresh token, обновляем доступ
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                } else {
                    // Если refresh token нет, перенаправляем пользователя для получения auth code
                    if (!isset($_GET['code'])) {
                        // Создаем URL для авторизации
                        $authUrl = $this->client->createAuthUrl();
                        header('Location: ' . filter_var($authUrl, FILTER_SANITIZE_URL));
                        exit();
                    } else {
                        // Получаем auth code из URL после редиректа
                        $authCode = $_GET['code'];
                        
                        // Обмениваем auth code на access token
                        $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
                        $this->client->setAccessToken($accessToken);
        
                        // Сохраняем токен для будущих обращений
                        if (!file_exists(dirname($tokenPath))) {
                            mkdir(dirname($tokenPath), 0700, true);
                        }
                        file_put_contents($tokenPath, json_encode($this->client->getAccessToken()));
                    }
                }
            }
        
            return new Sheets($this->client);
        }
        public function namedArray(array $arr = []) : array{
            foreach ($arr as $key => $value) {
                $temp = [];
                if (!empty($value[0])) $temp['id'] = $value[0];
                if (!empty($value[1])) $temp['price'] = $value[1];
                if (!empty($value[2])) $temp['discount'] = $value[2];
                if (!empty($value[3])) $temp['doUpdate'] = $value[3];
                $arr[$key] = $temp;
            }
            return $arr;
        }
        public function unNamedArray(array $arr = []) : array{
            foreach ($arr as $key => $data) {
                $arr[$key] = [
                    $data['id'],
                    $data['price'],
                    $data['discount']
                ];
            }
            return $arr;
        }
        public function readGoogleSheet($spreadsheetId, $range) {
            // Получаем данные из указанного диапазона
            $response = $this->service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues();
            return (!empty($values) ? $values : []);
        }
        public function getStoreData($url) {
            $response = file_get_contents($url);
            $response = json_decode($response, true);
            $response = $response['RESPONSE'];
            $result = $this->unNamedArray($response);

            return $result;
        }
        public function updateGoogleSheet($spreadsheetId, $range, $values) {
            // Получаем текущие данные
            $existingValues = $this->readGoogleSheet($spreadsheetId, $range);

            // Объединяем текущие и новые данные, исключая дубликаты
            $newValues = array_merge($existingValues, array_diff_key($values, array_flip(array_column($existingValues, 0))));

            // Записываем в Google Sheet
            $body = new Sheets\ValueRange(['values' => $newValues]);
            $params = ['valueInputOption' => 'RAW'];

            $this->service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
        }
        public function getUpdatedRows($spreadsheetId) {
            $range = 'Sheet1!A2:Z';
            $values = $this->readGoogleSheet($spreadsheetId, $range);
            $values = $this->namedArray($values);
            
            // Ищем столбец doUpdate и определяем столбцы для отправки
            $send = [];
            $emptyValues = [];
            foreach ($values as $key => $value) {
                // debug($value, false);
                if (array_key_exists('doUpdate', $value)) {
                    // debug($value, false);
                    array_push($send, $value);
                    unset($send[sizeof($send) - 1]['doUpdate']);
                }
                array_push($emptyValues, ['']);
            }
            
            // очищаем стобец 'doUpdate'
            $range = 'Sheet1!D2:D';

            $body = new Sheets\ValueRange(['values' => $emptyValues]);
            $params = ['valueInputOption' => 'RAW'];
            $this->service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);

            // выводим изменненные данные
            debug($send);
        }
    }

    $google = new google;
    $range = 'Sheet1!A1:Z';
    // получение данных из гугл таблицы
    // $google->readGoogleSheet(spreadsheetId, $range); 

    // получение данных из api
    $data = $google->getStoreData(api_url);

    debug($data);

    // обновление данных в гугл таблице
    // $google->updateGoogleSheet(spreadsheetId, $range, $data);

    // посмотреть обновляемые данные
    $google->getUpdatedRows(spreadsheetId);
