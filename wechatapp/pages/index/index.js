// pages/index/index.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const Zan = require('../../vendor/ZanUI/index');
const app = getApp();
const currentPages = getCurrentPages();

Page(Object.assign({}, Zan.TopTips, Zan.NoticeBar, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: app.data.name,
        currency: app.data.currency,
        currentPages: currentPages,
        banner: null,
        imgHeights: [],
        imgWidth: 750,
        current: 0,
        lastest_products: null,
        clearSearchShow: false,
    }),
    addToCart(e) {
        this.doAddToCart(e);
    },
    imageLoad(e) {

        // 获取图片下标
        var index = e.currentTarget.dataset.index;
        // 获取图片真实宽度  
        var imgWidth = e.detail.width,
            imgHeight = e.detail.height,
            // 宽高比  
            ratio = imgWidth / imgHeight;
        // 计算的高度值  
        var viewHeight = 750 / ratio;
        var imgHeight = viewHeight;
        var imgHeights = this.data.imgHeights;
        // 把每一张图片的高度记录到数组里  
        imgHeights[index] = imgHeight;
        this.setData({
            imgHeights: imgHeights
        })
    },
    imageChange(e) {
        this.setData({
            current: e.detail.current
        });
    },
    // 轮播图点击
    bannerTap(e) {
        var url = e.currentTarget.dataset.url;
        if (url != '') {
            if (/^http/.test(url)) {
                app.openWebView(url);
            } else {
                wx.navigateTo({
                    url: url
                })
            }
        }
    },
    // 跳转搜索页
    goSearch() {
        wx.navigateTo({
            url: '/pages/search/search'
        })
    },
    // 跳转产品详情页
    goProductDetail(e) {
        app.goProductDetail(e);
    },
    // 跳转所有产品页
    goViewAll() {
        wx.navigateTo({
            url: '/pages/product-list/product-list?mode=all'
        })
    },
    loginSuccess() {
        app.refreshOrdersInfo();
    },
    /* W2W Extension, Name: w2w-advanced-coupon, Code: indexCouponEvents */

/* W2W Extension, Name: w2w-advanced-coupon, Code: indexCouponEvents */
    
    onLoad() {
        /*
        app.Util.network.POST({
            url: app.API('coupon'),
            params: {
				w2w_session: app.data.w2w_session,
                code: 'qo20186'
            },
            success: data => {
                console.log(data);
            }
        });
        console.log('index onLoad');
        //app.Util.getVideoInfo(this, 'i06464fu5ab');*/

        app.Util.network.GET({
            url: app.API('index'),
            success: data => {
                this.setData(data);
                if (data.notice != null) {
                    this.initZanNoticeBarScroll('noticebar');
                }
                if (app.data.cart == null) {
                    app.checkLogin({
                        success: () => {
                            app.refreshCart(() => {
                                app.refreshOrdersInfo();
                            });
                        }
                    });
                }
            }
        });
    },
    onPullDownRefresh() {
        this.onLoad();
    },
    onShow() {
        if (app.data.cart != null) {
            app.updateCart(app.data.cart);
        }
    },
    onShareAppMessage() {
        var path = '/pages/index/index';
        if (app.isLoggedIn()) {
            path = path + '?uid=' + app.data.user_id;
        }
        return {
            path: path
        }
    }
}))