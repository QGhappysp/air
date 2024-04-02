<?php

namespace app\admin\command;

use app\admin\model\ApiKey;
use app\admin\model\File;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use think\Cache;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Monthly extends Command
{
    protected function configure()
    {
        $this->setName('monthly')->setDescription('monthly');
    }

    protected function execute(Input $input, Output $output)
    {
        $output->writeln('This is a test command');
    }

    public function run(Input $input, Output $output)
    {
        $firstDayOfLastMonth = strtotime('first day of last month midnight');
        $lastDayOfLastMonth = strtotime('last day of last month midnight') + 86399;
        $startTime = date('c', $firstDayOfLastMonth);
        $endTime = date('c', $lastDayOfLastMonth);
        $startTime = urlencode($startTime);
        $endTime = urlencode($endTime);

        $page = 0;
        $monthlyReport = $this->getReport(10, $page, $startTime, $endTime);
        $allBalance = $monthlyReport->items;
        while ($monthlyReport->has_more == true) {
            $page++;
            $monthlyReport = $this->getReport(10, $page, $startTime, $endTime);
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

        // 实例化 Spreadsheet 对象
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // 设置表头
        $columns = [
            '卡id',
            '别名',
            '卡号',
            '商户',
            '状态',
            '金额',
            '交易时间',
            '持卡人'
        ]; // 替换为你实际的列名
        $sheet->fromArray([$columns], null, 'A1');


        // 填充数据到表格中
        foreach ($excelData as $key => $row) {
            $rowData = [
                $row['card_id'], // 替换为你的字段名
                $row['card_nickname'],
                $row['card_number'],
                $row['merchant_name'],
                $row['status'],
                $row['billing_amount'],
                $row['transaction_date'],
                $row['name_on_card'],
            ];
            $sheet->fromArray([$rowData], null, 'A' . ($key + 2));
        }

        // 生成 Excel 文件
        $filename = date('Y-m',strtotime('last month')) . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save('public/uploads/report/' . $filename);

        $file = File::where('file_name', $filename)->find();
        if (!$file) {
            $file = new File();
            $file->file_name = $filename;
            $file->file_path = 'public/uploads/report/' . $filename;
            $file->date = date('Y-m',strtotime('last month'));
            $file->save();
        }
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