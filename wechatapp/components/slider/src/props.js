var _objectToStyle = require('./objectToStyle.js');

var _objectToStyle2 = _interopRequireDefault(_objectToStyle);

function _interopRequireDefault(obj) {
    return obj && obj.__esModule ? obj : {
        default: obj
    };
}

function _defineProperty(obj, key, value) {
    if (key in obj) {
        Object.defineProperty(obj, key, {
            value: value,
            enumerable: true,
            configurable: true,
            writable: true
        });
    } else {
        obj[key] = value;
    }
    return obj;
}

module.exports = Behavior({
    properties: {
        value: {
            type: [Number, Array],
            value: 0,
            observer: function observer(val) {
				
                var _setData;

                if (this.data.unlimited) {
                    this.data.max += this.data.step;
                }

                this.data.value[0] = this.data.value[0] < this.data.min ? this.data.min : this.data.value[0];
                this.data.value[1] = this.data.value[1] < this.data.min ? this.data.min : this.data.value[1];
                this.data.value[0] = this.data.value[0] > this.data.max ? this.data.max : this.data.value[0];
                this.data.value[1] = this.data.value[1] > this.data.max ? this.data.max : this.data.value[1];
                this.data.value[0] = this.data.value[0] > this.data.value[1] ? this.data.value[1] : this.data.value[0];

                // value 转 offsetX

                this.data.offsetX = this.value2Offset(this.data.value[0]);
                this.data.offset2X = this.value2Offset(this.data.value[1]);

                this.setData((_setData = {
                    offset2X: this.data.offset2X,
                    offsetX: this.data.offsetX,
                    value: val,
                    max: this.data.max,
                    sliderBarAnimateStyle: (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj()),
                    sliderBarWrapStyle: (0, _objectToStyle2.default)(this.sliderBarWrapStyleObj()),
                    sliderBarWrapDynamicStyle: (0, _objectToStyle2.default)(this.sliderBarWrapDynamicStyleObj()),
                    sliderLineDynamicStyle: (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj())
                }, _defineProperty(_setData, 'sliderBarAnimateStyle', (0, _objectToStyle2.default)(this.sliderBarAnimateStyleObj())), _defineProperty(_setData, 'sliderBarWrap2DynamicStyle', (0, _objectToStyle2.default)(this.sliderBarWrap2DynamicStyleObj())), _defineProperty(_setData, 'sliderLineDynamicStyle', (0, _objectToStyle2.default)(this.sliderLineDynamicStyleObj())), _defineProperty(_setData, 'thumbDownCustomStyle', ''), _setData));
            }
        },
        sliderWeight: {
            type: Number,
            value: 2
        },
        backgroundColor: {
            type: String,
            value: '#CCC'
        },
        barBorderRadius: {
            type: Number,
            value: 1
        },
        thumbSize: {
            type: Number,
            value: 28
        },
        activeColor: {
            type: String
        },
        height: {
            type: Number,
            value: 70
        },
        width: {
            type: Number,
            value: 260
        },
        containerWidth: {
            type: Number,
            value: 0
        },
        min: {
            type: Number,
            value: 0
        },
        max: {
            type: Number,
            value: 100
        },
        step: {
            type: Number,
            value: 1
        },
        thumbDownStyle: {
            type: Object,
            value: {}
        },
        unlimited: {
            type: Boolean,
            value: false
        },
    }
});