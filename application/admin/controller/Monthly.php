<?php

namespace app\admin\controller;

use app\common\controller\Backend;
use think\Env;

class Monthly extends Backend
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('File');
    }

    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);

            foreach ($list->items() as $item) {
                $item->url = 'http://omiemieo.com/' . "uploads/report/" . $item->file_name;
            }
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }


}