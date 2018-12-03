// pages/utils/methods.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

var Methods = {
    // 设置标题
    setPageTitle(title) {
        this.setData({
            pageTitle: title
        });
    },
    // 返回按钮点击
    backBtnTap() {
        if (this.data.currentPages != undefined && this.data.currentPages > 1) {
            wx.navigateBack();
        } else {
            wx.switchTab({
                url: '/pages/index/index'
            })
        }
    },
    // 检查登录
    checkLogin(callback = function() {}) {
        var app = getApp();
        app.checkLogin({
            success: () => {
                callback();
            },
            fail: () => {
                this.openLoginPopup();
                this._loginSuccess = callback;
            }
        });
    },
    // 获取用户信息
    getUserInfo(e) {
        var app = getApp();
        app.buttonGetUserInfo(e, {
            success: data => {
                this.setData({
                    userInfo: data.userInfo
                });
                setTimeout(() => {
                    if (typeof(this.loginSuccess) == 'function') {
                        this.loginSuccess(data);
                    }
                    if (typeof(this._loginSuccess) == 'function') {
                        this._loginSuccess();
                        delete this._loginSuccess;
                    }
					this.closeLoginPopup();
                }, 1500);
            },
            fail: () => {
                this.closeLoginPopup();
                delete this._loginSuccess;
            }
        });
    },
    closeLoginPopup() {
        this.setData({
            isLoginPopup: false
        });
		if (typeof (this._loginSuccess) == 'function') {
			delete this._loginSuccess;
		}
    },
    openLoginPopup() {
        this.setData({
            isLoginPopup: true
        });
    },
    // 添加购物车
    doAddToCart(e, callback = function() {}, newPage = true) {

        var app = getApp(),
            dataset = e.currentTarget.dataset,
            product_id = dataset.id,
            product_type = dataset.type,
            product_name = dataset.name,
            in_stock = dataset.inStock,
            url = '/pages/product-detail/product-detail?id=' + product_id + '&name=' + encodeURIComponent(product_name) + '&popup=true';

        if (product_type == 'variable') {
            if (newPage) {
                wx.navigateTo({
                    url: url
                })
            } else {
                wx.redirectTo({
                    url: url
                })
            }
        } else {
            this.checkLogin(() => {
                app.Util.network.POST({
                    url: app.API('add_to_cart'),
                    params: {
                        product_id: product_id,
                        quantity: 1,
                        w2w_session: app.data.w2w_session
                    },
                    success: data => {
                        if (data.errors.length > 0) {
                            this.showZanTopTips(data.errors);
                        } else {
                            wx.showToast({
                                title: '添加成功'
                            })
                        }

                        app.updateCart(data);
                        callback(data);
                    }
                });
            });
        }
    },
    // 跳转首页
    goIndex() {
        wx.switchTab({
            url: '/pages/index/index'
        })
    },
    // 复制文字
    copyText(e) {
        var app = getApp();
        var copy = e.currentTarget.dataset.copy;
        app.Util.setClipboard(copy);
    },
}

module.exports = Methods;