define(['jquery', 'bootstrap', 'backend', 'table', 'form'], function ($, undefined, Backend, Table, Form) {

    var Controller = {
        index: function () {
            // 初始化表格参数配置
            Table.api.init({
                extend: {
                    index_url: 'cards/active/index',
                    add_url: 'cards/active/add',
                    edit_url: 'cards/active/edit',
                    del_url: 'cards/active/del',
                    multi_url: 'cards/active/multi',
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
                        {field: 'id', title: __('Id'), visible: false, operate:false, sortable: true},
                        {field: 'brand', title: __('品牌'), operate:false},
                        // {field: 'card_number', title: __('卡号'), operate:false},
                        {field: 'card_number', title: __('卡号'), operate: false, formatter: Controller.api.formatter.browser},
                        {field: 'card_id', title: __('卡ID'), operate:false},
                        {field: 'remaining', title: __('可用余额'), operate:false},
                        {field: 'amount', title: __('每日限额'), operate:false},
                        {field: 'nick_name', title: __('别名'),},
                        {field: 'created_at', title: __('申请日期'), formatter: Table.api.formatter.datetime, operate: 'RANGE', addclass: 'datetimerange', sortable: true},
                        {field: 'cardholder_name', title: __('持卡人'), operate:false},
                        {field: 'cardholder_id', title: __('员工ID'), visible: false, addClass: "selectpage", extend: "data-source='cards/active/cardHolders' data-field='username'"},
                        {field: 'operate', title: __('Operate'), table: table, events: Table.api.events.operate, formatter: Table.api.formatter.operate}
                    ]
                ]
            });

            // 为表格绑定事件
            Table.api.bindevent(table);

            $(".overlay").click(function() {
                $("#myIframe").hide();
                $(".overlay").hide();
            });

            $(document).on('click', ".card-link", function () {
                $("#loading").show();
                var cardId = $(this).data("card_id");
                $.ajax({
                    url: 'cards/active/auth',
                    type: 'POST',
                    data: {cardId: cardId},
                    dataType: 'json',
                    success: function(response) {
                        const hash = {
                            token: response.token,
                            langKey: 'en',
                            rules: {
                                '.details': {
                                    backgroundColor: '#2a2a2a',
                                    color: 'white',
                                    borderRadius: '20px',
                                    fontFamily: 'Arial'

                                },
                                '.details__row': {
                                    display: 'flex',
                                    justifyContent: 'space-between',
                                    padding: '20px'
                                },
                                '.details__label': {
                                    width:  '100px',
                                    fontWeight: 'bold'
                                },
                                '.details__content': { display: 'flex' },
                                '.details__button svg': { color: 'white' }
                            },
                        };
                        const hashURI = encodeURIComponent(JSON.stringify(hash));

                        var hashScr = 'https://airwallex.com/issuing/pci/v2/' + cardId + '/details#' +  hashURI;
                        $("#myIframe").attr("src", hashScr).show();
                        $("#loading").hide();
                        $(".overlay").show();

                    }, error: function () {
                        $("#loading").hide();
                        Toastr.error(__('Network error'));
                    }
                });
            });
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
                    return '<a href="javascript:;" class="card-link" title="点击查看卡号" data-card_id="' + row.card_id + '" >' + row.card_number + '</a>';
                },
            },
        }
    };
    return Controller;
});