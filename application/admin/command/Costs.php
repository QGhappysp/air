<?php

namespace app\admin\command;

use app\admin\model\ApiKey;
use app\admin\model\Costs as CostsModel;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Costs extends Command
{
    protected function configure()
    {
        $this->setName('costs')->setDescription('costs');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('This is a test command');
    }

    public function run(Input $input, Output $output)
    {
        $maxTime = CostsModel::order('transaction_date', 'desc')->find();
        $firstDayOfLastMonth = strtotime($maxTime->transaction_date)+1;
        $lastDayOfLastMonth = time();
        $startTime = date('c', $firstDayOfLastMonth);
        $endTime = date('c', $lastDayOfLastMonth);
        $startTime = urlencode($startTime);
        $endTime = urlencode($endTime);

        $page = 0;
        $monthlyReport = $this->getReport(50, $page, $startTime, $endTime);
        $allBalance = $monthlyReport->items;
        while ($monthlyReport->has_more == true) {
            $page++;
            $monthlyReport = $this->getReport(50, $page, $startTime, $endTime);
            $allBalance = array_merge($allBalance, $monthlyReport->items);
        }

        $excelData = array();
        $cardIdToCardOnName = array();
        foreach ($allBalance as $item) {
            $data = [
                'card_id' => $item->card_id,
                'card_nickname' => $item->card_nickname,
                'merchant_name' => $item->merchant->name,
                'status' => $item->status,
                'billing_amount' => $item->billing_amount,
                'transaction_date' => date('Y-m-d H:i:s', strtotime($item->transaction_date)),
            ];
            if (!isset($cardIdToCardOnName[$item->card_id])) {
                $cardDetail = $this->getCardDetail($item->card_id);
                $cardIdToCardOnName[$item->card_id] = [
                    'number' => $cardDetail->card_number,
                    'name' => $cardDetail->name_on_card,
                ];
            }
            $data['card_number'] = $cardIdToCardOnName[$item->card_id]['number'] ?? null;
            $data['name_on_card'] = $cardIdToCardOnName[$item->card_id]['name'] ?? null;
            $excelData[] = $data;
        }

        CostsModel::insertAll($excelData);
        echo 'END';
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
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $ids;
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