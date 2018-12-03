// pages/my.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const app = getApp();

Page(Object.assign({}, app.Methods, {
    data: Object.assign({}, app.Variables, {
        appName: app.data.name,
        pageTitle: '我的',
        userInfo: null,
        blurClass: 'blur-android'
    }),
    goOrderList(e) {
        var dataset = e.currentTarget.dataset,
            status = 'all';
        if (dataset.status != undefined) {
            status = dataset.status;
        }
        this.checkLogin(() => {
            wx.navigateTo({
                url: '/pages/order-list/order-list?status=' + status,
            })
        });
    },
    goAbout() {
        wx.navigateTo({
            url: '/pages/about/about',
        })
    },
    /* W2W Extension, Name: w2w-scan-to-login, Code: scanQRCode */

/* W2W Extension, Name: w2w-scan-to-login, Code: scanQRCode */
    loginSuccess() {
        app.refreshOrdersInfo((data) => {
            this.setData({
                ordersInfo: data.orders_info
            });
        });
    },
    onLoad(options) {
        //var blurClass = wx.IS_ANDROID ? 'blur-android' : 'blur-ios'
        this.setData({
            //blurClass: blurClass,
            orderStatuses: app.Util.getOrderStatusObj()
        });
    },
    onShow() {
        this.setData({
            userInfo: app.data.userInfo
        });
        if (app.isLoggedIn()) {
            app.refreshOrdersInfo((data) => {
                this.setData({
                    ordersInfo: data.orders_info
                });
            });
        }
        app.checkLogin({
            fail: () => {
                wx.login({
                    success: (res) => {
                        app.data.js_code = res.code
                    }
                })
            }
        });
    },
    /*
    onPullDownRefresh() {

    },
	*/
    onReachBottom() {

    }
}))