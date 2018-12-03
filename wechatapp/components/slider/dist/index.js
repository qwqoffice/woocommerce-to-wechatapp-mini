var _objectToStyle = require('../src/objectToStyle.js');

var _objectToStyle2 = _interopRequireDefault(_objectToStyle);

var _sliderBar = require('../src/sliderBar1.js');

var _sliderBar2 = _interopRequireDefault(_sliderBar);

var _sliderBar3 = require('../src/sliderBar2.js');

var _sliderBar4 = _interopRequireDefault(_sliderBar3);

var _sliderBar5 = require('../src/sliderBar.js');

var _sliderBar6 = _interopRequireDefault(_sliderBar5);

var _sliderLine = require('../src/sliderLine.js');

var _sliderLine2 = _interopRequireDefault(_sliderLine);

var _props = require('../src/props.js');

var _props2 = _interopRequireDefault(_props);

function _interopRequireDefault(obj) {
    return obj && obj.__esModule ? obj : {
        default: obj
    };
}

function _toConsumableArray(arr) {
    if (Array.isArray(arr)) {
        for (var i = 0, arr2 = Array(arr.length); i < arr.length; i++) {
            arr2[i] = arr[i];
        }
        return arr2;
    } else {
        return Array.from(arr);
    }
}

Component({
    behaviors: [_sliderBar6.default, _sliderBar2.default, _sliderBar4.default, _sliderLine2.default, _props2.default],
    /**
     * 组件的属性列表
     */

    created: function created() {
        // this.data.max += this.data.step
    },
    attached: function attached() {
		/*if (this.data.unlimited) {
			this.data.max += this.data.step;
		}*/
		
        this.data.fixed = this.data.step.toString().split('.')[1];
        if (!this.data.fixed) {
            this.data.fixed = '';
        }

        this.data.value[0] = this.data.value[0] < this.data.min ? this.data.min : this.data.value[0];
        this.data.value[1] = this.data.value[1] < this.data.min ? this.data.min : this.data.value[1];
        this.data.value[0] = this.data.value[0] > this.data.max ? this.data.max : this.data.value[0];
        this.data.value[1] = this.data.value[1] > this.data.max ? this.data.max : this.data.value[1];
        this.data.value[0] = this.data.value[0] > this.data.value[1] ? this.data.value[1] : this.data.value[0];
        this.offsetX = this.value2Offset(this.data.value[0]);
        this.offset2X = this.value2Offset(this.data.value[1]);
        this.data.presentValue = [].concat(_toConsumableArray(this.properties.value));

        this.data.valueAlignLeft = 20;
        this.data.valueAlignWidth = 40;

        // value 转 offsetX

        this.data.offsetX = this.value2Offset(this.data.value[0]);
        this.data.offset2X = this.value2Offset(this.data.value[1]);

        // 更新页面
        this.setData({
            value: this.data.value,
            max: this.data.max,
            offsetX: this.data.offsetX,
            offset2X: this.data.offset2X,
            sliderStyle: (0, _objectToStyle2.default)(this.sliderStyleObj()),
            sliderLineTouchStyle: (0, _objectToStyle2.default)(this.sliderLineTouchStyleObj()),
            sliderLineBackgroundStyle: (0, _objectToStyle2.default)(this.sliderLineBackgroundStyleObj()),
            sliderLineStyle: (0, _objectToStyle2.default)(this.sliderLineStyleObj()),
            sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
            sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj()),
            sliderBarStyle: (0, _objectToStyle2.default)(this.sliderBarStyleObj()),
            sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
            sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
            valueAlignWidth: this.data.valueAlignWidth
        });
    },
    ready: function ready() {
        var _this = this;

        wx.createSelectorQuery().in(this).select('.slider-line-touch').boundingClientRect(function(res) {

            _this.data.offsetLeft = res.left - _this.data.containerWidth;
            _this.reMathWrapLeft();
        }).exec();
    },

    /**
     * 组件的初始数据
     */
    data: {
        valueAlignLeft: 0,
        valueAlignWidth: 0,
        zIndex: 0,
        zIndex2: 0,
        startX: 0,
        offsetX: 0,
        start2X: 0,
        offset2X: 0,
        presentValue: 0,
        animate: 'all .3s'
    },

    /**
     * 组件的方法列表
     */
    methods: {
        reMathWrapLeft: function reMathWrapLeft(container_width) {
            var _this2 = this;

            wx.createSelectorQuery().in(this).select('.slider-bar-wrap1').boundingClientRect(function(res) {
                _this2.data.wrap1Left = res.left;
            }).exec();

            wx.createSelectorQuery().in(this).select('.slider-bar-wrap2').boundingClientRect(function(res) {
                _this2.data.wrap2Left = res.left;
            }).exec();
        },
        touchStartLineHandler: function touchStartLineHandler(e) {
            //this.data.animate = 'all .3s';

            if (Math.abs(e.touches[0].clientX - this.data.wrap1Left - this.data.thumbSize / 2) < Math.abs(e.touches[0].clientX - this.data.wrap2Left - this.data.thumbSize / 2)) {
                this.data.offsetX = e.touches[0].clientX - this.data.offsetLeft;
                this.data.value[0] = this.offset2Value(this.data.offsetX);
                this.data.offsetX = this.value2Offset(this.data.value[0]);

                if (this.data.fixed) {
                    this.data.value[0] = Number(this.data.value[0].toFixed(this.data.fixed.length));
                } else {
                    this.data.value[0] = this.data.min + Math.round((this.data.value[0] - this.data.min) / this.data.step) * this.data.step;
                }

                this.data.offsetX = this.value2Offset(this.data.value[0]);

            } else {
                this.data.offset2X = e.touches[0].clientX - this.data.offsetLeft;
                this.data.value[1] = this.offset2Value(this.data.offset2X);
                this.data.offset2X = this.value2Offset(this.data.value[1]);

                if (this.data.fixed) {
                    this.data.value[1] = Number(this.data.value[1].toFixed(this.data.fixed.length));
                } else {
                    this.data.value[1] = this.data.min + Math.round((this.data.value[1] - this.data.min) / this.data.step) * this.data.step;
                }
                this.data.offset2X = this.value2Offset(this.data.value[1]);
            }

            //this.data.value = [parseInt(this.offset2Value(this.data.offsetX)), parseInt(this.offset2Value(this.data.offset2X))];

            this.setData({
                offset2X: this.data.offset2X,
                offsetX: this.data.offsetX,
                value: this.data.value,
                sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj()),
                sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
                sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
                sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj()),
                thumbDownCustomStyle: '',
                thumbDown2CustomStyle: ''
            });

            this.reMathWrapLeft();
            this.emitValue();
        },
        value2Offset: function value2Offset(value) {
            return Number(value - this.data.min) / (this.data.max - this.data.min) * (this.data.width - this.data.valueAlignWidth);
        },
        offset2Value: function offset2Value(offset) {
            return offset / (this.data.width - this.data.valueAlignWidth) * (this.data.max - this.data.min) + this.data.min;
        },
        touchStartHandler: function touchStartHandler(e) {
            this.data.startX = e.touches[0].clientX - this.data.offsetX;
            this.data.zIndex = 1;
            this.data.zIndex2 = 0;
            //this.data.animate = '';
            this.data.firstIn = true;
            this.setData({
                sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
                sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj())
            });
        },
        touchMoveHandler: function touchMoveHandler(e) {
            if (this.data.firstIn) {
                this.data.firstIn = false;
                this.setData({
                    thumbDownCustomStyle: (0, _objectToStyle2.default)(this.data.thumbDownStyle)
                });
            }
            // 判断offsetX的距离
            this.data.offsetX = e.touches[0].clientX - this.data.startX;
            if (this.data.offsetX > this.data.offset2X) {
                this.data.offsetX = this.data.offset2X;
            } else if (this.data.offsetX < 0) {
                this.data.offsetX = 0;
            } else if (this.data.offsetX > this.data.width - this.data.valueAlignWidth) {
                this.data.offsetX = this.data.width - this.data.valueAlignWidth;
            }

            // 赋值data并更新
            this.data.value = [Number(this.offset2Value(this.data.offsetX).toFixed(this.data.fixed.length)), Number(this.offset2Value(this.data.offset2X).toFixed(this.data.fixed.length))];

            if (this.data.fixed) {
                this.data.value[0] = Number(this.data.value[0].toFixed(this.data.fixed.length));
            } else {
                this.data.value[0] = this.data.min + Math.round((this.data.value[0] - this.data.min) / this.data.step) * this.data.step;
            }

            this.data.offsetX = this.value2Offset(this.data.value[0]);

            this.setData({
                value: this.data.value,
                sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
                sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj())
            });
            //this.emitValue();
        },
        touchEndHandler: function touchEndHandler(e) {
            //this.data.animate = 'all .3s';
            if (this.data.fixed) {
                this.data.value[0] = Number(this.data.value[0].toFixed(this.data.fixed.length));
            } else {
                this.data.value[0] = this.data.min + Math.round((this.data.value[0] - this.data.min) / this.data.step) * this.data.step;
            }

            this.data.offsetX = this.value2Offset(this.data.value[0]);
            //this.data.animate = 'all .3s';
            this.setData({
                thumbDownCustomStyle: '',
                offsetX: this.data.offsetX,
                value: this.data.value,
                sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj()),
                sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
                sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj())
            });
            this.reMathWrapLeft();
            this.emitValue();
        },
        touchStart2Handler: function touchStart2Handler(e) {
            this.data.start2X = e.touches[0].clientX - this.data.offset2X;
            this.data.zIndex = 0;
            this.data.zIndex2 = 1;
            //this.data.animate = '';
            //this.data.animate = 'all .3s';
            this.data.firstIn = true;
            this.setData({
                sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
                sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj())
            });
        },
        touchMove2Handler: function touchMove2Handler(e) {
            if (this.data.firstIn) {
                this.data.firstIn = false;
                this.setData({
                    thumbDown2CustomStyle: (0, _objectToStyle2.default)(this.data.thumbDownStyle)
                });
            }
            // 判断offsetX的距离
            this.data.offset2X = e.touches[0].clientX - this.data.start2X;
            if (this.data.offset2X < this.data.offsetX) {
                this.data.offset2X = this.data.offsetX;
            } else if (this.data.offset2X > this.data.width - this.data.valueAlignWidth) {
                this.data.offset2X = this.data.width - this.data.valueAlignWidth;
            } else if (this.data.offset2X < 0) {
                this.data.offset2X = 0;
            }

            this.data.value = [Number(this.offset2Value(this.data.offsetX).toFixed(this.data.fixed.length)), Number(this.offset2Value(this.data.offset2X).toFixed(this.data.fixed.length))];

            if (this.data.fixed) {
                this.data.value[1] = Number(this.data.value[1].toFixed(this.data.fixed.length));
            } else {
                this.data.value[1] = this.data.min + Math.round((this.data.value[1] - this.data.min) / this.data.step) * this.data.step;
            }

            this.data.offset2X = this.value2Offset(this.data.value[1]);

            this.setData({
                value: this.data.value,
                sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
                sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
                sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj())
            });

            //this.emitValue();
        },
        touchEnd2Handler: function touchEnd2Handler(e) {
            if (this.data.fixed) {
                this.data.value[1] = Number(this.data.value[1].toFixed(this.data.fixed.length));
            } else {
                this.data.value[1] = this.data.min + Math.round((this.data.value[1] - this.data.min) / this.data.step) * this.data.step;
            }
            this.data.offset2X = this.value2Offset(this.data.value[1]);
            //this.data.animate = 'all .3s';
            this.setData({
                offset2X: this.data.offset2X,
                value: this.data.value,
                sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj()),
                sliderBarWrap2DynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj()),
                sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj()),
                thumbDown2CustomStyle: ''
            });
            this.reMathWrapLeft();
            this.emitValue();
            // this.triggerEvent('change', { 'value': this.data.value })
        },
        emitValue: function emitValue() {
            if (this.data.unlimited) {
                var value = void 0;
                value = [].concat(_toConsumableArray(this.data.value));
                value[0] = value[0] > this.data.max - this.data.step ? 'unlimited' : value[0];
                value[1] = value[1] > this.data.max - this.data.step ? 'unlimited' : value[1];
                this.triggerEvent('change', {
                    'value': value
                });
            } else {
                this.triggerEvent('change', {
                    'value': this.data.value
                });
            }
        },
        sliderStyleObj: function sliderStyleObj() {
            var style = {};
            style.left = 0;
            style.width = this.data.width + 'px';
            style.height = this.data.height + 'px';
            return style;
        }
    }
});