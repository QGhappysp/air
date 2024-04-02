<?php

namespace app\admin\controller;

use app\common\controller\Backend;

class Balance extends Backend
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Costs');
    }

    public function index()
    {
//        $card = $this->pay1();
//        $cc = $this->pay2($card->transaction_id);
//        var_dump($cc);exit;

        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());
            return json($result);
        }
        $this->view->assign('page', 0);
        $this->view->assign('har_more', true);

        return $this->view->fetch();
    }

    protected function pay1()
    {
        $url = Backend::AIR_API_URL . 'api/v1/simulation/issuing/create';

        $data = array(
//            'auth_code' => '123312',
            'card_id' => 'c37da399-2e34-482a-9b30-376646412705',
            'transaction_amount' => 4300,
            'transaction_currency' => 'USD'
        );

        $jsonData = json_encode($data);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' .  $this->airToken// 替换为你的Bearer令牌
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);


        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }

    protected function pay2($transaction_id)
    {
        $url = Backend::AIR_API_URL . 'api/v1/simulation/issuing/'.$transaction_id.'/capture';

        $data = array(
            'merchant_info' => 'Example'
        );

        $jsonData = json_encode($data);
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Authorization: Bearer ' .  $this->airToken// 替换为你的Bearer令牌
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);


        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);

        return json_decode($response);
    }

    protected function getTransactions($limit = 20,$page=0, $startTime = null, $endTime = null, $cardholderId = null, $nickname = null, $card_id = null)
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

    protected function getCardDetail($ids)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $ids;
        $headers = array(
            "Authorization: Bearer " . $this->airToken
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