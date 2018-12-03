// pages/order-detail/order-detail.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const app = getApp();

Page(Object.assign({}, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: '订单详情',
        currency: app.data.currency,
        order: null
    }),
    // 发起支付
    makePayment() {
        app.requestPayment({
            id: this.options.id,
            success: res => {
                wx.showToast({
                    title: '支付成功',
                    success: () => {
                        setTimeout(() => {
                            this.onLoad(this.options);
                        }, 1500);
                    }
                });
            },
            fail: res => {
                if (res.errMsg != 'requestPayment:fail cancel') {
                    wx.showToast({
                        title: res.errMsg,
                        icon: 'none'
                    });
                }
            }
        })
    },
    goProductDetail(e) {
        app.goProductDetail(e);
    },
    onLoad(options) {
        this.setData({
            currentPages: getCurrentPages().length
        });

        this.options = options;
        app.Util.network.GET({
            url: app.API('order_detail') + options.id,
            params: {
                w2w_session: app.data.w2w_session
            },
            success: data => {
                this.setData({
                    order: data,
                    order_status: data.status == 'completed' && data.reviewed ? '已完成' : app.Util.getOrderStatus(data.status),
                    province: app.Util.getStateName(data.billing.state) || ''
                });
            }
        });
    },
    onShow() {

    },
    onPullDownRefresh() {
        this.onLoad(this.options);
    },
    onReachBottom() {

    }
}))