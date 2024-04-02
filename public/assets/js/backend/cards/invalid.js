define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cards/invalid/index',
                    add_url: 'cards/invalid/add',
                    edit_url: 'cards/invalid/edit',
                    del_url: 'cards/invalid/del',
                    multi_url: 'cards/invalid/multi',
                    table: 'cards',
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
                        {field: 'brand', title: __('品牌'), operate:false},
                        {field: 'card_number', title: __('卡号')},
                        {field: 'card_id', title: __('卡ID'), operate:false},
                        {field: 'remaining', title: __('可用余额'), operate:false},
                        {field: 'amount', title: __('每日限额'), operate:false},
                        {field: 'nick_name', title: __('别名'),},
                        {field: 'created_at', title: __('申请日期'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'cardholder_name', title: __('持卡人'), operate:false},
                        {field: 'cardholder_id', title: __('员工ID'), visible: false, addClass: "selectpage", extend: "data-source='cards/active/cardHolders' data-field='username'"},
                        // {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: function (value, row, index) {
                        //         if(row.id == Config.admin.id){
                        //             return '';
                        //         }
                        //         return Table.api.formatter.operate.call(this, value, row, index);
                        //     }}
                        // {field: 'avatar', title: __('Avatar'), events: Table.api.events.image, formatter: Table.api.formatter.image, operate: false},
                        // {field: 'level', title: __('Level'), operate: 'BETWEEN', sortable: true},
                        // {field: 'gender', title: __('Gender'), visible: false, searchList: {1: __('Male'), 0: __('Female')}},
                        // {field: 'score', title: __('Score'), operate: 'BETWEEN', sortable: true},
                        // {field: 'successions', title: __('Successions'), visible: false, operate: 'BETWEEN', sortable: true},
                        // {field: 'maxsuccessions', title: __('Maxsuccessions'), visible: false, operate: 'BETWEEN', sortable: true},
                        // {field: 'logintime', title: __('Logintime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'loginip', title: __('Loginip'), formatter: Table.api.formatter.search},
                        // {field: 'jointime', title: __('Jointime'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        // {field: 'joinip', title: __('Joinip'), formatter: Table.api.formatter.search},
                        // {field: 'status', title: __('Status'), formatter: Table.api.formatter.status, searchList: {normal: __('Normal'), hidden: __('Hidden')}},
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