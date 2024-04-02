define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'record/freeze/index',
                    add_url: 'record/freeze/add',
                    edit_url: 'record/freeze/edit',
                    del_url: 'record/freeze/del',
                    multi_url: 'record/freeze/multi',
                    table: 'record',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                paginationLoop: false,
                pageList:[10, 20, 25, 50, 100],
                pageSize: 20,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), visible: false, operate:false, sortable: true},
                        {field: 'card_number', title: __('卡号'), sortable: true},
                        {field: 'card_id', title: __('卡ID'), sortable: true},
                        {field: 'nick_name', title: __('别名'), sortable: true,operate: 'LIKE',},
                        {field: 'card_holder', title: __('持卡人'), sortable: true,operate: 'LIKE',},
                        {field: 'creator_name', title: __('管理员'), sortable: true,operate: 'LIKE',},
                        {field: 'created_at', title: __('冻结时间'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'status', title: __('员工ID'), visible: false, addClass: "selectpage", extend: "data-source='cards/active/cardHolders' data-field='username'"},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);
        },
        add: function () {
            Controller.api.bindevent();
        },
        edit: function () {
            Controller.api.bindevent();
        },
        api: {
            bindevent: function () {
                Form.api.bindevent($("form[role=form]"));
            }
        }
    };
    return Controller;
});