// pages/about/about.js

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
        pageTitle: '关于 ' + app.data.name,
        padding: 40
    }),
    tagATap(e) {
        var src = e.detail.src;
		if (/^http/.test(src)) {
			app.openWebView(src);
		} else {
			wx.navigateTo({
				url: src
			})
		}
    },
    onLoad(options) {
        this.setData({
            currentPages: getCurrentPages().length
        });
    },
    onShow() {
        app.Util.network.GET({
            url: app.API('about'),
            success: data => {
                this.setData({
                    custom: data.length > 0,
                    content: data,
                    version: app.data.version
                });
            }
        });
    },
    onPullDownRefresh() {
        this.onShow();
    },
    onReachBottom() {

    },
    onShareAppMessage() {
        var path = '/pages/about/about';
        if (app.isLoggedIn()) {
            path = path + '?uid=' + app.data.user_id;
        }
        return {
            path: path
        }
    }
}))