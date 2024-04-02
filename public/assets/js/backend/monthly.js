define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'monthly/index',
                    add_url: 'monthly/add',
                    edit_url: 'monthly/edit',
                    del_url: 'monthly/del',
                    multi_url: 'monthly/multi',
                    table: 'file',
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
                        {field: 'date', title: __('日期'), operate:false},
                        {field: 'file_name', title: __('文件名'), operate:false},
                        {field: 'browser', title: __('下载链接'), operate: false, formatter: Controller.api.formatter.browser},
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
            },
            formatter: {
                browser: function (value, row) {
                    return '<a href="' + row.url + '" download="">下载</a>';
                },
            },
        }
    };
    return Controller;
});