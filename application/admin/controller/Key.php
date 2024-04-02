<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\ApiKey;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use http\Env\Request;
use think\Db;
use function fast\array_except;

class Key extends Backend
{
    public function index()
    {
        $apiKey = ApiKey::find();
        $row = [
            'api_client_id' => $apiKey->api_client_id,
            'api_key' => $apiKey->api_key,
        ];
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $apiKey = ApiKey::find();
            $apiKey->api_client_id = $params['api_client_id'];
            $apiKey->api_key = $params['api_key'];
            $apiKey->save();
            echo '<h3>修改成功<h3>';
            $this->success();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    protected function getTransactions($limit = 100,$page=0, $startTime = null, $endTime = null, $card_id = null)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/transactions?page_size='.$limit.'&page_num='.$page;
        if ($startTime&&$endTime) {
            $url .= '&from_created_at='.$startTime.'&to_created_at='.$endTime;
        }
        if ($card_id) {
            $url .= '&card_id='.$card_id;
        }
        $headers = [
            'Authorization: Bearer ' . $this->airToken
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

        $data = json_decode($response);

        return $data;
    }

    protected function getCardHolders()
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cardholders?cardholder_status=READY';
        $headers = array(
            "Authorization: Bearer " . $this->airToken,
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

    protected function getAllCards($limit = 20,$page=0, $startTime = null, $endTime = null, $cardholderId = null, $nickname = null)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards?card_status=ACTIVE&page_size='.$limit.'&page_num='.$page;
        if ($startTime&&$endTime) {
            $url .= '&from_created_at='.$startTime.'&to_created_at='.$endTime;
        }
        if ($cardholderId) {
            $url .= '&cardholder_id='.$cardholderId;
        }
        if ($nickname) {
            $url .= '&nick_name='.$nickname;
        }
        $headers = [
            'Authorization: Bearer ' . $this->airToken
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




        return $response;
    }


    protected function getCardOnName($card_id)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $card_id;
        $headers = array(
            "Authorization: Bearer " . $this->airToken,
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

        $result = json_decode($response);
        return $result->name_on_card;
    }

}
