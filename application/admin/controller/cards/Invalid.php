<?php

namespace app\admin\controller\cards;

use app\admin\model\Cancel;
use app\admin\model\Cards;
use app\admin\model\Recharge;
use app\admin\model\Record;
use app\common\controller\Backend;
use DateTime;
use fast\Random;
use think\Db;
use think\Validate;
use function fast\array_except;

class Invalid extends Backend
{
    protected $relationSearch = true;
    protected $searchFields = 'id,username,nickname';

    /**
     * @var \app\admin\model\User
     */
    protected $model = null;

    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Cards');
    }

    /**
     * 查看
     */
    public function index()
    {
        //设置过滤方法
        $this->request->filter(['strip_tags', 'trim']);
        if ($this->request->isAjax()) {
            $filter = $this->request->get('filter');
            $filter = json_decode($filter);

            $search = $this->request->get('search');
            if (!empty($search)) {
                if (!empty($search)) {
                    $cardNumber = substr($search, -4);
                } else {
                    $cardNumber = substr($filter->card_number, -4);
                }
                $card = Cards::where('card_number', 'like', '%' . $cardNumber)->find();
                if ($card && $card->card_status == 2) {
                    $detail = $this->getCardDetail($card->card_id);
                    $data[0] = [
                        'card_id' => $detail->card_id ?? null,
                        'brand' => $detail->brand ?? null,
                        'card_number' => $detail->card_number ?? null,
                        'nick_name' => $detail->nick_name ?? null,
                        'created_at' => $card->created_at,
                        'cardholder_name' => $detail->name_on_card ?? null,
                    ];
                    $cardInfo = $this->getRemaining($card->card_id);
                    $data[0]['remaining'] = $cardInfo->limits[0]->remaining ?? null;
                    $data[0]['amount'] = $cardInfo->limits[0]->amount ?? null;
                    $jsonData = [
                        'nickname' => $data[0]['nick_name'] ?? '',
                        'card_id' => $data[0]['card_id'],
                        'card_status' => 'INACTIVE',
                        'limit' => $data[0]['amount'] ?? '',
                    ];
                    $string = implode(',', $jsonData);
                    $data[0]['id'] = $string;
                    $result = array("total" => 1, "page" => 1,"has_more" => false, "rows" => $data);
                    return json($result);
                } else {
                    $result = array("total" => 0, "page" => 1,"has_more" => false, "rows" => []);
                    return json($result);
                }
            }

            $cardHolders = $this->getCardHolders()->items;
            $groupIds = $this->auth->getGroupIds();
            if (!in_array(1,$groupIds)) {
                foreach ($cardHolders as $holder) {
                    if ($holder->email == $this->auth->email) {
                        $cardholderId = $holder->cardholder_id;
                    }
                }
                if (is_null($cardholderId)) {
                    $this->error('你的邮箱没有对应的持卡人ID');
                }
            }

            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            if (isset($cardholderId)) {
                $list = $this->model->where('cardholder_id', $cardholderId);
            } else {
                $list = $this->model;
            }
            $list = $list
                ->where($where)
                ->where('card_status','INACTIVE')
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list->items() as &$item) {
                $item->nick_name = $item->nick_name ? str_replace("/", "", $item->nick_name) : '';
                $jsonData = [
                    'nickname' => $item->nick_name ?? null,
                    'card_id' => $item->card_id,
                    'card_status' => $item->card_status,
                    'limit' => $item->amount ?? '',
                ];
                $string = implode(',', $jsonData);
                $item->id = $string;
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        $this->view->assign('page', 0);
        $this->view->assign('har_more', true);

        return $this->view->fetch();
    }

    protected function getAllCards($limit = 20,$page=0, $startTime = null, $endTime = null, $cardholderId = null, $nickname = null)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards?card_status=INACTIVE&page_size='.$limit.'&page_num='.$page;
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

    public function cardHolders()
    {
        $return = array();
        $cardHolders = $this->getCardHolders()->items;
        foreach ($cardHolders as $cardHolder) {
            $data = array();
            $data['id'] = $cardHolder->cardholder_id;
            $data['username'] = $cardHolder->individual->name->name_on_card;
            $return['list'][] = $data;
        }
        $return['total'] = count($return['list']);



        return $return;


    }

    public function add()
    {
        $cardHolders = $this->getCardHolders()->items;
        $cardHoldersArray = array();
        foreach ($cardHolders as $cardHolder) {
            $cardHoldersArray[$cardHolder->cardholder_id] = $cardHolder->individual->name->name_on_card;
        }
        $this->view->assign('cardHolders', $cardHoldersArray);
        if ($this->request->isPost()) {
            $this->token();
            $params = $this->request->post("row/a");
            $cardCount = $params['card_count'];
            if ($cardCount>20) {
                $this->error('最多一次性创建20张');
            }
            for ($i=0; $i<$cardCount; $i++) {
                $this->createAirCard($params);
            }
            $this->success();
        }
        return $this->view->fetch();
    }

    protected function createAirCard($params)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/create';

        $data = array(
            'authorization_controls' => array(
                'allowed_transaction_count' => 'MULTIPLE',
                'transaction_limits' => array(
                    'currency' => 'USD',
                    'limits' => array(
                        array(
                            'amount' => $params['amount'],
                            'interval' => 'DAILY'
                        )
                    )
                )
            ),
            'cardholder_id' => $params['cardholder_id'],
            'created_by' => $params['name'],
            'nick_name' => $params['nickname'],
            'form_factor' => 'VIRTUAL',
            'issue_to' => 'INDIVIDUAL',
            'request_id' => $this->getRequestId()
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

    protected function getRequestId(): string
    {
        $uniqueID = uniqid();
        // 使用UUID生成函数生成UUID
        $uuid = sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15),
            mt_rand(0, 15)
        );
        // 将UUID和唯一ID拼接起来作为request_id
        return $uniqueID . '-' . $uuid;
    }

    protected function getRemaining($card_id)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $card_id . '/limits';
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
        $data = json_decode($response);

        return $data;

//        return $data->limits[0]->remaining;
    }

    public function edit($ids = null)
    {
        $data = explode(',', $ids);
        $row = [
            'nickname' => $data[0],
            'card_id' => $data[1],
            'status' => $data[2],
            'amount' => $data[3],
        ];
        $cardStatusArray = [
            'ACTIVE' => '正常',
            'INACTIVE' => '冻结',
            'CLOSED' => '取消'
        ];
        $selected = ['INACTIVE'];
        $this->view->assign('cardStatus', $cardStatusArray);
        $this->view->assign('selected', $selected);
        if ($this->request->isPost()) {
            $params = $this->request->post("row/a");
            $this->updateCard($params);
            $this->success();
        }
        $this->view->assign("row", $row);
        return $this->view->fetch();
    }

    protected function updateCard($params)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/'.$params['card_id'].'/update';

        $data = array(
            'authorization_controls' => array(
                'allowed_transaction_count' => 'MULTIPLE',
                'transaction_limits' => array(
                    'currency' => 'USD',
                    'limits' => array(
                        array(
                            'amount' => $params['amount'],
                            'interval' => 'DAILY'
                        )
                    )
                )
            ),
            'card_status' => $params['status'],
            'nick_name' => $params['nickname'],
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
        $data = json_decode($response);

        $recharge = [
            'card_id' => $data->card_id ?? null,
            'card_number' => $data->card_number ?? null,
            'amount' => $data->authorization_controls->transaction_limits->limits[0]->amount ?? null,
            'status' => $data->card_status ?? null,
            'card_holder' => $data->name_on_card ?? null,
            'creator' => $this->auth->id ?? null,
            'creator_name' => $this->auth->nickname ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'nick_name' => $data->nick_name ?? null,
        ];
        Recharge::create($recharge);

        $record = [
            'card_id' => $data->card_id ?? null,
            'card_number' => $data->card_number ?? null,
            'card_holder' => $data->name_on_card ?? null,
            'creator' => $this->auth->id ?? null,
            'creator_name' => $this->auth->nickname ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'nick_name' => $data->nick_name ?? null,
        ];

        if ($data->card_status == 'INACTIVE') {
            Record::create($record);
            $card = Cards::where('card_id', '=', $params['card_id'])->find();
            if ($card) {
                $card->card_status = 'INACTIVE';
                $card->save();
            }
        } elseif ($data->card_status == 'CLOSED') {
            Cancel::create($record);
            $card = Cards::where('card_id', '=', $params['card_id'])->find();
            if ($card) {
                $card->card_status = 'CLOSED';
                $card->save();
            }
        } elseif ($data->card_status == 'ACTIVE') {
            $card = Cards::where('card_id', '=', $params['card_id'])->find();
            if ($card) {
                $card->card_status = 'ACTIVE';
                $card->save();
            }
        }


        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);
    }

    protected function getCardInfo($ids)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/' . $ids;
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
        return [
            'nickname' => $result->nick_name,
        ];
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