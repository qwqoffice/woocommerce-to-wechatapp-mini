// pages/order-list/order-list.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const Zan = require('../../vendor/ZanUI/index');
const app = getApp();

Page(Object.assign({}, Zan.Tab, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: '订单列表',
        currency: app.data.currency,
        orders: [],
        bottomStyle: null,
        tabSelected: 'all',
        tabList: [{
                id: 'all',
                title: '全部'
            },
            {
                id: 'pending',
                title: '待付款'
            },
            {
                id: 'processing',
                title: '处理中'
            },
            {
                id: 'completed',
                title: '已完成'
            }
        ]
    }),
    page: 1,
    options: null,
    // 选项卡变更
    handleZanTabChange({
        componentId,
        selectedId
    }) {
        if (componentId == 'order-tab') {
            this.setData({
                tabSelected: selectedId
            });
            this.options.status = selectedId;
            this.refreshOrderData();
        }
    },
    // 发起支付
    makePayment(e) {
        var dataset = e.currentTarget.dataset;
        app.requestPayment({
            id: dataset.id,
            success: res => {
                wx.showToast({
                    title: '支付成功',
                    success: () => {
                        this.changeOrderStatus(dataset.index, 'processing');
                    }
                });
            },
            fail: res => {
                if (res.errMsg != 'requestPayment:fail cancel') {
                    wx.showToast({
                        title: '支付暂时出现问题，请稍候再试',
                        icon: 'none'
                    });
                }
            }
        })
    },
    // 改变页面订单状态
    changeOrderStatus(index, status) {
        var orders = this.data.orders;
        orders[index].status = status;
        orders[index]['order_status_desc'] = orders[index].status == 'completed' && orders[index].reviewed ? '已完成' : app.Util.getOrderStatus(orders[index].status);
        this.setData({
            orders: orders
        });
    },
    // 跳转订单详情
    goOrderDetail(e) {
        var id = e.currentTarget.dataset.id;
        wx.navigateTo({
            url: '/pages/order-detail/order-detail?id=' + id
        })
    },
    goProductDetail() {},
    loadData(clear) {

        if (this.data.bottomStyle == 'nomore' || this.data.bottomStyle == 'empty') {
            if (this.page != 1) {
                wx.showToast({
                    icon: 'none',
                    title: '没有更多了~'
                })
            }
            return;
        }

        var params = {
            w2w_session: app.data.w2w_session,
            page: this.page
        };
        if (this.options.status != 'all') params.status = this.options.status;

        app.Util.network.GET({
            url: app.API('order_list'),
            params: params,
            success: data => {

                var orders = data;

                for (var i = 0; i < orders.length; i++) {
                    orders[i]['order_status_desc'] = orders[i].status == 'completed' && orders[i].reviewed ? '已完成' : app.Util.getOrderStatus(orders[i].status);
                }
                if (clear != true) {
					var setdata = {};
					var offset = (this.page - 1) * 10;
					for (var i = 0; i < data.length; i++) {
						setdata['orders[' + (offset + i) + ']'] = data[i];
					}
					setdata.page = this.page;
					this.setData(setdata);
                }
				else{
					this.setData({
						orders: orders
					})
				}

                if (data.length == 0) {
                    this.setData({
                        bottomStyle: (this.page == 1 ? 'empty' : 'nomore')
                    });
                    if (this.page != 1) {
                        wx.showToast({
                            icon: 'none',
                            title: '没有更多了~'
                        })
                    }
                    return;
                }

                this.page++;
            }
        });
    },
    refreshOrderData() {
        this.page = 1;
        this.setData({
            tabSelected: this.options.status,
            bottomStyle: null
        });
        this.loadData(true);
    },
    onLoad(options) {
        this.setData({
            currentPages: getCurrentPages().length
        });

        this.options = options;
        if (this.options.status == undefined) this.options.status = this.data.tabList[0].id;
        this.refreshOrderData();
        this.isOnLoad = true;
    },
    onShow() {
        if (this.isOnLoad == true) {
            this.isOnLoad = false;
        } else {
            this.refreshOrderData();
        }
    },
    onPullDownRefresh() {
        this.refreshOrderData();
    },
    onReachBottom() {
        this.loadData(false);
    }
}))