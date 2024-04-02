<?php

namespace app\admin\controller;

use app\admin\model\Admin;
use app\admin\model\Costs;
use app\admin\model\User;
use app\common\controller\Backend;
use app\common\model\Attachment;
use fast\Date;
use http\Env\Request;
use think\Db;
use function fast\array_except;

/**
 * 控制台
 *
 * @icon   fa fa-dashboard
 * @remark 用于展示当前系统中的统计数据、统计报表及重要实时数据
 */
class Dashboard extends Backend
{

    /**
     * 查看
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            $currentDate = date('Y-m-d');
            $time = $this->request->request('timeRange') ?? null;
            switch ($time) {
                case 'daily':
                    $startTimeTimestamp = strtotime($currentDate . ' 00:00:00');
                    $endTimeTimestamp = strtotime($currentDate . ' 23:59:59');
                    break;
                case 'yesterday':
                    $yesterday = date('Y-m-d', strtotime('-1 day'));
                    $startTimeTimestamp = strtotime($yesterday . ' 00:00:00');
                    $endTimeTimestamp = strtotime($yesterday . ' 23:59:59');
                    break;
                case 'last7days':
                    $startTimeTimestamp = strtotime($currentDate . ' -6 day');
                    $endTimeTimestamp = strtotime($currentDate . ' +1 day');
                    break;
                case 'thisweek':
                    $startTimeTimestamp = strtotime(date('Y-m-d', strtotime('this week')));
                    $endTimeTimestamp = strtotime(date('Y-m-d', strtotime('this week next day')));
                    break;
                case 'lastweek':
                    $startTimeTimestamp = strtotime(date('Y-m-d', strtotime('last week')));
                    $endTimeTimestamp = $startTimeTimestamp+7*86400-1   ;
                    break;
                case 'thismonth':
                    $startTimeTimestamp = strtotime(date('Y-m', strtotime('this month')));
                    $endTimeTimestamp = strtotime(date('Y-m-t', strtotime('this month')));
                    break;
                case 'lastmonth':
                    $year = date('Y');
                    $month = date('m');
                    $lastMonth = $month - 1;
                    if ($lastMonth == 0) {
                        $lastMonth = 12;
                        $year -= 1;
                    }
                    $startTimeTimestamp = strtotime("$year-$lastMonth-01 00:00:00");
                    $endTimeTimestamp = strtotime("$year-$lastMonth-" . date('t', strtotime("$year-$lastMonth-01")) . " 23:59:59");
                    break;
                case 'custom':
                    $startTimeTimestamp = strtotime($this->request->request('start') . ' 00:00:00');
                    $endTimeTimestamp = strtotime($this->request->request('end') . ' 23:59:59');
                    break;
                default:
                    $startTimeTimestamp = strtotime($currentDate . ' 00:00:00');
                    $endTimeTimestamp = strtotime($currentDate . ' 23:59:59');
            }
            $startTime = date('c', $startTimeTimestamp);
            $endTime = date('c', $endTimeTimestamp);

            $startTime = urlencode($startTime);
            $endTime = urlencode($endTime);


            $cardHolders = $this->getCardHolders()->items;
            $groupIds = $this->auth->getGroupIds();
            if (!in_array(1,$groupIds)) {
                foreach ($cardHolders as $holder) {
                    if ($holder->email == $this->auth->email) {
                        $nameOnCard = $holder->individual->name->name_on_card;
                    }
                }
                if (is_null($nameOnCard)) {
                    $this->error('你的邮箱没有对应的持卡人ID');
                    exit;
                }
                $spendData = array();
                $spendData = $this->getTransactions($startTime, $endTime, $nameOnCard);
            } else {
                $spendData = $this->getTransactions($startTime, $endTime);
            }
            $return = [
                'labels' => [],
                'data' => [],
            ];
            if (!empty($spendData)) {
                foreach ($spendData as $spendDatum) {
                    if ($spendDatum->status == 'APPROVED') {
                        if (isset($data[$spendDatum->name_on_card])) {
                            $data[$spendDatum->name_on_card] += abs($spendDatum->billing_amount);
                        } else {
                            $data[$spendDatum->name_on_card] = abs($spendDatum->billing_amount);
                        }
                    }
                }
                $return['labels'] = array_keys($data);
                $return['data'] = array_values($data);
            }
//            $return = [
//                'labels' => ['demo','Amy','Jick','Thomas','Bob','demo','Amy','Jick','Thomas','Bob'],
//                'data' => [435,5435,345,34,76,57,56,456,6786,5464],
//            ];
            echo json_encode($return);exit;
        }
        $groupIds = $this->auth->getGroupIds();
        if (in_array(1,$groupIds)) {
            $this->view->assign('isAdmin', true);
        } else {
            $this->view->assign('isAdmin', false);
        }
        return $this->view->fetch();
    }

    protected function getTransactions($startTime = null, $endTime = null, $name_on_card = null)
    {
        $query = Costs::where('transaction_date', '>=', $startTime)
            ->where('transaction_date','<=', $endTime);
        if ($name_on_card) {
            $query->where('name_on_card', $name_on_card);
        }
        return $query->select();
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
