<?php

namespace app\admin\controller\cards;

use app\admin\model\Cancel;
use app\admin\model\Cards;
use app\admin\model\Record;
use app\common\controller\Backend;
use DateTime;
use fast\Random;
use Monolog\Handler\IFTTTHandler;
use think\Db;
use think\Validate;
use function fast\array_except;
use app\admin\model\Recharge;

class Freeze extends Backend
{
    public function index()
    {
        $cardStatusArray = [
            'ACTIVE' => '正常',
            'INACTIVE' => '冻结',
            'CLOSED' => '取消'
        ];
        $this->view->assign('cardStatus', $cardStatusArray);
        if ($this->request->isPost()) {
            $params = $this->request->post();
            $status = $params['status'] ?? null;
            $cardNumbers = $params['card_number'] ?? null;
            if ($cardNumbers) {
                $cardNumbers = explode(PHP_EOL,$cardNumbers);
                if (count($cardNumbers) > 0) {
                    foreach ($cardNumbers as $number) {
                        $cardNumber = ltrim(rtrim($number));
                        $cardNumber = substr($cardNumber, -4);
                        $card = Cards::where('card_number', 'like', '%' . $cardNumber)->find();
                        if ($card) {
                            $this->updateCard($card->card_id,$status);
                        }
                    }
                }
                echo '<h3>操作成功<h3>';
                $this->success();
            } else {
                echo '<h3>未输入卡号<h3>';
                $this->error();
            }
        }
        return $this->view->fetch();
    }

    protected function updateCard($card_id,$status)
    {
        $url = Backend::AIR_API_URL . 'api/v1/issuing/cards/'.$card_id.'/update';

        $data = array(
            'card_status' => $status,
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
            $card = Cards::where('card_id', '=', $card_id)->find();
            if ($card) {
                $card->card_status = 2;
                $card->save();
            }
        } elseif ($data->card_status == 'CLOSED') {
            Cancel::create($record);
            $card = Cards::where('card_id', '=', $card_id)->find();
            if ($card) {
                $card->card_status = 3;
                $card->save();
            }
        } elseif ($data->card_status == 'ACTIVE') {
            $card = Cards::where('card_id', '=', $card_id)->find();
            if ($card) {
                $card->card_status = 1;
                $card->save();
            }
        }


        if (curl_errno($ch)) {
            echo 'cURL Error: ' . curl_error($ch);
        }
        curl_close($ch);
    }

}