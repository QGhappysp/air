<?php

namespace app\admin\command;

use app\admin\model\ApiKey;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Cards extends Command
{
    protected function configure()
    {
        $this->setName('cards')->setDescription('cards');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('This is a test command');
    }

    public function run(Input $input, Output $output)
    {
        $page = 0;
        $cards = $this->getAllCards($page);
        if (count($cards->items) > 0) {
            $cardsArray = $cards->items;
            while ($cards->has_more) {
                $page++;
                $cards = $this->getAllCards($page);
                $cardsArray = array_merge($cardsArray, $cards->items);
            }
            $cardHolders = $this->getCardHolders()->items;
            $cardHoldersArray = array();
            foreach ($cardHolders as $cardHolder) {
                $cardHoldersArray[$cardHolder->cardholder_id] = $cardHolder->individual->name->name_on_card ?? null;
            }
            foreach ($cardsArray as $card) {
                $c = \app\admin\model\Cards::where('card_id','=', $card->card_id)->find();
                if (empty($c)) {
                    $c = new \app\admin\model\Cards();
                }
                $cardInfo = $this->getRemaining($card->card_id);
                $c->remaining = $cardInfo->limits[0]->remaining;
                $c->amount = $cardInfo->limits[0]->amount;
                $c->card_id = $card->card_id;
                $c->card_number = $card->card_number;
                $c->created_at = date('Y-m-d H:i:s', strtotime($card->created_at));
                $c->card_status = $card->card_status;
                $c->brand = $card->brand;
                $c->nick_name = $card->nick_name ?? null;
                $c->cardholder_id = $card->cardholder_id;
                $c->cardholder_name = $cardHoldersArray[$card->cardholder_id] ?? null;
                $c->save();
            }
        }
    }

    protected function getAllCards($page,$limit = 10)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards?page_size='.$limit.'&page_num='.$page;

        $headers = [
            'Authorization: Bearer ' . $this->getAirToken()
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }


    protected function getReport($limit = 20,$page=0, $startTime = null, $endTime = null, $cardholderId = null, $nickname = null, $card_id = null)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/transactions?page_size='.$limit.'&page_num='.$page;
        if ($startTime&&$endTime) {
            $url .= '&from_created_at='.$startTime.'&to_created_at='.$endTime;
        }
        if ($cardholderId) {
            $url .= '&cardholder_id='.$cardholderId;
        }
        if ($nickname) {
            $url .= '&nick_name='.$nickname;
        }
        if ($card_id) {
            $url .= '&card_id='.$card_id;
        }

        $airToken = $this->getAirToken();

        $headers = [
            'Authorization: Bearer ' . $airToken
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);

        if(curl_errno($ch)){
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }

    protected function getAirToken()
    {
        $airToken = Cache::get('airApiToken');
        if (!$airToken) {
            $airToken = $this->airApiLogin();
            if ($airToken) {
                Cache::set('airApiToken',$airToken,1200);
            }
        }
        return $airToken;
    }

    protected function airApiLogin()
    {
        $apiKey = ApiKey::find();
        $url = Backend::AIR_API_URL . "api/v1/authentication/login";
        $headers = array(
            "Content-Type: application/json",
            "x-client-id: " . $apiKey->api_client_id,
            "x-api-key: " . $apiKey->api_key
        );
        $data = '{ }';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        if ($response === false) {
            echo "cURL Error: " . curl_error($ch);
        } else {
//            echo $response;
        }

        curl_close($ch);

        $result = json_decode($response);

        return $result->token;
    }

    protected function getCardDetail($ids)
    {
        $airToken = $this->getAirToken();
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $ids . '/details';
        $headers = array(
            "Authorization: Bearer " . $airToken,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        // 检查是否有错误发生
        if(curl_errno($ch)){
            echo 'cURL 错误: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }

    protected function getRemaining($card_id)
    {
        $airToken = $this->getAirToken();
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $card_id . '/limits';
        $headers = array(
            "Authorization: Bearer " . $airToken,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        // 检查是否有错误发生
        if(curl_errno($ch)){
            echo 'cURL 错误: ' . curl_error($ch);
        }
        curl_close($ch);
        $data = json_decode($response);

        return $data;

//        var_dump($data);exit;

//        return $data->limits[0]->remaining;
    }

    protected function getCardHolders()
    {
        $airToken = $this->getAirToken();
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cardholders?cardholder_status=READY';
        $headers = array(
            "Authorization: Bearer " . $airToken,
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        // 检查是否有错误发生
        if(curl_errno($ch)){
            echo 'cURL 错误: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }

}