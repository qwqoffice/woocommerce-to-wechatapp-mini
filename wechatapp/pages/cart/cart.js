// pages/cart/cart.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const Zan = require('../../vendor/ZanUI/index');
const app = getApp();

Page(Object.assign({}, Zan.Stepper, Zan.TopTips, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: '购物车',
        currency: app.data.currency,
        cart: null,
        _isLoginPopup: false
    }),
    // 数量选择 更新购物车
    handleZanStepperChange(e) {
        var componentId = e.componentId,
            stepper = e.stepper,
            params = {
                w2w_session: app.data.w2w_session,
                cart_key: componentId,
            },
            url;

        if (stepper == 0) {
            url = app.API('delete_cart');
        } else {
            url = app.API('update_cart');
            params.quantity = stepper;
        }

        app.Util.network.POST({
            url: url,
            params: params,
            success: data => {
                app.updateCart(data);
                this.setData({
                    cart: data
                });
                if (data.errors.length > 0) {
                    this.showZanTopTips(data.errors);
                }
            }
        });
    },
    // 删除购物车项
    deleteCartItem(e) {

        var cart_key = e.currentTarget.dataset.cartKey;

        app.Util.network.POST({
            url: app.API('delete_cart'),
            params: {
                w2w_session: app.data.w2w_session,
                cart_key: cart_key
            },
            success: data => {
                wx.showToast({
                    title: '删除成功',
                    duration: 1500
                });
                app.updateCart(data);
                this.setData({
                    cart: data
                });
                if (data.errors.length > 0) {
                    this.showZanTopTips(data.errors);
                }
            }
        });
    },
    // 登录成功
    loginSuccess(data) {
        this.setData({
            cart: data.cart,
            _isLoginPopup: false
        });
        if (data.cart.errors.length > 0) {
            this.showZanTopTips(data.cart.errors);
        }
        app.refreshOrdersInfo();
    },
    // 结算
    goCheckout() {
        /*
        if (this.data.cart.errors.length > 0) {
        	this.showZanTopTips(this.data.cart.errors);
        }
        else {*/
        wx.navigateTo({
            url: '/pages/checkout/checkout'
        })
        //}
    },
    // 随便逛逛
    goShopping() {
        wx.navigateTo({
            url: '/pages/product-list/product-list?mode=all'
        })
    },
    goProductDetail(e) {
        app.goProductDetail(e);
    },
    onLoad(options) {
        app.checkLogin({
            fail: () => {
                this.setData({
                    _isLoginPopup: true
                });
                wx.login({
                    success: (res) => {
                        app.data.js_code = res.code
                    }
                })
            }
        });
    },
    onShow() {
        /*if (app.data.cart != null) {
        	wx.showLoading({
        		title: '正在加载',
        		mask: true
        	})
        	this.setData({
        		cart: app.data.cart
        	}, () => {
        		wx.hideLoading();
        	});
        	app.updateCart(app.data.cart);
        	if (app.data.cart.errors.length > 0) {
        		this.showZanTopTips(app.data.cart.errors);
        	}
        }
        else {
        	this.onPullDownRefresh();
        }*/
        this.onPullDownRefresh();
    },
    onPullDownRefresh() {
        this.checkLogin(() => {
            app.refreshCart((cart) => {
                this.setData({
                    cart: cart,
                    _isLoginPopup: false
                });
                if (cart.errors.length > 0) {
                    this.showZanTopTips(cart.errors);
                }
            });
        });
    },
    onReachBottom() {

    }
}))