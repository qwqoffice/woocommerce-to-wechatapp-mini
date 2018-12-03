// app.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

var system = require('utils/system.js');

App({
    data: {
        version: '1.7.1',
        versionDate: '20181204',
        siteURL: 'http://localhost/wordpress/',
        name: 'WooCommerce微信小程序',
        logo: '/images/woo-logo.png',
        apiPath: 'wp-json/w2w/v1/',
        apiList: {
            login: 'customers/login',
            orderinfo: 'customers/orderinfo',
            index: 'store/index',
            about: 'store/about',
            product: 'products/',
            product_list: 'products',
            product_search: 'products/search',
            product_qrcode: 'products/qrcode',
            category: 'products/categories',
            get_cart: 'cart',
            add_to_cart: 'cart/add',
            update_cart: 'cart/update_quantity',
            delete_cart: 'cart/delete',
            set_address: 'cart/address',
            get_shipping_method: 'cart/shipping',
            set_shipping_method: 'cart/shipping',
            get_payment_method: 'cart/payment',
            set_payment_method: 'cart/payment',
            order: 'orders',
            order_detail: 'orders/',
            order_list: 'orders',
            payment: 'payment',
        },
        apiPathWP: 'wp-json/wp/v2/',
        apiListWP: {
            post_list: 'posts'
        },
        /* W2W Extension, Name: w2w-advanced-address, Code: addressType */

/* W2W Extension, Name: w2w-advanced-address, Code: addressType */
        posterLogo: '/images/logo.png',
        js_code: null,
        w2w_session: null,
        currency: '¥',
        ordersInfo: null,
        cart: null,
        cart_quantity: 0,
        country_id: 'CN',
        address: null,
        shipping: null,
        payment: null,
        userInfo: null,
    },
    Util: require('utils/util.js'),
    Methods: require('utils/methods.js'),
    Variables: require('utils/variables.js'),
    // 获取API地址
    API(apiName, WP = false) {
        if (!/\/$/.test(this.data.siteURL)) {
            this.data.siteURL = this.data.siteURL + '/';
        }
        var apiURL = WP ? this.data.apiPathWP : this.data.apiPath;
        apiURL += WP ? this.data.apiListWP[apiName] : this.data.apiList[apiName];
        return this.data.siteURL + apiURL;
    },
    // 获取地址参数
    getAddressParam() {
        var address = this.data.address;
        var address_param = {};
        if (address != '') {
            if (address.userName != undefined) {
                address_param = {
                    country_id: this.data.country_id,
                    state: this.Util.getStateCode(address.provinceName),
                    city: address.cityName,
                    postcode: address.postalCode
                };
            }
            /* W2W Extension, Name: w2w-advanced-address, Code: addressAddressParam */

/* W2W Extension, Name: w2w-advanced-address, Code: addressAddressParam */
        }
        return address_param;
    },
    // 获取订单参数
    getOrderParam() {

        var address = this.data.address,
            addressParams,
            params = {
                // 2018-03-16 添加同意服务条款参数
                terms: true,
                _wpnonce: this.data.cart._wpnonce
            };
        if (address.userName != undefined) {
            addressParams = {
                billing_first_name: address.userName,
                billing_phone: address.telNumber,
                billing_country: this.data.country_id,
                billing_state: this.Util.getStateCode(address.provinceName),
                billing_city: address.cityName,
                billing_address_1: address.countyName + address.detailInfo,
                billing_postcode: address.postalCode,
            };
        }
        /* W2W Extension, Name: w2w-advanced-address, Code: addressOrderParam */

/* W2W Extension, Name: w2w-advanced-address, Code: addressOrderParam */
        params = Object.assign({}, params, addressParams);
        return params;
    },
    // 刷新订单信息
    refreshOrdersInfo(callback = function() {}) {

        this.Util.network.GET({
            url: this.API('orderinfo'),
            params: {
                w2w_session: this.data.w2w_session
            },
            showLoading: false,
            success: data => {
                this.updateOrdersInfo(data.orders_info);
                callback(data);
            }
        });
    },
    // 更新订单信息
    updateOrdersInfo(ordersInfo) {
        this.data.ordersInfo = ordersInfo;

        if (ordersInfo.total > 0) {
            wx.showTabBarRedDot({
                index: 3
            })
        } else {
            wx.hideTabBarRedDot({
                index: 3
            });
        }
    },
    // 刷新购物车
    refreshCart(callback = function() {}) {

        this.Util.network.GET({
            url: this.API('get_cart'),
            params: {
                w2w_session: this.data.w2w_session,
                check_cart_items: true
            },
            success: data => {
                this.updateCart(data);
                callback(data);
            }
        });
    },
    // 更新购物车
    updateCart(cart) {

        this.data.cart = cart;
        this.data.cart_quantity = cart == null ? 0 : cart.cart_contents_count;

        if (this.data.cart_quantity != 0) {
            wx.setTabBarBadge({
                index: 2,
                text: this.data.cart_quantity.toString()
            })
        } else {
            wx.removeTabBarBadge({
                index: 2
            });
        }
    },
    // 检查App是否登录
    isLoggedIn() {
        return !(this.data.userInfo == '' || this.data.userInfo == null || this.data.w2w_session == '' || this.data.w2w_session == null);
    },
    checkLogin(callback) {

        if (this.isLoggedIn()) {
            if (callback.success) callback.success();
        } else {
            if (callback.fail) {
                wx.login({
                    success: res => {
                        this.data.js_code = res.code;
                        callback.fail();
                    },
                    fail: res => {
                        console.error('wx.login失败', res);
                    }
                });
            }
        }
    },
    // 登录
    login(userRes, callback = function() {}) {
        this.Util.network.POST({
            url: this.API('login'),
            params: {
                js_code: this.data.js_code,
                encryptedData: userRes.encryptedData,
                iv: encodeURIComponent(userRes.iv)
            },
            success: data => {
                if (data.w2w_session != undefined) {
                    console.log('登录成功', data.w2w_session);
                    // 存储Session
                    this.data.user_id = data.user_id;
                    wx.setStorageSync('user_id', data.user_id);
                    this.data.w2w_session = data.w2w_session;
                    wx.setStorageSync('w2w_session', data.w2w_session);
                    callback(data.w2w_session);
                }
            },
            fail: data => {
                console.error('登录失败', data.code + ': ' + data.message);
            },
            loadingTitle: '正在登录'
        });
    },
    // 按钮点击获取用户信息
    buttonGetUserInfo(e, callback) {

        var userRes = e.detail;

        if (userRes.errMsg == 'getUserInfo:ok') {
            console.log('获取用户信息成功', userRes);
            this.data.userInfo = userRes.userInfo;
            wx.setStorageSync('userInfo', userRes.userInfo);

            this.login(userRes, (w2w_session) => {
                this.refreshCart(cart => {
                    if (callback.success) {
                        callback.success({
                            userInfo: userRes.userInfo,
                            cart: cart
                        });
                    }
                });
            });
        } else {
            console.error('获取用户信息失败', userRes);
            if (callback.fail) callback.fail();
        }
    },
    // 登出
    logout() {
        this.data.user_id = null;
        this.data.w2w_session = null;
        this.data.userInfo = null;
        this.updateCart(null);
        wx.removeStorageSync('user_id');
        wx.removeStorageSync('w2w_session');
        wx.removeStorageSync('userInfo');
        wx.removeTabBarBadge({
            index: 3
        });
        wx.hideTabBarRedDot({
            index: 3
        });
        wx.reLaunch({
            url: '/pages/index/index'
        })
    },
    // 微信支付
    requestPayment(paymentData) {

        // 获取支付参数
        this.Util.network.GET({
            url: this.API('payment'),
            params: {
                id: paymentData.id,
                w2w_session: this.data.w2w_session
            },
            success: data => {
                console.log('支付参数', data);

                if (data.success) {

                    // 发起微信支付
                    wx.requestPayment({
                        timeStamp: data.timeStamp,
                        nonceStr: data.nonceStr,
                        package: data.package,
                        signType: 'MD5',
                        paySign: data.paySign,
                        success: res => {
                            if (paymentData.success) paymentData.success(res);
                        },
                        fail: res => {
                            if (paymentData.fail) paymentData.fail(res);
                        },
                        complete: res => {
                            console.log('wx.requestPayment回调', res);
                            if (paymentData.complete) paymentData.complete(res);
                        }
                    });
                } else {
                    if (paymentData.fail) paymentData.fail({});
                }

            }
        });
    },
    // 跳转产品详情页
    goProductDetail(e, newPage = true, nextNewPage = true) {
        var id = e.currentTarget.dataset.id,
            name = e.currentTarget.dataset.name,
            url = '/pages/product-detail/product-detail?id=' + id + '&name=' + encodeURIComponent(name) + '&popup=false&redirect=' + !nextNewPage;
        if (newPage) {
            wx.navigateTo({
                url: url
            })
        } else {
            wx.redirectTo({
                url: url
            })
        }
    },
    // 打开web-view
    openWebView(src) {
        wx.navigateTo({
            url: '/pages/web-view/web-view?src=' + encodeURIComponent(src)
        })
    },
    onLaunch() {
        console.log('App onLaunch');
        system.default.attachInfo();
        // 取出UserID Session 收货地址 用户信息
        this.data.user_id = wx.getStorageSync('user_id');
        this.data.w2w_session = wx.getStorageSync('w2w_session');
        this.data.address = wx.getStorageSync('address');
        this.data.userInfo = wx.getStorageSync('userInfo');
    }
})