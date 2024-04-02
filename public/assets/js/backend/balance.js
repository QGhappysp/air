define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'balance/index',
                    add_url: 'balance/add',
                    edit_url: 'balance/edit',
                    del_url: 'balance/del',
                    multi_url: 'balance/multi',
                }
            });

            var table = $("#table");

            // 初始化表格
            table.bootstrapTable({
                url: $.fn.bootstrapTable.defaults.extend.index_url,
                paginationLoop: false,
                pageList:[10, 20, 25, 50, 100],
                pageSize: 10,
                columns: [
                    [
                        {checkbox: true},
                        {field: 'id', title: __('Id'), visible: false, operate:false, sortable: true},
                        {field: 'transaction_date', title: __('交易时间'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'card_number', title: __('卡号'), operate:false},
                        // {field: 'card_number', title: __('卡号'), operate:false},
                        {field: 'card_id', title: __('卡ID')},
                        {field: 'billing_amount', title: __('账单金额'), operate:false},
                        {field: 'card_nickname', title: __('卡别名'), operate:false},
                        {field: 'status', title: __('状态')},
                        {field: 'name_on_card', title: __('持卡人')},
                        // {field: 'cardholder_name', title: __('员工'), operate:false},
                        // {field: 'cardholder_id', title: __('员工ID'), visible: false, addClass: "selectpage", extend: "data-source='cards/active/cardHolders' data-field='username'"},
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