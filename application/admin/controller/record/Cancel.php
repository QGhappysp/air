<?php

namespace app\admin\controller\record;

use app\common\controller\Backend;

class Cancel extends Backend
{
    public function _initialize()
    {
        parent::_initialize();
        $this->model = model('Cancel');
    }

    public function index()
    {
        if ($this->request->isAjax()) {
            list($where, $sort, $order, $offset, $limit) = $this->buildparams();

            $list = $this->model
                ->where($where)
                ->order($sort, $order)
                ->paginate($limit);
            $result = array("total" => $list->total(), "rows" => $list->items());

            return json($result);
        }
        return $this->view->fetch();
    }

}