// pages/category/category.js

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
        pageTitle: '分类',
        style: 'mi',
        categories: null,
        tabSelectedID: 0
    }),
    goFeatureProducts() {
        wx.navigateTo({
            url: '/pages/product-list/product-list?mode=featured'
        })
    },
    goOnSaleProducts() {
        wx.navigateTo({
            url: '/pages/product-list/product-list?mode=on_sale'
        })
    },
    goCategoryProducts(e) {
        var id = e.currentTarget.dataset.id,
            name = e.currentTarget.dataset.name;
        wx.navigateTo({
            url: '/pages/product-list/product-list?mode=category&id=' + id + '&name=' + encodeURIComponent(name)
        })
    },
    // 选项卡切换
    tabChange(e) {
        var selected = e.currentTarget.dataset.id;
        this.setData({
            tabSelected: selected
        });
    },
    onLoad(options) {
        app.Util.network.GET({
            url: app.API('category'),
            // 2018-03-16 添加 pre_page 参数获取所有分类
            params: {
                per_page: 0
            },
            success: data => {
                var firstTopCategory = 0;
                for (var i in data) {
                    if (data[i].parent == 0) {
                        firstTopCategory = data[i].id
                        break;
                    }
                }
                this.setData({
                    categories: data,
                    tabSelected: firstTopCategory
                });
            }
        });
    },
    onShow() {

    },
    onPullDownRefresh() {
        this.onLoad();
    },
    onReachBottom() {

    }
}))