// pages/product-list/product-list.js

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
        pageTitle: app.data.name,
        currency: app.data.currency,
        products: null,
        bottomStyle: null
    }),
    page: 1,
    options: null,
    goProductDetail(e) {
        app.goProductDetail(e);
    },
    addToCart(e) {
        this.doAddToCart(e, () => {
            this.setData({
                cart_quantity: app.data.cart_quantity
            });
        });
    },
    goCart: () => {
        wx.switchTab({
            url: '/pages/cart/cart'
        })
    },
    goTop() {
        wx.pageScrollTo({
            scrollTop: 0
        })
    },
    loadData() {

        if (this.data.bottomStyle == 'nomore' || this.data.bottomStyle == 'empty') {
            if (this.page != 1) {
                wx.showToast({
                    icon: 'none',
                    title: '没有更多了~'
                })
            }
            return;
        }

        app.Util.network.GET({
            url: app.API('product_list'),
            params: Object.assign({}, this.params, {
                page: this.page,
            }),
            success: data => {

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

                var setdata = {};
                var offset = (this.page - 1) * 10;
                for (var i = 0; i < data.length; i++) {
                    setdata['products[' + (offset + i) + ']'] = data[i];
                }
                this.setData(setdata);
                this.page++;
            }
        });
    },
    onLoad(options) {
        this.setData({
            currentPages: getCurrentPages().length
        });

        if (app.data.cart != null) {
            /*
            wx.showLoading({
                title: '正在加载',
                mask: true
            })*/
            this.setData({
                cart: app.data.cart
            }, () => {
                //wx.hideLoading();
            });
        } else {
            app.checkLogin({
                success: () => {
                    app.refreshCart(cart => {
                        this.setData({
                            cart_quantity: app.data.cart_quantity
                        });
                    });
                }
            })
        }

        this.options = options;
        this.page = 1;
        this.setData({
            products: [],
            bottomStyle: null,
            mode: this.options.mode != undefined ? this.options.mode : null
        });

        var title;
        if (this.params == undefined) {
            this.params = {};
        }

        switch (this.options.mode) {
            case 'all':
                title = '所有产品';
                break;
            case 'search':
                //url = 'product_search';
                title = '搜索 "' + decodeURIComponent(this.options.search) + '"';
                this.params.search = decodeURIComponent(this.options.search);
                break;
            case 'category':
                title = decodeURIComponent(this.options.name);
                this.params.category = this.options.id;
                break;
            case 'featured':
                title = '特色产品';
                this.params.featured = true;
                break;
            case 'on_sale':
                title = '促销产品';
                this.params.on_sale = true;
                break;
                /* W2W Extension, Name: w2w-products-favor, Code: favorCase */

/* W2W Extension, Name: w2w-products-favor, Code: favorCase */
                /* W2W Extension, Name: w2w-advanced-coupon, Code: couponCase */

/* W2W Extension, Name: w2w-advanced-coupon, Code: couponCase */
        }
        if (title != undefined) {
            this.title = title;
            wx.setNavigationBarTitle({
                title: title,
            })
            this.setPageTitle(title);
        }

        this.loadData();

        /* W2W Extension, Name: w2w-products-filter-and-orderby, Code: filterRequest */

/* W2W Extension, Name: w2w-products-filter-and-orderby, Code: filterRequest */
    },
    /* W2W Extension, Name: w2w-products-filter-and-orderby, Code: filterEvents */

/* W2W Extension, Name: w2w-products-filter-and-orderby, Code: filterEvents */
    /* W2W Extension, Name: w2w-products-favor, Code: favorEvents */

/* W2W Extension, Name: w2w-products-favor, Code: favorEvents */
    onShow() {
        this.setData({
            cart_quantity: app.data.cart_quantity
        });
    },
    onPullDownRefresh() {
        /* W2W Extension, Name: w2w-products-filter-and-orderby, Code: refreshFilter */

/* W2W Extension, Name: w2w-products-filter-and-orderby, Code: refreshFilter */
        this.onLoad(this.options);
    },
    onReachBottom() {
        this.loadData();
    },
    onShareAppMessage() {
        var params = this.options;
        if (app.isLoggedIn()) {
            params.uid = app.data.user_id;
        }
        var path = '/pages/product-list/product-list?' + app.Util.serialize(params);
        return {
            title: this.title,
            path: path
        }
    }
}))