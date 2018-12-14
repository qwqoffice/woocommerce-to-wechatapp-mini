// pages/product-detail/product-detail.js

/**
 * Project: WooCommerce微信小程序
 * Description: 将WooCommerce商城接入微信小程序
 * Author: 幻想小籽
 * Organization: QwqOffice (https://www.qwqoffice.com)
 */

const Zan = require('../../vendor/ZanUI/index');
const app = getApp();

Page(Object.assign({}, Zan.Stepper, Zan.Tab, Zan.TopTips, app.Methods, {
    data: Object.assign({}, app.Variables, {
        NAV_HEIGHT: wx.STATUS_BAR_HEIGHT + wx.DEFAULT_HEADER_HEIGHT + 'px',
        NAV_BAR_HEIGHT: wx.DEFAULT_HEADER_HEIGHT + 'px',
        pageTitle: app.data.name,
        logo: app.data.logo,
        currency: app.data.currency,
        imgHeights: [],
        imgWidth: 750,
        current: 0,
        isVariationPopup: false,
        isAttributePopup: false,
        isSharePopup: false,
        isPosterPopup: false,
        quantity: 1,
        productTabSelected: 'description',
        productTabList: [{
            id: 'description',
            title: '详情'
        }],
        descriptionPadding: 10,
    }),
    // 随便逛逛
    goShopping() {
        wx.redirectTo({
            url: '/pages/product-list/product-list?mode=all'
        })
    },
    // 打开产品选择弹窗
    openVariationPopup() {
        this.setData({
            isVariationPopup: true
        });
    },
    // 关闭产品选择弹窗
    closeVariationPopup() {
        this.setData({
            isVariationPopup: false
        });
    },
    // 打开产品属性弹窗
    openAttributePopup() {
        this.setData({
            isAttributePopup: true
        });
    },
    // 关闭产品属性弹窗
    closeAttributePopup() {
        this.setData({
            isAttributePopup: false
        });
    },
    // 打开产品分享弹窗
    openSharePopup() {
        this.setData({
            isSharePopup: true
        });
    },
    // 关闭产品分享弹窗
    closeSharePopup() {
        this.setData({
            isSharePopup: false
        });
    },
    // 打开海报弹窗
    openPosterPopup(posterImage) {
        this.setData({
            isPosterPopup: true,
            posterImage: posterImage
        });
    },
    // 关闭海报弹窗
    closePosterPopup() {
        this.setData({
            isPosterPopup: false
        });
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
    // 选项卡变更
    handleZanTabChange({
        componentId,
        selectedId
    }) {
        if (componentId == 'product-tab') {
            this.setData({
                productTabSelected: selectedId
            });
        }
    },
    // 登录成功
    loginSuccess() {
        this.onPullDownRefresh();
        /*this.setData({
            cart_quantity: app.data.cart_quantity
        });*/
    },
    // 选项变更
    variationChange(e) {

        var attribute = e.currentTarget.dataset.attriubte,
            oldOption = e.currentTarget.dataset.oldOption,
            option = e.currentTarget.dataset.option,
            isAvailable = e.currentTarget.dataset.isAvailable;

        if (!isAvailable) {
            return;
        }

        if (oldOption == option) {
            var default_attributes = app.Util.cloneObj(this.data.product.default_attributes);
            delete default_attributes[attribute];
            this.setData({
                ['product.default_attributes']: default_attributes
            });
        } else {
            this.setData({
                ['product.default_attributes.' + attribute + '.option']: option
            });
        }

        this.setSelected();
    },
    // 设置选项 并从已选择选项获取变量产品ID
    setSelected() {

        if (this.data.product.type != 'variable') {
            return;
        }

        var attributes = app.Util.cloneObj(this.data.product.attributes),
            default_attributes = this.data.product.default_attributes,
            variations = this.data.product.variations;

        var variationCount = this.data.variationCount;
        var selectedAllVariation = Object.keys(default_attributes).length == variationCount;

        // 筛选可用选项
        for (var attr_key in attributes) {

            if (attributes[attr_key].variation == false) {
                continue;
            }

            var attribute_options = attributes[attr_key].options;

            for (var opt_index in attribute_options) {
                var attribute_option = attribute_options[opt_index];
                var default_attributes_clone = app.Util.cloneObj(default_attributes);

                if (default_attributes_clone[attr_key]) {
                    default_attributes_clone[attr_key].option = attribute_option.slug;
                } else {
                    default_attributes_clone = Object.assign({}, default_attributes_clone, {
                        [attr_key]: {
                            option: attribute_option.slug
                        }
                    });
                }

                if (this.findVariationMatchAttributes(variations, default_attributes_clone)) {
                    attributes[attr_key].options[opt_index].is_available = true;
                } else {
                    attributes[attr_key].options[opt_index].is_available = false;
                }
            }
        }

        this.setData({
            selectedAllVariation: selectedAllVariation,
            ['product.attributes']: attributes
        });

        var selectedVariation = null,
            inStock = true;

        // 已选择所有变量
        if (selectedAllVariation) {

            // 查找选择的变量
            selectedVariation = this.findVariationMatchAttributes(variations, default_attributes);
            if (selectedVariation) {

                inStock = selectedVariation.in_stock;

                var selectedAttributes = {};
                for (var attr_key in default_attributes) {
                    selectedAttributes['variation[' + attr_key + ']'] = default_attributes[attr_key].option;
                }
                this.setData({
                    selectedAttributes: selectedAttributes,
                    selectedVariation: selectedVariation
                });
            } else {
                this.clearSelected();
            }
        } else {
            this.setData({
                selectedVariation: selectedVariation
            });
        }

    },
    clearSelected() {
        this.setData({
            ['product.default_attributes']: []
        });
        this.setSelected();
    },
    // 属性是否有对应变量
    findVariationMatchAttributes(variations, attributes) {

        for (var v_index in variations) {
            var variation = variations[v_index];
            var match = true;

            for (var attr_key in attributes) {
                var attribute = attributes[attr_key].option,
                    variation_attribute = variation.attributes[attr_key].option;

                if (variation_attribute == '') {
                    continue;
                }
                if (variation_attribute != attribute || variation.visible == false) {
                    match = false;
                    break;
                }
            }
            if (!match) {
                continue;
            }
            return variation;
        }
        return null;
    },
    // 数量选择器处理
    handleZanStepperChange(e) {
        this.setData({
            quantity: e.stepper
        });
    },
    // 添加到购物车
    detailAddToCart(e) {
        var product = this.data.product;
        var params = {
            product_id: product.id,
            quantity: this.data.quantity,
        };
        // 可变产品
        if (product.type == 'variable') {
            if (this.data.selectedAllVariation) {

                if (e.currentTarget.id == 'add-to-cart') {
                    this.openVariationPopup();
                    return;
                }

                params.variation_id = this.data.selectedVariation.id;
                params = Object.assign(params, this.data.selectedAttributes);

                /*// 点击非弹出层按钮时只添加一个
                if (e.currentTarget.id == 'add-to-cart') {
                	params.quantity = 1;
                }*/
            } else {
                if (e.currentTarget.id == 'add-to-cart') {
                    this.openVariationPopup();
                }
                return;
            }
        } else {
            if (!product.in_stock) {
                return;
            }
        }

        this.checkLogin(() => {
            app.Util.network.POST({
                url: app.API('add_to_cart'),
                params: Object.assign({}, {
                        w2w_session: app.data.w2w_session
                    },
                    params
                ),
                success: data => {
                    if (data.errors.length > 0) {
                        this.showZanTopTips(data.errors);
                    } else {
                        this.closeVariationPopup();
                        wx.showToast({
                            title: '添加成功',
                        })
                    }
                    app.updateCart(data);
                    this.setData({
                        cart_quantity: app.data.cart_quantity
                    });
                }
            });
        });
    },
    // 产品画廊全屏查看图片
    galleryViewFullScreen(e) {

        var product_images = this.data.product.images,
            currentURL = e.currentTarget.dataset.src,
            images_urls = [];
        for (var i in product_images) {
            images_urls.push(product_images[i].shop_single);
        }
        wx.previewImage({
            current: currentURL,
            urls: images_urls,
        })
    },
    // 选项弹窗全屏查看图片
    variationViewFullScreen(e) {
        var url = e.currentTarget.dataset.src;
        wx.previewImage({
            urls: [url]
        })
    },
    // 移除指定Tab
    removeTab(name) {
        for (var i in this.data.productTabList) {
            if (this.data.productTabList[i].id == name) {
                this.data.productTabList.splice(i, 1);
                this.setData({
                    productTabList: this.data.productTabList
                });
                break;
            }
        }
    },
    /* W2W Extension, Name: w2w-products-favor, Code: switchFavor */

    /* W2W Extension, Name: w2w-products-favor, Code: switchFavor */
    // 分享海报
    sharePoster() {
        this.checkLogin(() => {
            app.Util.network.GET({
                url: app.API('product_qrcode'),
                params: {
                    id: this.data.id,
                    w2w_session: app.data.w2w_session
                },
                success: data => {
                    wx.showLoading({
                        title: '正在生成海报',
                        mask: true,
                    });
                    wx.downloadFile({
                        url: data,
                        success: qrcodeRes => {
                            console.log('二维码图片本地位置', qrcodeRes.tempFilePath);
                            wx.downloadFile({
                                url: this.data.product.images[0].shop_single,
                                success: imageRes => {
                                    console.log('产品图片本地位置', imageRes.tempFilePath);
                                    wx.getImageInfo({
                                        src: imageRes.tempFilePath,
                                        success: res => {
                                            var resize = app.Util.resizeRect(res.width, res.height, 600, 600);
                                            this.createPosterLocal(imageRes.tempFilePath, qrcodeRes.tempFilePath, this.data.product, resize);
                                        }
                                    });

                                }
                            })
                        }
                    })
                }
            });
        });
    },
    // 生成分享海报
    createPosterLocal(imageLocal, qrcodeLocal, product, resize) {
        var context = wx.createCanvasContext('poster_canvas');
        context.setFillStyle('#ffffff'); //填充背景色
        context.fillRect(0, 0, 600, 970);
        context.drawImage(imageLocal, resize.x, resize.y, resize.width, resize.height); //绘制首图
        context.drawImage(qrcodeLocal, 400, 620, 180, 180); //绘制二维码
        context.setFillStyle('#000000');
        context.setFontSize(20);
        context.setTextAlign('center');
        context.fillText('长按识别小程序码立即购买', 300, 940);
        context.setFillStyle('#000000');
        this.drawProductMeta(context, product); //产品信息
        context.draw(false, () => {
            wx.canvasToTempFilePath({
                canvasId: 'poster_canvas',
                success: res => {
                    var tempFilePath = res.tempFilePath;
                    wx.hideLoading();
                    console.log('海报图片路径', res.tempFilePath);
                    this.closeSharePopup();
                    this.openPosterPopup(res.tempFilePath);
                },
                fail: function(res) {
                    console.log(res);
                }
            });
        });
    },
    // 绘制标题、短描述
    drawProductMeta(context, product) {
        context.setFillStyle('#000000');
        context.setTextAlign('left');

        var title = product.name,
            excerpt = app.Util.stripHTML(product.short_description_html);

        var titleLength = app.Util.getStrLength(title),
            titleSingleLine = title.length <= 14;
        if (titleLength <= 14) {
            // 14字以内绘制成一行，美观一点
            context.setFontSize(40);
            context.fillText(title, 40, 660);
        } else {
            // 标题字数很多的，只绘制前28个字
            context.setFontSize(30);
            context.fillText(title.substring(0, 11), 40, 660);

            if (titleLength <= 42) {
                context.fillText(title.substring(11, 22), 40, 710);
            } else {
                context.fillText(title.substring(11, 20) + '...', 40, 710);
            }

        }

        context.setFontSize(24);
        context.setTextAlign('left');
        context.setGlobalAlpha(0.7);

        var excerptPerLine = 14,
            excerptlineHeight = 20,
            excerptLines = Math.ceil(excerpt.length / excerptPerLine),
            more = excerpt.length > 2 * excerptPerLine,
            excerptStartY = titleSingleLine ? 720 : 770;
        excerptLines = excerptLines > 2 ? 2 : excerptLines;

        var lineStartY = excerptStartY;
        for (var i = 1; i <= excerptLines; i++) {
            var moreStr = more && i == excerptLines ? '...' : '';
            lineStartY = excerptStartY + (i - 1) * excerptlineHeight * 2;
            context.fillText(excerpt.substring((i - 1) * excerptPerLine, i * excerptPerLine) + moreStr, 40, lineStartY);
        }

        //绘制价格
        context.setGlobalAlpha(1);
        context.setFillStyle('#96588a');
        context.setTextAlign('left');
        context.setFontSize(60);

        var currency = app.data.currency,
            priceStr = '';

        if (product.type == 'variable' && product.min_price != product.max_price) {
            priceStr = currency + product.min_price + '-' + currency + product.max_price;
        } else if (product.price != '') {
            priceStr = currency + product.price;
        }

        context.fillText(priceStr, 40, excerptLines > 0 ? lineStartY + excerptlineHeight * 2 + 40 : lineStartY + 20);

        context.save();
    },
    savePoster() {
        wx.saveImageToPhotosAlbum({
            filePath: this.data.posterImage,
            success: result => {
                this.closePosterPopup();
                wx.showModal({
                    title: '提示',
                    content: '海报已存入手机相册，赶快分享吧~',
                    showCancel: false
                })
            },
            fail: err => {
                console.log('保存海报错误', err);
                this.closePosterPopup();
                if (err.errMsg === 'saveImageToPhotosAlbum:fail auth deny') {
                    wx.showToast({
                        icon: 'none',
                        title: '请到设置中打开保存图片权限'
                    })
                }
            }
        });
    },
    addToCart(e) {
        this.doAddToCart(e, () => {
            this.setData({
                cart_quantity: app.data.cart_quantity
            });
        }, false);
    },
    goProductDetail(e) {
        app.goProductDetail(e, !this.data.redirect, false);
    },
    wxmlGoProductDetail(e) {
        var id = e.detail.id;
        wx.navigateTo({
            url: '/pages/product-detail/product-detail?id=' + id
        })
    },
    wxmlGoPostDetail(e) {
        var id = e.detail.id;
        wx.navigateTo({
            url: '/pages/post-detail/post-detail?id=' + id
        })
    },
    goCart() {
        wx.switchTab({
            url: '/pages/cart/cart'
        })
    },
    onLoad(options) {
        this.setData({
            currentPages: getCurrentPages().length
        });

        this.options = options;
        if (app.data.cart != null) {
            this.setData({
                cart: app.data.cart
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

        // 加载成功前即显示标题
        var product_title = decodeURIComponent(options.name);
        if (product_title != 'undefined') {
            wx.setNavigationBarTitle({
                title: product_title
            })
            this.setPageTitle(product_title);
        }

        this.setData({
            id: options.id,
            product: null,
            product_name: product_title,
            cart_quantity: app.data.cart_quantity,
            selectedAllVariation: null,
            selectedVariation: null,
            selectedAttributes: null,
            redirect: options.redirect === 'true'
        });

        app.Util.network.GET({
            url: app.API('product') + options.id,
            params: app.isLoggedIn() ? {
                w2w_session: app.data.w2w_session
            } : {},
            success: data => {

                var product = data;

                // 获取相关产品
                if (product.related_ids.length > 0) {
                    var related_ids = {};
                    for (var i in product.related_ids) {
                        related_ids['include[' + i + ']'] = product.related_ids[i];
                    }
                    app.Util.network.GET({
                        url: app.API('product_list'),
                        params: Object.assign({},
                            related_ids, {
                                orderby: 'include',
                                per_page: product.related_ids.length
                            }
                        ),
                        showLoading: false,
                        success: data => {
                            this.setData({
                                related_products: data
                            });
                        }
                    });
                }

                // 产品变量、可见属性个数
                var variationCount = 0,
                    visibleAttributeCount = 0;
                for (var i in product.attributes) {
                    if (product.attributes[i].variation == true) {
                        variationCount++;
                    }
                    if (product.attributes[i].visible == true) {
                        visibleAttributeCount++;
                    }
                }

                // 总库存
                var totalStock = null;
                if (product.type == 'variable') {
                    for (var i in product.variations) {
                        if (product.variations[i].in_stock && product.variations[i].stock_quantity != null) {
                            if (totalStock == null) totalStock = 0;
                            totalStock += product.variations[i].stock_quantity;
                        }
                    }
                } else {
                    totalStock = product.stock_quantity;
                }

                this.setData({
                    product: product,
                    variationCount: variationCount,
                    visibleAttributeCount: visibleAttributeCount,
                    totalStock: totalStock,
                    /* W2W Extension, Name: w2w-products-favor, Code: cartPosition */

                    /* W2W Extension, Name: w2w-products-favor, Code: cartPosition */
                    //isPopup: options.popup == 'true' ? true : false
                });
                wx.setNavigationBarTitle({
                    title: product.name
                })
                this.setPageTitle(product.name);

                this.setSelected();

                // 描述和短描述
                //var WxParse = require('../../vendor/wxParse/wxParse.js');
                //WxParse.wxParse('short_description', 'html', data.short_description, this, 5);
                //WxParse.wxParse('description', 'html', data.description, this, 5);
                // 弹窗
                if (options.popup == 'true') this.openVariationPopup();
            },
            // 获取产品失败
            fail: () => {
                this.setData({
                    product: false
                });
            }
        });
    },
    onPullDownRefresh() {
        this.onLoad({
            id: this.data.id,
            name: this.data.product_name,
            popup: false
        });
    },
    onReachBottom() {

    },
    onShareAppMessage() {
        this.closeSharePopup();
        var path = '/pages/product-detail/product-detail?id=' + this.data.id + '&name=' + this.data.product.name;
        if (app.isLoggedIn()) {
            path = path + '&uid=' + app.data.user_id;
        }
        return {
            title: this.data.product.name,
            path: path,
            imageUrl: this.data.product.images[0].shop_single
        }
    }
}))