// pages/checkout/checkout.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const Zan = require('../../vendor/ZanUI/index');
const app = getApp();

Page(Object.assign({}, Zan.TopTips, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: '结算',
        currency: app.data.currency,
        /* W2W Extension, Name: w2w-advanced-coupon, Code: couponSelectMode */

/* W2W Extension, Name: w2w-advanced-coupon, Code: couponSelectMode */
        address: null,
        cart: null,
        isShippingPopup: false,
        shipping: null,
        addressAuth: true, // 是否授权收货地址
    }),
    // 检查配送方式
    checkShipping() {
        if ((this.data.addressType == 'wx' && (app.data.address == '' || app.data.address.userName == undefined)) ||
            (this.data.addressType != 'wx' && (app.data.address == '' || app.data.address.billing_first_name == undefined))) {
            wx.showToast({
                title: '请先选择收货地址',
                icon: 'none'
            })
            return false;
        } else if (app.data.cart.needs_shipping === true && app.data.shipping.methods.length == 0) {
            wx.showToast({
                title: '当前地址暂无可用配送方式',
                icon: 'none'
            })
            return false;
        }
        return true;
    },
    // 打开配送方式弹窗
    openShippingPopup() {
        /*if (this.data.cart.errors.length > 0) {
            this.showZanTopTips(this.data.cart.errors);
            return;
        }*/

        if (app.data.cart.needs_shipping === false) {
            wx.showToast({
                title: '当前订单无需物流',
                icon: 'none'
            })
        } else {
            if (this.checkShipping()) {
                this.setData({
                    isShippingPopup: true
                });
            }
        }
    },
    // 关闭配送方式弹窗
    closeShippingPopup() {
        this.setData({
            isShippingPopup: false
        });
    },
    // 新版 - 按钮打开设置页回调
    openSetting(e) {
        console.log(e);
        if (e.detail.authSetting['scope.address'] == true) {
            this.setData({
                addressAuth: true
            });
            this.selectAddressSuccess();
        }
    },
    // 选择收货地址成功
    selectAddressSuccess() {
        wx.chooseAddress({
            success: res => {
                console.log('获取收货地址成功', res);
                app.data.address = res;
                this.setData({
                    address: res
                });
                wx.setStorage({
                    key: 'address',
                    data: res
                })

                this.getShippingMethods();
            },
            fail: res => {
                if (res.errMsg == 'chooseAddress:fail auth deny' || res.errMsg == 'chooseAddress:fail:auth denied') {
                    this.setData({
                        addressAuth: false
                    }); // 为新版做准备
                }
            }
        })
    },
    // 获取配送方式
    getShippingMethods() {

        var address_param = app.getAddressParam();

        app.Util.network.GET({
            url: app.API('get_shipping_method'),
            params: Object.assign({},
                address_param, {
                    w2w_session: app.data.w2w_session,
                }
            ),
            success: data => {
                app.updateCart(data.cart);
                app.data.shipping = data.shipping;
                app.data.payment = data.payment;
                this.setData(data);
                if (data.cart.errors.length > 0) {
                    this.showZanTopTips(data.cart.errors);
                }
            }
        });
    },
    // 选择配送方式
    shippingChange(e) {
        var method = e.currentTarget.dataset.id;
        this.closeShippingPopup();

        app.Util.network.POST({
            url: app.API('set_shipping_method'),
            params: {
                w2w_session: app.data.w2w_session,
                shipping_method: method
            },
            success: data => {
                app.updateCart(data.cart);
                app.data.shipping = data.shipping;
                app.data.payment = data.payment;
                this.setData(data);
            }
        });
    },
    // 选择支付方式
    paymentChange(e) {
        /*if (this.data.cart.errors.length > 0) {
            this.showZanTopTips(this.data.cart.errors);
            return;
        }*/

        var payment = e.currentTarget.dataset.id;
        app.Util.network.POST({
            url: app.API('set_payment_method'),
            params: {
                w2w_session: app.data.w2w_session,
                payment_method: payment
            },
            success: data => {
                app.data.payment = data.payment;
                this.setData(data);
            }
        });
    },
    // 提交订单
    goSubmitOrder(e) {
        if (this.data.cart.errors.length > 0) {
            this.showZanTopTips(this.data.cart.errors);
            return;
        }
        if (!this.checkShipping()) return;
        if (this.data.payment.chosen_method === false) {
            wx.showToast({
                icon: 'none',
                title: '暂无可用支付方式'
            })
            return;
        }
        if (app.data.cart.cart_contents_count == 0) return;

        var order_param = app.getOrderParam(),
            params = Object.assign({},
                order_param, {
                    w2w_session: app.data.w2w_session,
                    order_comments: e.detail.value.comment,
					form_id: e.detail.formId
                }
            );

        if (this.data.shipping.chosen_method != '') {
            params.shipping_method = this.data.shipping.chosen_method;
        }
        if (this.data.payment.chosen_method != '') {
            params.payment_method = this.data.payment.chosen_method;
        }

        app.Util.network.POST({
            url: app.API('order'),
            params: params,
            success: data => {
                console.log(data);

                // 订单提交成功
                if (data.result == 'success') {

                    // 需要付费
                    if (data.order != undefined) {
                        var order_id = data.order.id;

                        // 发起支付
                        app.requestPayment({
                            id: order_id,
                            success: res => {
                                wx.showToast({
                                    title: '支付成功',
                                    success: () => {
                                        setTimeout(() => {
                                            wx.redirectTo({
                                                url: '/pages/order-detail/order-detail?id=' + order_id + '&status=success',
                                            })
                                        }, 1500);
                                    }
                                });
                            },
                            fail: res => {
                                if (res.errMsg == 'requestPayment:fail cancel') {
                                    wx.redirectTo({
                                        url: '/pages/order-detail/order-detail?id=' + order_id + '&status=cancel',
                                    })
                                } else {
                                    wx.showToast({
                                        title: '支付暂时出现问题，请稍候再试',
                                        icon: 'none',
                                        success: () => {
                                            setTimeout(() => {
                                                wx.redirectTo({
                                                    url: '/pages/order-detail/order-detail?id=' + order_id + '&status=fail',
                                                })
                                            }, 1500);
                                        }
                                    });
                                }
                            },
                            complete: res => {
                                console.log(res);
                                this.emptyCheckoutData();
                            }
                        });
                    }
                    // 不需付费
                    else {
                        var redirect = data.redirect,
                            exp = /order\-received\/(\d+)\/\?key\=/g,
                            result = exp.exec(redirect);
                        if (result != null) {
                            wx.showToast({
                                title: '订单提交成功',
                                success: () => {
                                    setTimeout(() => {
                                        wx.redirectTo({
                                            url: '/pages/order-detail/order-detail?id=' + result[1] + '&status=success',
                                        })
                                    }, 1500);
                                }
                            });
                        }
                    }
                }
                // 订单提交失败
                else if (data.result == 'failure') {

                    if (data.messages) {
                        var exp = /\<li\>(.*?)\<\/li\>/ig,
                            result,
                            errors = [];
                        while ((result = exp.exec(data.messages)) != null) {
                            errors.push(result[1]);
                        }
                        this.showZanTopTips(errors);
                    } else {
                        this.showZanTopTips(['提交失败，请稍候再试']);
                    }
                }
            }
        });
    },
    // 清空结算数据
    emptyCheckoutData() {
        app.updateCart({
            cart: null,
            cart_contents_count: 0
        });
        //app.shipping = null;

        this.load();
    },
    // 跳转地址选择
    goAddressList() {
        wx.navigateTo({
            url: '/pages/address-list/address-list?mode=select'
        })
    },
    load() {
        var data = {
            cart: app.data.cart,
            addressType: app.data.addressType || 'wx'
        };
        if (app.data.address != null) {
            data.address = app.data.address;
        }
        if (app.data.shipping != null) {
            data.shipping = app.data.shipping;
        }
        if (app.data.payment != null) {
            data.payment = app.data.payment;
        }
        this.setData(data);
    },
    onLoad(options) {
        this.load();
        this.getShippingMethods();
    },
    onShow() {
        this.setData({
            currentPages: getCurrentPages().length
        });

        this.load();
        if (app.data.addressChange === true) {
            this.getShippingMethods();
            app.data.addressChange = false;
        }
    },
    onPullDownRefresh() {
        this.onLoad();
    },
    onReachBottom() {

    }
}))